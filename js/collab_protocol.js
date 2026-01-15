/**
 * collab_protocol.js - Kollaboratives Protokoll Auto-Sync
 * Ermöglicht mehreren Teilnehmern gleichzeitig ins Protokoll zu schreiben
 * Version: 2.1 - MD5 Hash Fix
 */

(function() {
    'use strict';

    console.log('📋 Kollaboratives Protokoll v2.1 geladen (MD5 Hash Fix - KRITISCH)');

    // Konfiguration
    const AUTO_SAVE_INTERVAL = 2000; // Auto-Save alle 2 Sekunden
    const AUTO_LOAD_INTERVAL = 2000; // Updates laden alle 2 Sekunden
    const TYPING_TIMEOUT = 1000; // Nach 1s ohne Tippen als "nicht am Tippen" gelten
    const EDITOR_DISPLAY_TIMEOUT = 10; // "Schreibt gerade" nach 10 Sekunden ausblenden

    // State für jedes Textarea
    const textareaStates = new Map();

    /**
     * Initialisiert kollaboratives Protokoll für alle Textareas
     */
    function initCollaborativeProtocol() {
        const textareas = document.querySelectorAll('.collab-protocol-textarea');

        if (textareas.length === 0) {
            console.log('Kein kollaboratives Protokoll aktiv');
            return;
        }

        console.log(`Initialisiere ${textareas.length} kollaborative Protokoll-Felder`);

        textareas.forEach(textarea => {
            const itemId = parseInt(textarea.dataset.itemId);

            if (!itemId) {
                console.error('Keine item_id gefunden für Textarea', textarea);
                return;
            }

            // State initialisieren
            const state = {
                itemId: itemId,
                textarea: textarea,
                lastSavedContent: textarea.value,
                currentHash: md5(textarea.value),
                isTyping: false,
                typingTimeout: null,
                autoSaveInterval: null,
                autoLoadInterval: null,
                cursorPosition: 0,
                lastForceUpdateAt: null // Tracking für Force-Updates
            };

            textareaStates.set(itemId, state);

            // Event Listener
            textarea.addEventListener('input', () => handleInput(state));
            textarea.addEventListener('focus', () => handleFocus(state));
            textarea.addEventListener('blur', () => handleBlur(state));

            // Auto-Save starten
            state.autoSaveInterval = setInterval(() => autoSave(state), AUTO_SAVE_INTERVAL);

            // Auto-Load starten
            state.autoLoadInterval = setInterval(() => autoLoad(state), AUTO_LOAD_INTERVAL);

            // Initial load
            autoLoad(state);
        });

        console.log('Kollaboratives Protokoll initialisiert');
    }

    /**
     * Behandelt Input-Event (User tippt)
     */
    function handleInput(state) {
        state.isTyping = true;
        state.cursorPosition = state.textarea.selectionStart;

        // Typing-Timeout zurücksetzen
        if (state.typingTimeout) {
            clearTimeout(state.typingTimeout);
        }

        state.typingTimeout = setTimeout(() => {
            state.isTyping = false;
        }, TYPING_TIMEOUT);

        // Status aktualisieren
        updateStatus(state.itemId, 'editing', '✏️ Schreibe...');
    }

    /**
     * Behandelt Focus-Event
     */
    function handleFocus(state) {
        state.isTyping = true;
    }

    /**
     * Behandelt Blur-Event
     */
    function handleBlur(state) {
        // Sofort speichern bei Blur
        autoSave(state);

        // "Schreibt gerade" sofort ausschalten
        state.isTyping = false;

        // Sofort signalisieren dass wir nicht mehr schreiben
        clearEditingStatus(state.itemId);
    }

    /**
     * Auto-Save: Speichert Änderungen wenn vorhanden
     */
    async function autoSave(state) {
        const currentContent = state.textarea.value;

        // Nur speichern wenn sich was geändert hat
        if (currentContent === state.lastSavedContent) {
            return;
        }

        try {
            updateStatus(state.itemId, 'saving', '💾 Speichere...');

            const response = await fetch('api/protocol_autosave.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: state.itemId,
                    content: currentContent,
                    cursor_pos: state.cursorPosition,
                    client_hash: state.currentHash,
                    is_typing: state.isTyping // Nur bei aktivem Tippen Editing-Status aktualisieren
                })
            });

            const data = await response.json();

            if (data.success) {
                state.lastSavedContent = currentContent;
                state.currentHash = data.new_hash;

                const now = new Date();
                const timeStr = now.toLocaleTimeString('de-DE', {hour: '2-digit', minute: '2-digit'});

                updateStatus(state.itemId, 'saved', '✓ Gespeichert');
                updateLastSaved(state.itemId, `Zuletzt gespeichert: ${timeStr}`);

                // Debug: Warnung wenn Server einen Konflikt erkannt hat (aber nicht blockiert)
                if (data.has_conflict) {
                    console.log(`ℹ️ Server meldete Konflikt (ignoriert), Hash synchronisiert: ${data.new_hash}`);
                }

                // WICHTIG: Kein Konflikt-Warning mehr nach erfolgreichem Save
                // Der Hash ist jetzt synchronisiert - has_conflict wird ignoriert
            } else {
                updateStatus(state.itemId, 'error', '❌ Fehler');
                console.error('Auto-Save Fehler:', data.error);
            }
        } catch (error) {
            updateStatus(state.itemId, 'error', '❌ Netzwerkfehler');
            console.error('Auto-Save Exception:', error);
        }
    }

    /**
     * Wendet Content-Update auf Textarea an (mit Cursor-Preservation)
     */
    function applyContentUpdate(state, newContent, newHash) {
        // Cursor-Position merken
        const cursorPos = state.textarea.selectionStart;
        const hasFocus = document.activeElement === state.textarea;

        // Content aktualisieren
        state.textarea.value = newContent;
        state.lastSavedContent = newContent;
        state.currentHash = newHash;

        // Cursor-Position wiederherstellen (nur wenn Feld fokussiert war)
        if (hasFocus) {
            try {
                state.textarea.setSelectionRange(cursorPos, cursorPos);
            } catch (e) {
                // Ignorieren wenn Position ungültig
            }
        }

        // Visuelles Feedback
        state.textarea.style.borderColor = '#4caf50';
        setTimeout(() => {
            state.textarea.style.borderColor = '';
        }, 300);
    }

    /**
     * Auto-Load: Lädt Updates von anderen wenn User nicht tippt
     * AUSNAHME: Force-Updates werden immer geladen (mit Warnung)
     */
    async function autoLoad(state) {
        try {
            const response = await fetch(`api/protocol_get_updates.php?item_id=${state.itemId}`);
            const data = await response.json();

            if (!data.success) {
                console.error('Auto-Load Fehler:', data.error);
                return;
            }

            // Editoren-Anzeige aktualisieren
            updateEditors(state.itemId, data.editors, data.editor_count);

            // Prüfen ob es ein neues Force-Update gibt
            const hasForceUpdate = data.force_update_at &&
                                   data.force_update_at !== state.lastForceUpdateAt;

            if (hasForceUpdate) {
                // Force-Update erkannt - laden auch wenn User tippt
                state.lastForceUpdateAt = data.force_update_at;

                if (state.isTyping) {
                    // User tippt gerade - Warnung anzeigen
                    if (confirm('⚠️ Ein anderer Teilnehmer hat den Prioritäts-Button verwendet.\n\n' +
                               'Deine aktuellen Änderungen werden überschrieben!\n\n' +
                               'OK = Überschreiben, Abbrechen = Deine Version behalten')) {
                        // User akzeptiert Überschreiben
                        applyContentUpdate(state, data.content, data.content_hash);
                        updateStatus(state.itemId, 'saved', '⚡ Force-Update geladen');
                    } else {
                        // User will seine Version behalten - dann sofort speichern
                        autoSave(state);
                    }
                } else {
                    // User tippt nicht - einfach laden
                    applyContentUpdate(state, data.content, data.content_hash);
                    updateStatus(state.itemId, 'saved', '⚡ Force-Update geladen');
                }
                return;
            }

            // Nicht laden wenn User gerade tippt (außer bei Force-Update oben)
            if (state.isTyping) {
                return;
            }

            // Content nur aktualisieren wenn:
            // 1. Hash unterschiedlich ist
            // 2. Content tatsächlich anders ist
            if (data.content_hash !== state.currentHash &&
                data.content !== state.textarea.value) {

                applyContentUpdate(state, data.content, data.content_hash);

            } else if (data.content_hash === state.currentHash && state.textarea.value !== data.content) {
                // Edge case: Hash gleich aber Content anders
                // Dann unseren Hash neu berechnen
                state.currentHash = data.content_hash;
                state.lastSavedContent = state.textarea.value;
            }
        } catch (error) {
            console.error('Auto-Load Exception:', error);
        }
    }

    /**
     * Status-Anzeige aktualisieren
     */
    function updateStatus(itemId, status, text) {
        const statusEl = document.getElementById(`collab-status-${itemId}`);
        if (!statusEl) return;

        statusEl.textContent = text;

        // Farbe basierend auf Status
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
     * Editoren-Anzeige aktualisieren (wer schreibt noch?)
     */
    function updateEditors(itemId, editors, count) {
        const el = document.getElementById(`collab-editors-${itemId}`);
        if (!el) return;

        if (count === 0) {
            el.textContent = '';
        } else if (count === 1) {
            el.textContent = `✍️ ${editors[0]} schreibt gerade...`;
            el.style.color = '#ff9800';
        } else {
            el.textContent = `✍️ ${count} Personen schreiben gerade: ${editors.join(', ')}`;
            el.style.color = '#ff9800';
        }
    }

    /**
     * Editing-Status sofort löschen (z.B. bei Blur)
     */
    async function clearEditingStatus(itemId) {
        try {
            await fetch('api/protocol_clear_editing.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: itemId
                })
            });
            // Kein Fehler-Handling nötig - best effort
        } catch (error) {
            // Ignorieren - nicht kritisch
            console.log('Clear editing status failed (ignored):', error);
        }
    }

    /**
     * MD5-Hash-Funktion (kompatibel mit PHP md5())
     * Quelle: https://github.com/blueimp/JavaScript-MD5
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
        textareaStates.forEach((state, itemId) => {
            // Letzte Änderungen speichern
            if (state.textarea.value !== state.lastSavedContent) {
                // Synchroner Save beim Verlassen (beste Effort)
                navigator.sendBeacon('api/protocol_autosave.php', JSON.stringify({
                    item_id: state.itemId,
                    content: state.textarea.value,
                    client_hash: state.currentHash
                }));
            }

            // Intervals stoppen
            if (state.autoSaveInterval) clearInterval(state.autoSaveInterval);
            if (state.autoLoadInterval) clearInterval(state.autoLoadInterval);
            if (state.typingTimeout) clearTimeout(state.typingTimeout);
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

    /**
     * Force-Save: "Meine Version hat Priorität"
     * Wird bei Konflikten verwendet um die eigene Version durchzusetzen
     */
    window.forceSaveProtocol = async function(itemId) {
        const state = textareaStates.get(itemId);
        if (!state) {
            alert('Fehler: Protokoll-Feld nicht gefunden');
            return;
        }

        // Bestätigung
        if (!confirm('⚠️ ACHTUNG!\n\nDies überschreibt ALLE anderen Änderungen mit deinem aktuellen Text.\n\nBist du sicher?')) {
            return;
        }

        try {
            updateStatus(itemId, 'saving', '💾 Speichere (forciert)...');

            const currentContent = state.textarea.value;

            const response = await fetch('api/protocol_autosave.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    item_id: itemId,
                    content: currentContent,
                    cursor_pos: state.textarea.selectionStart,
                    client_hash: state.currentHash,
                    force: true // Marker für Force-Save
                })
            });

            const data = await response.json();

            if (data.success) {
                state.lastSavedContent = currentContent;
                state.currentHash = data.new_hash;

                // Force-Update Timestamp aktualisieren
                if (data.force_update_at) {
                    state.lastForceUpdateAt = data.force_update_at;
                }

                updateStatus(itemId, 'saved', '✓ Forciert gespeichert');
                updateLastSaved(itemId, 'Deine Version hat Priorität');

                // Nach 3 Sekunden normalen Status wiederherstellen
                setTimeout(() => {
                    updateLastSaved(itemId, '');
                }, 3000);

                alert('✅ Deine Version wurde gespeichert und hat nun Priorität!\n\nAlle anderen Teilnehmer werden benachrichtigt.');
            } else {
                updateStatus(itemId, 'error', '❌ Fehler');
                alert('Fehler beim Speichern: ' + (data.error || 'Unbekannt'));
            }
        } catch (error) {
            updateStatus(itemId, 'error', '❌ Netzwerkfehler');
            alert('Netzwerkfehler beim Speichern: ' + error.message);
            console.error('Force-Save Exception:', error);
        }
    };

    // Global verfügbar machen für Debug
    window.collabProtocol = {
        textareaStates: textareaStates,
        reinit: initCollaborativeProtocol,
        forceSave: window.forceSaveProtocol
    };

})();
