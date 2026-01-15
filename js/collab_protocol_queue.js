/**
 * collab_protocol_queue.js - Master-Slave Pattern mit Queue
 * Version: 3.0 - Stabile Queue-basierte Architektur
 *
 * Protokollführung = Master (2 Felder)
 * Andere User = Slave (1 Feld, über Queue)
 */

(function() {
    'use strict';

    console.log('📋 Kollaboratives Protokoll v3.0 - Master-Slave mit Queue');

    // Konfiguration
    const AUTO_LOAD_INTERVAL = 2000; // Hauptsystem laden alle 2 Sekunden
    const QUEUE_PROCESS_INTERVAL = 2000; // Queue verarbeiten alle 2 Sekunden (nur Master)
    const APPEND_SAVE_DELAY = 2000; // Fortsetzungsfeld nach 2s Pause speichern
    const QUEUE_SAVE_DELAY = 1500; // Queue-Save nach 1.5s Pause

    // State für jedes Textarea
    const textareaStates = new Map();

    /**
     * Initialisiert kollaboratives Protokoll
     */
    function initCollaborativeProtocol() {
        const mainFields = document.querySelectorAll('.collab-protocol-main');
        const appendFields = document.querySelectorAll('.collab-protocol-append');

        if (mainFields.length === 0 && appendFields.length === 0) {
            console.log('Kein kollaboratives Protokoll aktiv');
            return;
        }

        console.log(`Initialisiere Hauptfelder: ${mainFields.length}, Fortsetzungsfelder: ${appendFields.length}`);

        // Hauptfelder initialisieren (für alle User)
        mainFields.forEach(textarea => {
            const itemId = parseInt(textarea.dataset.itemId);
            const isSecretary = textarea.dataset.isSecretary === '1';

            if (!itemId) {
                console.error('Keine item_id gefunden', textarea);
                return;
            }

            const state = {
                itemId: itemId,
                textarea: textarea,
                isSecretary: isSecretary,
                fieldType: 'main',
                lastSavedContent: textarea.value,
                currentHash: md5(textarea.value),
                isTyping: false,
                typingTimeout: null,
                saveTimeout: null,
                autoLoadInterval: null,
                queueProcessInterval: null
            };

            textareaStates.set(`main_${itemId}`, state);

            // Event Listener
            textarea.addEventListener('input', () => handleMainInput(state));
            textarea.addEventListener('blur', () => handleMainBlur(state));

            // Auto-Load starten (alle User laden Hauptsystem)
            state.autoLoadInterval = setInterval(() => autoLoadMain(state), AUTO_LOAD_INTERVAL);

            // Queue-Processing starten (nur Protokollführung)
            if (isSecretary) {
                state.queueProcessInterval = setInterval(() => processQueue(itemId), QUEUE_PROCESS_INTERVAL);
            }

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

            const state = {
                itemId: itemId,
                textarea: textarea,
                fieldType: 'append',
                lastSavedContent: textarea.value,
                isTyping: false,
                typingTimeout: null,
                saveTimeout: null
            };

            textareaStates.set(`append_${itemId}`, state);

            // Event Listener
            textarea.addEventListener('input', () => handleAppendInput(state));
            textarea.addEventListener('blur', () => handleAppendBlur(state));
        });

        console.log('Kollaboratives Protokoll initialisiert (Queue-System)');
    }

    /**
     * HAUPTFELD: Input-Event
     */
    function handleMainInput(state) {
        state.isTyping = true;

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
            saveToQueue(state);
        }, QUEUE_SAVE_DELAY);

        updateStatus(state.itemId, 'editing', '✏️ Schreibe...');
    }

    /**
     * HAUPTFELD: Blur-Event
     */
    function handleMainBlur(state) {
        state.isTyping = false;

        // Sofort in Queue speichern
        if (state.saveTimeout) {
            clearTimeout(state.saveTimeout);
        }
        saveToQueue(state);
    }

    /**
     * FORTSETZUNGSFELD: Input-Event (nur Protokollführung)
     */
    function handleAppendInput(state) {
        state.isTyping = true;

        // Save-Timeout zurücksetzen
        if (state.saveTimeout) {
            clearTimeout(state.saveTimeout);
        }

        state.saveTimeout = setTimeout(() => {
            saveAppendField(state);
        }, APPEND_SAVE_DELAY);

        updateAppendStatus(state.itemId, '✏️ Schreibe...');
    }

    /**
     * FORTSETZUNGSFELD: Blur-Event
     */
    function handleAppendBlur(state) {
        state.isTyping = false;

        // Sofort speichern
        if (state.saveTimeout) {
            clearTimeout(state.saveTimeout);
        }
        saveAppendField(state);
    }

    /**
     * Speichert Hauptfeld in Queue
     */
    async function saveToQueue(state) {
        const currentContent = state.textarea.value;

        // Nur speichern wenn geändert
        if (currentContent === state.lastSavedContent) {
            return;
        }

        try {
            updateStatus(state.itemId, 'saving', '💾 Speichere in Queue...');

            const response = await fetch('api/protocol_queue_save.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: state.itemId,
                    content: currentContent
                })
            });

            const data = await response.json();

            if (data.success) {
                state.lastSavedContent = currentContent;

                const now = new Date();
                const timeStr = now.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'});

                updateStatus(state.itemId, 'saved', `⏳ In Queue (Pos. ${data.queue_position})`);
                updateLastSaved(state.itemId, `Gespeichert: ${timeStr}`);

                console.log(`In Queue gespeichert - Position ${data.queue_position}/${data.queue_size}`);
            } else {
                updateStatus(state.itemId, 'error', '❌ Fehler');
                console.error('Queue-Save Fehler:', data.error);
            }
        } catch (error) {
            updateStatus(state.itemId, 'error', '❌ Netzwerkfehler');
            console.error('Queue-Save Exception:', error);
        }
    }

    /**
     * Speichert Fortsetzungsfeld (priorisiert)
     */
    async function saveAppendField(state) {
        const currentContent = state.textarea.value.trim();

        // Leer → nichts zu tun
        if (!currentContent) {
            updateAppendStatus(state.itemId, '');
            return;
        }

        // Nur speichern wenn geändert
        if (currentContent === state.lastSavedContent) {
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

            const data = await response.json();

            if (data.success && data.appended) {
                state.lastSavedContent = currentContent;

                // Feld leeren nach erfolgreicher Übertragung
                state.textarea.value = '';
                state.lastSavedContent = '';

                updateAppendStatus(state.itemId, '✅ Übertragen');

                // Status nach 2 Sekunden zurücksetzen
                setTimeout(() => {
                    updateAppendStatus(state.itemId, '');
                }, 2000);

                console.log(`Fortsetzungsfeld übertragen: ${data.appended_length} Zeichen`);

                // Hauptfeld aktualisieren
                const mainState = textareaStates.get(`main_${state.itemId}`);
                if (mainState) {
                    mainState.currentHash = data.new_hash;
                }
            } else {
                updateAppendStatus(state.itemId, '❌ Fehler');
                console.error('Append Fehler:', data.error);
            }
        } catch (error) {
            updateAppendStatus(state.itemId, '❌ Netzwerkfehler');
            console.error('Append Exception:', error);
        }
    }

    /**
     * Lädt aktuellen Stand vom Hauptsystem
     */
    async function autoLoadMain(state) {
        // Nicht laden wenn User gerade tippt
        if (state.isTyping) {
            return;
        }

        try {
            const response = await fetch(`api/protocol_get_updates.php?item_id=${state.itemId}`);
            const data = await response.json();

            if (!data.success) {
                console.error('Auto-Load Fehler:', data.error);
                return;
            }

            // Queue-Anzeige aktualisieren (nur für Protokollführung)
            if (data.is_secretary && data.queue_size > 0) {
                updateQueueDisplay(state.itemId, data.queue_size, data.queue_waiting);
            } else {
                updateQueueDisplay(state.itemId, 0, []);
            }

            // Content nur aktualisieren wenn Hash unterschiedlich
            if (data.content_hash !== state.currentHash && data.content !== state.textarea.value) {
                const cursorPos = state.textarea.selectionStart;
                const hasFocus = document.activeElement === state.textarea;

                state.textarea.value = data.content;
                state.lastSavedContent = data.content;
                state.currentHash = data.content_hash;

                // Cursor nur wiederherstellen wenn fokussiert
                if (hasFocus) {
                    try {
                        state.textarea.setSelectionRange(cursorPos, cursorPos);
                    } catch (e) {}
                }

                // Visuelles Feedback
                state.textarea.style.borderColor = '#4caf50';
                setTimeout(() => {
                    state.textarea.style.borderColor = '';
                }, 300);
            }
        } catch (error) {
            console.error('Auto-Load Exception:', error);
        }
    }

    /**
     * Verarbeitet Queue (nur Protokollführung)
     */
    async function processQueue(itemId) {
        try {
            const response = await fetch('api/protocol_process_queue.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: itemId
                })
            });

            const data = await response.json();

            if (data.success && data.processed > 0) {
                console.log(`Queue verarbeitet: ${data.processed} Einträge`);

                // Hauptfeld neu laden
                const mainState = textareaStates.get(`main_${itemId}`);
                if (mainState && !mainState.isTyping) {
                    autoLoadMain(mainState);
                }
            }
        } catch (error) {
            console.error('Queue-Processing Exception:', error);
        }
    }

    /**
     * Aktualisiert Queue-Anzeige
     */
    function updateQueueDisplay(itemId, queueSize, queueWaiting) {
        const el = document.getElementById(`queue-display-${itemId}`);
        if (!el) return;

        if (queueSize === 0) {
            el.textContent = '';
            el.style.display = 'none';
        } else {
            const names = queueWaiting.map(w => w.name).join(', ');
            el.textContent = `📥 Queue: ${queueSize} ${queueSize === 1 ? 'Eintrag' : 'Einträge'}${names ? ` (${names})` : ''}`;
            el.style.display = 'block';
            el.style.color = '#ff9800';
            el.style.fontWeight = 'bold';
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
     * MD5-Hash-Funktion (kompatibel mit PHP md5())
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
            // Intervals stoppen
            if (state.autoLoadInterval) clearInterval(state.autoLoadInterval);
            if (state.queueProcessInterval) clearInterval(state.queueProcessInterval);
            if (state.typingTimeout) clearTimeout(state.typingTimeout);
            if (state.saveTimeout) clearTimeout(state.saveTimeout);
        });
    }

    // Beim Seitenladen initialisieren
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCollaborativeProtocol);
    } else {
        initCollaborativeProtocol();
    }

    // Cleanup beim Verlassen
    window.addEventListener('beforeunload', cleanup);

    // Global verfügbar machen für Debug
    window.collabProtocolQueue = {
        textareaStates: textareaStates,
        reinit: initCollaborativeProtocol,
        processQueue: processQueue
    };

})();
