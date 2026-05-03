# DEPRECATED Dateien

Dieser Ordner enthält veraltete Dateien, die nicht mehr in Produktion verwendet werden.

## Verschobene Dateien

### ajax_meeting_actions.php
- **Ersetzt durch**: `api/meeting_actions.php`
- **Grund**: Neue API-Architektur mit Best Practices
- **Migration**: 26.04.2025 (bereits in Produktiv-Code umgesetzt)
- **Status**: Wird nur noch in Tests referenziert

### ajax_get_protocol.php
- **Ersetzt durch**: `api/meeting_get_updates.php`
- **Grund**: Neue API-Architektur mit Session-Management
- **Migration**: 26.04.2025 (bereits in Produktiv-Code umgesetzt)
- **Status**: Wird nur noch in Tests referenziert

## Vorteile der neuen API

Die neuen API-Endpunkte bieten:
- ✅ Besseres Session-Management (session_write_close)
- ✅ Korrekte HTTP-Status-Codes
- ✅ Keine Error-Suppression (@-Operator)
- ✅ Konsistente Fehlerbehandlung
- ✅ Performance-Optimierungen

## Kann gelöscht werden?

**Ja, nach vollständiger Migration der Test-Dateien (test_ajax.php).**

Aktuell (03.05.2026):
- ❌ Noch in test_ajax.php referenziert
- ⚠️ Nach Test-Update können diese Dateien gelöscht werden

## Nächste Schritte

1. `test_ajax.php` auf neue API umstellen
2. Nach erfolgreichem Test: Diese Dateien löschen
3. Deprecated-Ordner kann dann entfernt werden

---
Verschoben am: 03.05.2026
