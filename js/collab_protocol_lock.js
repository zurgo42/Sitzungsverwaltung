/**
 * collab_protocol_lock.js - Lock-basierte kollaborative Mitschrift
 * Version: 4.0 - Vereinfachtes Lock-System (kein Queue mehr)
 *
 * Protokollführung = 2 Felder (Hauptsystem + Fortsetzungsfeld)
 * Andere User = 1 Feld (Mitschrift mit Lock)
 *
 * Lock-Regel: Nur EINE Person kann gleichzeitig schreiben
 */

(function() {
    'use strict';

    // Konfiguration
    const AUTO_LOAD_INTERVAL = 2000; // Status alle 2 Sekunden prüfen
    const LOCK_REFRESH_INTERVAL = 5000; // Lock alle 5 Sekunden refreshen
    const SAVE_DELAY = 1500; // Nach 1.5s Pause speichern
    const ACTIVE_TOP_CHECK_INTERVAL = 3000; // Aktiven TOP alle 3 Sekunden prüfen

    // State für jedes Textarea
    const textareaStates = new Map();

    // Aktueller aktiver TOP (für Change-Detection)
    let currentActiveTopId = null;

    /**
     * Initialisiert kollaborative Mitschrift
     */
    function initCollaborativeProtocol() {
        const mainFields = document.querySelectorAll('.collab-protocol-main');
        const appendFields = document.querySelectorAll('.collab-protocol-append');

        if (mainFields.length === 0 && appendFields.length === 0) {
            return;
        }

        // Hauptfelder initialisieren (für alle User)
        mainFields.forEach(textarea => {
            const itemId = parseInt(textarea.dataset.itemId);
            const isSecretary = textarea.dataset.isSecretary === '1';

            if (!itemId) {
                console.error('Keine item_id gefunden', textarea);
                return;
            }

            const key = `main_${itemId}`;

            // Prüfen ob bereits initialisiert
            if (textareaStates.has(key)) {
                const existingState = textareaStates.get(key);
                if (existingState.textarea === textarea) {
                    return; // Bereits initialisiert
                }
                cleanupField(itemId, 'main');
            }

            const state = {
                itemId: itemId,
                textarea: textarea,
                isSecretary: isSecretary,
                fieldType: 'main',
                lastSavedContent: textarea.value,
                currentHash: md5(textarea.value),
                hasLock: false,
                isTyping: false,
                typingTimeout: null,
                saveTimeout: null,
                autoLoadInterval: null,
                lockRefreshInterval: null
            };

            textareaStates.set(`main_${itemId}`, state);

            // Event Listener
            textarea.addEventListener('input', () => handleMainInput(state));
            textarea.addEventListener('blur', () => handleMainBlur(state));

            // Auto-Load starten (alle User laden Status)
            state.autoLoadInterval = setInterval(() => autoLoadMain(state), AUTO_LOAD_INTERVAL);

            // Initial load
            autoLoadMain(state);
        });

        // Fortsetzungsfelder initialisieren (nur Protokollführung)
        appendFields.forEach(textarea => {
            const itemId = parseInt(textarea.dataset.itemId);

            if (!itemId) {
                console.error('Keine item_id gefunden', textarea);
                return;
            }

            const key = `append_${itemId}`;

            if (textareaStates.has(key)) {
                const existingState = textareaStates.get(key);
                if (existingState.textarea === textarea) {
                    return;
                }
                cleanupField(itemId, 'append');
            }

            const state = {
                itemId: itemId,
                textarea: textarea,
                fieldType: 'append',
                lastSavedContent: textarea.value
            };

            textareaStates.set(`append_${itemId}`, state);
        });

        // Anhängen-Buttons initialisieren (nur Protokollführung)
        const appendButtons = document.querySelectorAll('.append-button');
        appendButtons.forEach(button => {
            const itemId = parseInt(button.dataset.itemId);
            if (!itemId) return;

            button.addEventListener('click', () => handleAppendButtonClick(itemId));
        });
    }

    /**
     * HAUPTFELD: Input-Event
     */
    async function handleMainInput(state) {
        state.isTyping = true;

        // Beim ersten Buchstaben: Lock anfordern
        if (!state.hasLock) {
            console.log('[LOCK] Fordere Lock an für item', state.itemId);
            const lockResult = await acquireLock(state.itemId);
            console.log('[LOCK] Lock-Result:', lockResult);

            if (!lockResult.success || !lockResult.own_lock) {
                // Lock fehlgeschlagen → Textarea disabled
                console.log('[LOCK] Lock fehlgeschlagen - disabled textarea');
                state.textarea.disabled = true;
                showLockWarning(state.itemId, lockResult.locked_by_name);
                return;
            }
            state.hasLock = true;
            console.log('[LOCK] Lock erfolgreich erhalten');

            // Lock-Refresh starten
            state.lockRefreshInterval = setInterval(() => {
                refreshLock(state.itemId);
            }, LOCK_REFRESH_INTERVAL);
        }

        // Typing-Timeout zurücksetzen
        if (state.typingTimeout) {
            clearTimeout(state.typingTimeout);
        }

        state.typingTimeout = setTimeout(() => {
            state.isTyping = false;
        }, 1000);

        // Save-Timeout zurücksetzen
        if (state.saveTimeout) {
            clearTimeout(state.saveTimeout);
        }

        state.saveTimeout = setTimeout(() => {
            console.log('[SAVE] Timeout abgelaufen - rufe saveContent auf');
            saveContent(state);
        }, SAVE_DELAY);

        updateStatus(state.itemId, 'editing', '✏️ Schreibe...');
    }

    /**
     * HAUPTFELD: Blur-Event
     */
    async function handleMainBlur(state) {
        console.log('[BLUR] Blur-Event, hasLock:', state.hasLock);
        state.isTyping = false;

        // Sofort speichern
        if (state.saveTimeout) {
            clearTimeout(state.saveTimeout);
        }

        if (state.hasLock) {
            console.log('[BLUR] Speichere und gebe Lock frei');
            await saveContent(state);
            await releaseLock(state);
            console.log('[BLUR] Lock freigegeben, hasLock:', state.hasLock);
        }
    }

    /**
     * FORTSETZUNGSFELD: Button-Click Handler
     */
    function handleAppendButtonClick(itemId) {
        const state = textareaStates.get(`append_${itemId}`);
        if (!state) {
            console.error('Append-State nicht gefunden für item_id:', itemId);
            return;
        }

        saveAppendField(state);
    }

    /**
     * Speichert Hauptfeld
     */
    async function saveContent(state) {
        console.log('[SAVE] saveContent aufgerufen, hasLock:', state.hasLock);
        const currentContent = state.textarea.value;

        // Nur speichern wenn geändert
        if (currentContent === state.lastSavedContent) {
            console.log('[SAVE] Kein Save - Content unverändert');
            return;
        }

        if (!state.hasLock) {
            console.error('[SAVE] Cannot save without lock');
            updateStatus(state.itemId, 'error', '❌ Kein Lock');
            return;
        }

        try {
            console.log('[SAVE] Speichere Content für item', state.itemId);
            updateStatus(state.itemId, 'saving', '💾 Speichere...');

            const response = await fetch('api/protocol_save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: state.itemId,
                    content: currentContent
                })
            });

            console.log('[SAVE] Response status:', response.status);
            const data = await response.json();
            console.log('[SAVE] Response data:', data);

            if (data.success) {
                state.lastSavedContent = currentContent;

                const now = new Date();
                const timeStr = now.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'});

                updateStatus(state.itemId, 'saved', `✅ Gespeichert`);
                updateLastSaved(state.itemId, `Gespeichert: ${timeStr}`);

                // Status nach 2 Sekunden zurücksetzen
                setTimeout(() => {
                    updateStatus(state.itemId, 'idle', '');
                }, 2000);
            } else {
                console.error('[SAVE] Save fehlgeschlagen:', data.error);
                updateStatus(state.itemId, 'error', '❌ Fehler: ' + (data.error || 'Unbekannt'));
            }
        } catch (error) {
            console.error('[SAVE] Exception:', error);
            updateStatus(state.itemId, 'error', '❌ Netzwerkfehler');
        }
    }

    /**
     * Speichert Fortsetzungsfeld (priorisiert)
     */
    async function saveAppendField(state) {
        const currentContent = state.textarea.value.trim();

        if (!currentContent) {
            updateAppendStatus(state.itemId, 'Feld ist leer');
            setTimeout(() => {
                updateAppendStatus(state.itemId, '');
            }, 2000);
            return;
        }

        try {
            updateAppendStatus(state.itemId, '💾 Übertrage...');

            const response = await fetch('api/protocol_secretary_append.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: state.itemId,
                    append_text: currentContent
                })
            });

            if (!response.ok) {
                const errorText = await response.text();
                console.error('Append HTTP Error:', response.status, errorText);
                throw new Error(`HTTP ${response.status}: ${errorText}`);
            }

            const data = await response.json();

            if (data.success && data.appended) {
                state.textarea.value = '';
                state.lastSavedContent = '';

                updateAppendStatus(state.itemId, '✅ Übertragen und angehängt');

                setTimeout(() => {
                    updateAppendStatus(state.itemId, '');
                }, 2000);

                // Hauptfeld neu laden
                const mainState = textareaStates.get(`main_${state.itemId}`);
                if (mainState) {
                    forceLoadMain(mainState);
                }
            } else {
                updateAppendStatus(state.itemId, '❌ Fehler');
                console.error('Append Fehler:', data);
            }
        } catch (error) {
            updateAppendStatus(state.itemId, '❌ Netzwerkfehler');
            console.error('Append Exception:', error);
        }
    }

    /**
     * Lädt aktuellen Stand
     */
    async function autoLoadMain(state) {
        // Nicht laden wenn User gerade tippt
        if (state.isTyping) {
            return;
        }

        await loadMainContent(state, false);
    }

    /**
     * Force-Reload (ignoriert isTyping)
     */
    async function forceLoadMain(state) {
        await loadMainContent(state, true);
    }

    /**
     * Lädt Hauptfeld-Content
     */
    async function loadMainContent(state, forceReload) {
        try {
            const response = await fetch(`api/protocol_get_updates.php?item_id=${state.itemId}`);
            const data = await response.json();

            if (!data.success) {
                console.error('Load Fehler:', data.error);
                return;
            }

            // Lock-Status prüfen
            if (data.is_locked && !data.locked_by_me) {
                // Jemand anderes hat Lock → Textarea disabled
                state.textarea.disabled = true;
                showLockWarning(state.itemId, data.locked_by_name);
                state.hasLock = false;

                // Lock-Refresh stoppen
                if (state.lockRefreshInterval) {
                    clearInterval(state.lockRefreshInterval);
                    state.lockRefreshInterval = null;
                }
            } else if (!data.is_locked) {
                // Kein Lock → Textarea enabled
                state.textarea.disabled = false;
                hideLockWarning(state.itemId);
                state.hasLock = false;
            }

            // Content nur aktualisieren wenn Hash unterschiedlich
            if (data.content_hash !== state.currentHash && data.content !== state.textarea.value) {
                const cursorPos = state.textarea.selectionStart;
                const hasFocus = document.activeElement === state.textarea;

                state.textarea.value = data.content;
                state.lastSavedContent = data.content;
                state.currentHash = data.content_hash;

                // Cursor nur wiederherstellen wenn fokussiert UND kein Force-Reload
                if (hasFocus && !forceReload) {
                    try {
                        state.textarea.setSelectionRange(cursorPos, cursorPos);
                    } catch (e) {}
                }

                // Visuelles Feedback
                state.textarea.style.borderColor = forceReload ? '#2196f3' : '#4caf50';
                setTimeout(() => {
                    state.textarea.style.borderColor = '';
                }, 300);
            }
        } catch (error) {
            console.error('Load Exception:', error);
        }
    }

    /**
     * Lock anfordern
     */
    async function acquireLock(itemId) {
        try {
            console.log('[LOCK] Sende Lock-Request für item', itemId);
            const response = await fetch('api/protocol_acquire_lock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: itemId
                })
            });

            console.log('[LOCK] Response erhalten, status:', response.status);
            const data = await response.json();
            console.log('[LOCK] JSON geparst:', data);
            return data;
        } catch (error) {
            console.error('[LOCK] acquire error:', error);
            return { success: false, error: 'Network error', locked_by_name: 'Fehler beim Lock-Abruf' };
        }
    }

    /**
     * Lock refreshen (verlängern)
     */
    async function refreshLock(itemId) {
        // Einfach nochmal acquire aufrufen
        await acquireLock(itemId);
    }

    /**
     * Lock freigeben
     */
    async function releaseLock(state) {
        if (!state.hasLock) {
            console.log('[UNLOCK] Kein Lock zum Freigeben');
            return;
        }

        try {
            console.log('[UNLOCK] Sende Release-Request für item', state.itemId);
            const response = await fetch('api/protocol_release_lock.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: state.itemId
                })
            });

            console.log('[UNLOCK] Response erhalten, status:', response.status);
            const data = await response.json();
            console.log('[UNLOCK] Response data:', data);

            if (data.success) {
                state.hasLock = false;
                console.log('[UNLOCK] Lock erfolgreich freigegeben');
            } else {
                console.error('[UNLOCK] Lock-Release fehlgeschlagen:', data);
            }

            // Lock-Refresh stoppen
            if (state.lockRefreshInterval) {
                clearInterval(state.lockRefreshInterval);
                state.lockRefreshInterval = null;
            }
        } catch (error) {
            console.error('Lock release error:', error);
        }
    }

    /**
     * Zeigt Lock-Warnung
     */
    function showLockWarning(itemId, lockedByName) {
        const warningId = `lock-warning-${itemId}`;
        let warningEl = document.getElementById(warningId);

        if (!warningEl) {
            const textarea = document.querySelector(`.collab-protocol-main[data-item-id="${itemId}"]`);
            if (!textarea) return;

            warningEl = document.createElement('div');
            warningEl.id = warningId;
            warningEl.style.cssText = `
                padding: 12px 15px;
                margin-bottom: 10px;
                background: #ffebee;
                border: 2px solid #f44336;
                border-radius: 4px;
                color: #c62828;
                font-weight: bold;
            `;

            textarea.parentNode.insertBefore(warningEl, textarea);
        }

        warningEl.innerHTML = `🔒 <strong>${lockedByName}</strong> schreibt gerade - keine Eingabe möglich`;
        warningEl.style.display = 'block';
    }

    /**
     * Versteckt Lock-Warnung
     */
    function hideLockWarning(itemId) {
        const warningEl = document.getElementById(`lock-warning-${itemId}`);
        if (warningEl) {
            warningEl.style.display = 'none';
        }
    }

    /**
     * Status-Anzeige aktualisieren
     */
    function updateStatus(itemId, status, text) {
        const statusEl = document.getElementById(`collab-status-${itemId}`);
        if (!statusEl) return;

        statusEl.textContent = text;

        const colors = {
            'editing': '#ff9800',
            'saving': '#2196f3',
            'saved': '#4caf50',
            'error': '#f44336'
        };

        statusEl.style.color = colors[status] || '#666';
    }

    /**
     * "Zuletzt gespeichert" Anzeige aktualisieren
     */
    function updateLastSaved(itemId, text) {
        const el = document.getElementById(`collab-last-saved-${itemId}`);
        if (el) {
            el.textContent = text;
        }
    }

    /**
     * Fortsetzungsfeld-Status aktualisieren
     */
    function updateAppendStatus(itemId, text) {
        const el = document.getElementById(`append-status-${itemId}`);
        if (el) {
            el.textContent = text;
            el.style.color = text.includes('✅') ? '#4caf50' : (text.includes('❌') ? '#f44336' : '#ff9800');
        }
    }

    /**
     * MD5-Hash-Funktion
     */
    function md5(string) {
        function md5cycle(x, k) {
            var a = x[0], b = x[1], c = x[2], d = x[3];
            a = ff(a, b, c, d, k[0], 7, -680876936);
            d = ff(d, a, b, c, k[1], 12, -389564586);
            c = ff(c, d, a, b, k[2], 17, 606105819);
            b = ff(b, c, d, a, k[3], 22, -1044525330);
            a = ff(a, b, c, d, k[4], 7, -176418897);
            d = ff(d, a, b, c, k[5], 12, 1200080426);
            c = ff(c, d, a, b, k[6], 17, -1473231341);
            b = ff(b, c, d, a, k[7], 22, -45705983);
            a = ff(a, b, c, d, k[8], 7, 1770035416);
            d = ff(d, a, b, c, k[9], 12, -1958414417);
            c = ff(c, d, a, b, k[10], 17, -42063);
            b = ff(b, c, d, a, k[11], 22, -1990404162);
            a = ff(a, b, c, d, k[12], 7, 1804603682);
            d = ff(d, a, b, c, k[13], 12, -40341101);
            c = ff(c, d, a, b, k[14], 17, -1502002290);
            b = ff(b, c, d, a, k[15], 22, 1236535329);
            a = gg(a, b, c, d, k[1], 5, -165796510);
            d = gg(d, a, b, c, k[6], 9, -1069501632);
            c = gg(c, d, a, b, k[11], 14, 643717713);
            b = gg(b, c, d, a, k[0], 20, -373897302);
            a = gg(a, b, c, d, k[5], 5, -701558691);
            d = gg(d, a, b, c, k[10], 9, 38016083);
            c = gg(c, d, a, b, k[15], 14, -660478335);
            b = gg(b, c, d, a, k[4], 20, -405537848);
            a = gg(a, b, c, d, k[9], 5, 568446438);
            d = gg(d, a, b, c, k[14], 9, -1019803690);
            c = gg(c, d, a, b, k[3], 14, -187363961);
            b = gg(b, c, d, a, k[8], 20, 1163531501);
            a = gg(a, b, c, d, k[13], 5, -1444681467);
            d = gg(d, a, b, c, k[2], 9, -51403784);
            c = gg(c, d, a, b, k[7], 14, 1735328473);
            b = gg(b, c, d, a, k[12], 20, -1926607734);
            a = hh(a, b, c, d, k[5], 4, -378558);
            d = hh(d, a, b, c, k[8], 11, -2022574463);
            c = hh(c, d, a, b, k[11], 16, 1839030562);
            b = hh(b, c, d, a, k[14], 23, -35309556);
            a = hh(a, b, c, d, k[1], 4, -1530992060);
            d = hh(d, a, b, c, k[4], 11, 1272893353);
            c = hh(c, d, a, b, k[7], 16, -155497632);
            b = hh(b, c, d, a, k[10], 23, -1094730640);
            a = hh(a, b, c, d, k[13], 4, 681279174);
            d = hh(d, a, b, c, k[0], 11, -358537222);
            c = hh(c, d, a, b, k[3], 16, -722521979);
            b = hh(b, c, d, a, k[6], 23, 76029189);
            a = hh(a, b, c, d, k[9], 4, -640364487);
            d = hh(d, a, b, c, k[12], 11, -421815835);
            c = hh(c, d, a, b, k[15], 16, 530742520);
            b = hh(b, c, d, a, k[2], 23, -995338651);
            a = ii(a, b, c, d, k[0], 6, -198630844);
            d = ii(d, a, b, c, k[7], 10, 1126891415);
            c = ii(c, d, a, b, k[14], 15, -1416354905);
            b = ii(b, c, d, a, k[5], 21, -57434055);
            a = ii(a, b, c, d, k[12], 6, 1700485571);
            d = ii(d, a, b, c, k[3], 10, -1894986606);
            c = ii(c, d, a, b, k[10], 15, -1051523);
            b = ii(b, c, d, a, k[1], 21, -2054922799);
            a = ii(a, b, c, d, k[8], 6, 1873313359);
            d = ii(d, a, b, c, k[15], 10, -30611744);
            c = ii(c, d, a, b, k[6], 15, -1560198380);
            b = ii(b, c, d, a, k[13], 21, 1309151649);
            a = ii(a, b, c, d, k[4], 6, -145523070);
            d = ii(d, a, b, c, k[11], 10, -1120210379);
            c = ii(c, d, a, b, k[2], 15, 718787259);
            b = ii(b, c, d, a, k[9], 21, -343485551);
            x[0] = add32(a, x[0]);
            x[1] = add32(b, x[1]);
            x[2] = add32(c, x[2]);
            x[3] = add32(d, x[3]);
        }

        function cmn(q, a, b, x, s, t) {
            a = add32(add32(a, q), add32(x, t));
            return add32((a << s) | (a >>> (32 - s)), b);
        }

        function ff(a, b, c, d, x, s, t) {
            return cmn((b & c) | ((~b) & d), a, b, x, s, t);
        }

        function gg(a, b, c, d, x, s, t) {
            return cmn((b & d) | (c & (~d)), a, b, x, s, t);
        }

        function hh(a, b, c, d, x, s, t) {
            return cmn(b ^ c ^ d, a, b, x, s, t);
        }

        function ii(a, b, c, d, x, s, t) {
            return cmn(c ^ (b | (~d)), a, b, x, s, t);
        }

        function md51(s) {
            var n = s.length,
                state = [1732584193, -271733879, -1732584194, 271733878], i;
            for (i = 64; i <= s.length; i += 64) {
                md5cycle(state, md5blk(s.substring(i - 64, i)));
            }
            s = s.substring(i - 64);
            var tail = [0,0,0,0, 0,0,0,0, 0,0,0,0, 0,0,0,0];
            for (i = 0; i < s.length; i++)
                tail[i>>2] |= s.charCodeAt(i) << ((i%4) << 3);
            tail[i>>2] |= 0x80 << ((i%4) << 3);
            if (i > 55) {
                md5cycle(state, tail);
                for (i = 0; i < 16; i++) tail[i] = 0;
            }
            tail[14] = n*8;
            md5cycle(state, tail);
            return state;
        }

        function md5blk(s) {
            var md5blks = [], i;
            for (i = 0; i < 64; i += 4) {
                md5blks[i>>2] = s.charCodeAt(i)
                    + (s.charCodeAt(i+1) << 8)
                    + (s.charCodeAt(i+2) << 16)
                    + (s.charCodeAt(i+3) << 24);
            }
            return md5blks;
        }

        var hex_chr = '0123456789abcdef'.split('');

        function rhex(n) {
            var s='', j=0;
            for(; j<4; j++)
                s += hex_chr[(n >> (j * 8 + 4)) & 0x0F]
                    + hex_chr[(n >> (j * 8)) & 0x0F];
            return s;
        }

        function hex(x) {
            for (var i=0; i<x.length; i++)
                x[i] = rhex(x[i]);
            return x.join('');
        }

        function add32(a, b) {
            return (a + b) & 0xFFFFFFFF;
        }

        if (string === '') {
            return 'd41d8cd98f00b204e9800998ecf8427e';
        }

        return hex(md51(string));
    }

    /**
     * Cleanup beim Verlassen der Seite
     */
    function cleanup() {
        textareaStates.forEach((state, key) => {
            if (state.autoLoadInterval) clearInterval(state.autoLoadInterval);
            if (state.lockRefreshInterval) clearInterval(state.lockRefreshInterval);
            if (state.typingTimeout) clearTimeout(state.typingTimeout);
            if (state.saveTimeout) clearTimeout(state.saveTimeout);

            // Lock freigeben
            if (state.hasLock) {
                releaseLock(state);
            }
        });
    }

    /**
     * Cleanup für einzelnes Feld
     */
    function cleanupField(itemId, fieldType) {
        const key = `${fieldType}_${itemId}`;
        const state = textareaStates.get(key);
        if (state) {
            if (state.autoLoadInterval) clearInterval(state.autoLoadInterval);
            if (state.lockRefreshInterval) clearInterval(state.lockRefreshInterval);
            if (state.typingTimeout) clearTimeout(state.typingTimeout);
            if (state.saveTimeout) clearTimeout(state.saveTimeout);

            if (state.hasLock) {
                releaseLock(state);
            }

            textareaStates.delete(key);
        }
    }

    /**
     * Prüft ob sich der aktive TOP geändert hat
     * Wenn ja → Seite neu laden
     */
    async function checkActiveTopChange() {
        // Meeting ID aus URL extrahieren
        const urlParams = new URLSearchParams(window.location.search);
        const meetingId = urlParams.get('meeting_id');

        if (!meetingId) {
            return; // Keine Meeting ID → nicht in aktiver Sitzung
        }

        try {
            const response = await fetch(`api/get_active_top.php?meeting_id=${meetingId}`);
            const data = await response.json();

            if (!data.success) {
                return;
            }

            const activeTopId = data.active_item_id;

            // Beim ersten Aufruf: Initialisieren
            if (currentActiveTopId === null) {
                currentActiveTopId = activeTopId;
                console.log('[TOP-MONITOR] Initialer aktiver TOP:', activeTopId);
                return;
            }

            // TOP hat sich geändert → Seite neu laden
            if (currentActiveTopId !== activeTopId) {
                console.log('[TOP-MONITOR] TOP hat sich geändert:', currentActiveTopId, '→', activeTopId);
                console.log('[TOP-MONITOR] Lade Seite neu...');

                // Seite neu laden (mit Cache-Buster)
                const newUrl = `?tab=agenda&meeting_id=${meetingId}&_reload=${Date.now()}${activeTopId ? '#top-' + activeTopId : ''}`;
                window.location.replace(newUrl);
            }

        } catch (error) {
            console.error('[TOP-MONITOR] Fehler beim Prüfen des aktiven TOPs:', error);
        }
    }

    // Beim Seitenladen initialisieren
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initCollaborativeProtocol();
            checkActiveTopChange(); // Initial check
            setInterval(checkActiveTopChange, ACTIVE_TOP_CHECK_INTERVAL); // Regelmäßig prüfen
        });
    } else {
        initCollaborativeProtocol();
        checkActiveTopChange(); // Initial check
        setInterval(checkActiveTopChange, ACTIVE_TOP_CHECK_INTERVAL); // Regelmäßig prüfen
    }

    // Bei TOP-Wechsel
    window.addEventListener('hashchange', () => {
        setTimeout(initCollaborativeProtocol, 100);
    });

    // Cleanup beim Verlassen
    window.addEventListener('beforeunload', cleanup);

    // Global verfügbar machen für Debug
    window.collabProtocolLock = {
        textareaStates: textareaStates,
        reinit: initCollaborativeProtocol
    };

})();
