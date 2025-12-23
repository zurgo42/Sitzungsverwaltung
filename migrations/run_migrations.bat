@echo off
REM Migrations für Production Deployment (Windows)
REM Alle Migrationen dieser Session ausführen

setlocal
set DB_NAME=Sitzungsverwaltung
set MYSQL_PATH=mysql
set SCRIPT_DIR=%~dp0

echo ===================================
echo Sitzungsverwaltung - Migrationen
echo ===================================
echo.

REM Migration 1: Externe Teilnehmer
echo -----------------------------------
echo Migration 1: Externe Teilnehmer
echo -----------------------------------
echo Datei: add_external_participants.sql
echo.
echo Diese Migration fuegt hinzu:
echo   - Tabelle: svexternal_participants
echo   - Spalte: svopinion_responses.external_participant_id
echo   - Spalte: svpoll_responses.external_participant_id
echo.
set /p CONFIRM1="Migration ausfuehren? (j/n): "
if /i "%CONFIRM1%"=="j" (
    %MYSQL_PATH% -u root -p %DB_NAME% < "%SCRIPT_DIR%add_external_participants.sql"
    if %ERRORLEVEL% EQU 0 (
        echo + Migration erfolgreich
    ) else (
        echo X Migration fehlgeschlagen
        pause
        exit /b 1
    )
) else (
    echo - Uebersprungen
)
echo.

REM Migration 2: Externe Dokument-Links
echo -----------------------------------
echo Migration 2: Externe Dokument-Links
echo -----------------------------------
echo Datei: add_external_url_to_documents.sql
echo.
echo Diese Migration fuegt hinzu:
echo   - Spalte: svdocuments.external_url
echo   - Aendert: filepath, filename, filesize auf NULL erlaubt
echo.
set /p CONFIRM2="Migration ausfuehren? (j/n): "
if /i "%CONFIRM2%"=="j" (
    %MYSQL_PATH% -u root -p %DB_NAME% < "%SCRIPT_DIR%add_external_url_to_documents.sql"
    if %ERRORLEVEL% EQU 0 (
        echo + Migration erfolgreich
    ) else (
        echo X Migration fehlgeschlagen
        pause
        exit /b 1
    )
) else (
    echo - Uebersprungen
)
echo.

REM Optional: Weitere Migrationen
echo -----------------------------------
echo Optionale Migrationen
echo -----------------------------------
echo.
echo Migration 3: Target Type fuer Polls (optional)
echo Datei: add_target_type_to_polls.sql
echo Wird nur benoetigt, wenn die Terminplanung genutzt wird
echo.
set /p CONFIRM3="Migration ausfuehren? (j/n): "
if /i "%CONFIRM3%"=="j" (
    %MYSQL_PATH% -u root -p %DB_NAME% < "%SCRIPT_DIR%add_target_type_to_polls.sql"
    if %ERRORLEVEL% EQU 0 (
        echo + Migration erfolgreich
    ) else (
        echo ~ Migration fehlgeschlagen (evtl. bereits vorhanden)
    )
) else (
    echo - Uebersprungen
)
echo.

echo ===================================
echo Alle Migrationen abgeschlossen!
echo ===================================
echo.
echo Naechste Schritte:
echo 1. Pruefe die Logs auf Fehler
echo 2. Teste die neuen Features im Browser
echo 3. Optional: Richte Cron-Job ein fuer Cleanup
echo.
pause
