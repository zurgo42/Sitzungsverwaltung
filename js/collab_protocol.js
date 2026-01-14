/**
 * collab_protocol.js - Kollaboratives Protokoll Auto-Sync
 * Ermöglicht mehreren Teilnehmern gleichzeitig ins Protokoll zu schreiben
 */

(function() {
    'use strict';

    // Konfiguration
    const AUTO_SAVE_INTERVAL = 2000; // Auto-Save alle 2 Sekunden
    const AUTO_LOAD_INTERVAL = 2000; // Updates laden alle 2 Sekunden
    const TYPING_TIMEOUT = 1500; // Nach 1,5s ohne Tippen als "nicht am Tippen" gelten

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
                cursorPosition: 0
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
                    client_hash: state.currentHash
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

                // Konflikt-Warnung wenn nötig
                if (data.has_conflict) {
                    showConflictWarning(state.itemId);
                }
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
     * Auto-Load: Lädt Updates von anderen wenn User nicht tippt
     */
    async function autoLoad(state) {
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

            // Editoren-Anzeige aktualisieren
            updateEditors(state.itemId, data.editors, data.editor_count);

            // Content nur aktualisieren wenn:
            // 1. Hash unterschiedlich ist
            // 2. User nicht am Tippen ist
            // 3. Content tatsächlich anders ist
            if (data.content_hash !== state.currentHash &&
                !state.isTyping &&
                data.content !== state.textarea.value) {

                // Cursor-Position merken
                const cursorPos = state.textarea.selectionStart;

                // Content aktualisieren
                state.textarea.value = data.content;
                state.lastSavedContent = data.content;
                state.currentHash = data.content_hash;

                // Cursor-Position wiederherstellen (ungefähr)
                try {
                    state.textarea.setSelectionRange(cursorPos, cursorPos);
                } catch (e) {
                    // Ignorieren wenn Position ungültig
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
     * Konflikt-Warnung anzeigen
     */
    function showConflictWarning(itemId) {
        const statusEl = document.getElementById(`collab-status-${itemId}`);
        if (!statusEl) return;

        const originalColor = statusEl.style.color;
        statusEl.textContent = '⚠️ Konflikt erkannt - Überprüfe den Text';
        statusEl.style.color = '#ff9800';
        statusEl.style.fontWeight = 'bold';

        // Nach 5 Sekunden zurücksetzen
        setTimeout(() => {
            statusEl.style.fontWeight = '';
        }, 5000);
    }

    /**
     * Einfache MD5-Hash-Funktion (für Konflikt-Erkennung)
     */
    function md5(str) {
        // Für echte Anwendung sollte eine richtige MD5-Library verwendet werden
        // Dies ist eine vereinfachte Version nur für Konflikt-Erkennung
        return str.length + '_' + str.substring(0, 10) + '_' + str.substring(str.length - 10);
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

    // Global verfügbar machen für Debug
    window.collabProtocol = {
        textareaStates: textareaStates,
        reinit: initCollaborativeProtocol
    };

})();
