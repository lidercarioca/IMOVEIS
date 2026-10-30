@echo off
REM Script para backup automático do banco de dados
REM Executar diariamente via Windows Task Scheduler

setlocal enabledelayedexpansion

REM Definir variáveis
set XAMPP_PATH=C:\XAMPP
set HTDOCS_PATH=%XAMPP_PATH%\htdocs
set PHP_BIN=%XAMPP_PATH%\php\php.exe
set BACKUP_SCRIPT=%HTDOCS_PATH%\tools\backup_database.php
set LOG_FILE=%HTDOCS_PATH%\tools\backups\backup_log.txt

REM Data e hora
for /f "tokens=2-4 delims=/ " %%a in ('date /t') do (set mydate=%%c-%%a-%%b)
for /f "tokens=1-2 delims=/:" %%a in ('time /t') do (set mytime=%%a:%%b)

REM Executar backup
echo [%mydate% %mytime%] Iniciando backup... >> "%LOG_FILE%"
"%PHP_BIN%" "%BACKUP_SCRIPT%" >> "%LOG_FILE%" 2>&1

if %errorlevel% equ 0 (
    echo [%mydate% %mytime%] Backup concluído com sucesso >> "%LOG_FILE%"
) else (
    echo [%mydate% %mytime%] ERRO ao fazer backup >> "%LOG_FILE%"
)

echo. >> "%LOG_FILE%"
