/**
 * MinD-Referentenliste - Moderne JavaScript-Funktionalität
 * Verwendet ES6+ Features
 */

(function() {
    'use strict';

    /**
     * Modal-Funktionalität
     */
    class ModalManager {
        constructor() {
            this.modal = document.getElementById('detailModal');
            this.modalBody = document.getElementById('modalBody');
            this.closeBtn = document.querySelector('.modal-close');

            this.init();
        }

        init() {
            if (!this.modal) return;

            // Event Listeners
            this.closeBtn?.addEventListener('click', () => this.close());
            this.modal.addEventListener('click', (e) => {
                if (e.target === this.modal) this.close();
            });

            // ESC-Taste zum Schließen
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.modal.style.display === 'block') {
                    this.close();
                }
            });
        }

        open() {
            if (this.modal) {
                this.modal.style.display = 'block';
                document.body.style.overflow = 'hidden';
            }
        }

        close() {
            if (this.modal) {
                this.modal.style.display = 'none';
                document.body.style.overflow = '';
            }
        }

        setContent(html) {
            if (this.modalBody) {
                this.modalBody.innerHTML = html;
            }
        }

        showLoading() {
            this.setContent('<div class="loading">Lade Daten...</div>');
        }

        showError(message) {
            this.setContent(`<div class="alert alert-error"><p>${message}</p></div>`);
        }
    }

    /**
     * Vortrag-Details laden
     */
    class VortragLoader {
        constructor(modalManager) {
            this.modalManager = modalManager;
            this.init();
        }

        init() {
            // Details-Buttons
            document.querySelectorAll('.details-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const id = btn.dataset.id;
                    const mnr = btn.dataset.mnr;
                    this.loadVortrag(id, mnr);
                });
            });

            // Info-Links im Formular
            document.querySelectorAll('.info-link').forEach(link => {
                link.addEventListener('click', (e) => {
                    e.preventDefault();
                    const id = link.dataset.id;
                    const mnr = link.dataset.mnr;
                    this.loadVortrag(id, mnr);
                });
            });
        }

        async loadVortrag(id, mnr) {
            try {
                this.modalManager.showLoading();
                this.modalManager.open();

                const response = await fetch(`referenten.php?steuer=17&ID=${id}&MNr=${mnr}`);

                if (!response.ok) {
                    throw new Error('Fehler beim Laden der Daten');
                }

                const html = await response.text();
                this.modalManager.setContent(html);

            } catch (error) {
                console.error('Fehler:', error);
                this.modalManager.showError('Die Daten konnten nicht geladen werden.');
            }
        }
    }

    /**
     * Formular-Validierung
     */
    class FormValidator {
        constructor() {
            this.forms = document.querySelectorAll('.referenten-form');
            this.init();
        }

        init() {
            this.forms.forEach(form => {
                form.addEventListener('submit', (e) => {
                    if (!this.validateForm(form)) {
                        e.preventDefault();
                    }
                });

                // Echtzeit-Validierung
                form.querySelectorAll('input, textarea, select').forEach(field => {
                    field.addEventListener('blur', () => this.validateField(field));
                });
            });
        }

        validateForm(form) {
            let isValid = true;

            form.querySelectorAll('[required]').forEach(field => {
                if (!this.validateField(field)) {
                    isValid = false;
                }
            });

            return isValid;
        }

        validateField(field) {
            const value = field.value.trim();
            let isValid = true;
            let message = '';

            // Required-Check
            if (field.hasAttribute('required') && !value) {
                isValid = false;
                message = 'Dieses Feld ist erforderlich.';
            }

            // Email-Validierung
            if (field.type === 'email' && value) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(value)) {
                    isValid = false;
                    message = 'Bitte eine gültige E-Mail-Adresse eingeben.';
                }
            }

            // PLZ-Validierung
            if (field.id === 'PLZ' && value && !/^\d{5}$/.test(value)) {
                isValid = false;
                message = 'PLZ muss 5-stellig sein.';
            }

            // Geburtsjahr-Validierung
            if (field.id === 'Gebj' && value && !/^\d{4}$/.test(value)) {
                isValid = false;
                message = 'Bitte ein 4-stelliges Jahr eingeben.';
            }

            this.setFieldStatus(field, isValid, message);
            return isValid;
        }

        setFieldStatus(field, isValid, message) {
            const formGroup = field.closest('.form-group');
            if (!formGroup) return;

            // Entferne alte Fehlermeldungen
            const oldError = formGroup.querySelector('.error-message');
            if (oldError) oldError.remove();

            // Entferne alte Klassen
            field.classList.remove('is-valid', 'is-invalid');

            if (!isValid) {
                field.classList.add('is-invalid');
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.style.color = '#dc3545';
                errorDiv.style.fontSize = '0.875rem';
                errorDiv.style.marginTop = '0.25rem';
                errorDiv.textContent = message;
                formGroup.appendChild(errorDiv);
            } else if (field.value.trim()) {
                field.classList.add('is-valid');
            }
        }
    }

    /**
     * Zeichen-Zähler
     */
    class CharacterCounter {
        constructor() {
            this.init();
        }

        init() {
            document.querySelectorAll('.char-counter').forEach(counter => {
                const targetId = counter.dataset.for;
                const field = document.getElementById(targetId);

                if (field && field.hasAttribute('maxlength')) {
                    const maxLength = parseInt(field.getAttribute('maxlength'));

                    const updateCounter = () => {
                        const currentLength = field.value.length;
                        counter.textContent = `(${currentLength}/${maxLength})`;

                        if (currentLength > maxLength * 0.9) {
                            counter.style.color = '#dc3545';
                        } else {
                            counter.style.color = '#6c757d';
                        }
                    };

                    field.addEventListener('input', updateCounter);
                    updateCounter();
                }
            });
        }
    }

    /**
     * Smooth Scroll für Anker-Links
     */
    class SmoothScroll {
        constructor() {
            this.init();
        }

        init() {
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', (e) => {
                    const href = anchor.getAttribute('href');
                    if (href === '#') return;

                    const target = document.querySelector(href);
                    if (target) {
                        e.preventDefault();
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        }
    }

    /**
     * Tabellen-Sortierung (erweitert)
     */
    class TableSort {
        constructor() {
            this.init();
        }

        init() {
            const table = document.querySelector('.referenten-table');
            if (!table) return;

            const headers = table.querySelectorAll('th');
            headers.forEach((header, index) => {
                if (header.textContent.trim() === 'Aktionen') return;

                header.style.cursor = 'pointer';
                header.title = 'Klicken zum Sortieren';

                header.addEventListener('click', () => {
                    this.sortTable(table, index);
                });
            });
        }

        sortTable(table, columnIndex) {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));

            const sortedRows = rows.sort((a, b) => {
                const aText = a.children[columnIndex]?.textContent.trim() || '';
                const bText = b.children[columnIndex]?.textContent.trim() || '';

                // Versuche numerisch zu sortieren
                const aNum = parseFloat(aText.replace(/[^\d.-]/g, ''));
                const bNum = parseFloat(bText.replace(/[^\d.-]/g, ''));

                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return aNum - bNum;
                }

                // Alphabetisch sortieren
                return aText.localeCompare(bText, 'de');
            });

            // Zeilen neu einfügen
            sortedRows.forEach(row => tbody.appendChild(row));
        }
    }

    /**
     * Utility: Debounce-Funktion
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Auto-Save für Formulare (optional)
     */
    class FormAutoSave {
        constructor() {
            this.storageKey = 'referenten_form_autosave';
            this.init();
        }

        init() {
            const form = document.querySelector('.referenten-form');
            if (!form) return;

            // Lade gespeicherte Daten
            this.loadFormData(form);

            // Speichere bei Änderungen
            const saveData = debounce(() => this.saveFormData(form), 1000);

            form.querySelectorAll('input, textarea, select').forEach(field => {
                // Nicht bei Passwort-Feldern oder CSRF-Tokens
                if (field.type !== 'password' && field.name !== 'csrf_token') {
                    field.addEventListener('input', saveData);
                }
            });

            // Lösche bei Submit
            form.addEventListener('submit', () => this.clearFormData());
        }

        saveFormData(form) {
            const data = {};
            const formData = new FormData(form);

            for (let [key, value] of formData.entries()) {
                if (key !== 'csrf_token' && key !== 'steuer') {
                    data[key] = value;
                }
            }

            try {
                localStorage.setItem(this.storageKey, JSON.stringify(data));
            } catch (e) {
                console.warn('Auto-Save fehlgeschlagen:', e);
            }
        }

        loadFormData(form) {
            try {
                const savedData = localStorage.getItem(this.storageKey);
                if (!savedData) return;

                const data = JSON.parse(savedData);

                Object.entries(data).forEach(([key, value]) => {
                    const field = form.elements[key];
                    if (field && !field.value) {
                        field.value = value;
                    }
                });
            } catch (e) {
                console.warn('Auto-Load fehlgeschlagen:', e);
            }
        }

        clearFormData() {
            try {
                localStorage.removeItem(this.storageKey);
            } catch (e) {
                console.warn('Clear Auto-Save fehlgeschlagen:', e);
            }
        }
    }

    /**
     * Initialisierung
     */
    document.addEventListener('DOMContentLoaded', () => {
        // Initialisiere alle Komponenten
        const modalManager = new ModalManager();
        const vortragLoader = new VortragLoader(modalManager);
        const formValidator = new FormValidator();
        const characterCounter = new CharacterCounter();
        const smoothScroll = new SmoothScroll();
        const tableSort = new TableSort();
        // Auto-Save optional aktivieren
        // const formAutoSave = new FormAutoSave();

        console.log('MinD-Referentenliste initialisiert');
    });

})();
