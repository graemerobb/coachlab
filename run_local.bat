@echo off
setlocal

set "ROOT_DIR=%~dp0"
cd /d "%ROOT_DIR%"

if exist ".env" (
  for /f "usebackq tokens=1,* delims==" %%A in (".env") do (
    if not "%%A"=="" (
      if not "%%A:~0,1%"=="#" (
        set "%%A=%%B"
      )
    )
  )
)

if "%HOST%"=="" set "HOST=127.0.0.1"
if "%PORT%"=="" set "PORT=8000"

where php >nul 2>nul
if errorlevel 1 (
  echo PHP is required to run CoachLab locally.
  echo Install PHP and ensure "php" is available on PATH, then run this file again.
  exit /b 1
)

echo Starting CoachLab at http://%HOST%:%PORT%/coachlab/coachlab.html
echo Press Ctrl+C to stop.

php -S %HOST%:%PORT% -t public_html router.php
