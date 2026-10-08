@echo off
echo =========================================
echo  SonyaBus - Build Frontend Assets
echo =========================================
echo.

SET NODE="C:\laragon\bin\nodejs\node-v18\node.exe"
SET NPM="C:\laragon\bin\nodejs\node-v18\npm.cmd"
SET DIR=C:\laragon\www\bus-app\backend

echo [1/2] Installing npm dependencies...
cd /d %DIR%
%NPM% install
if %errorlevel% neq 0 (
    echo ERROR: npm install failed.
    pause
    exit /b 1
)

echo.
echo [2/2] Building assets (Vite)...
%NPM% run build
if %errorlevel% neq 0 (
    echo ERROR: npm run build failed.
    pause
    exit /b 1
)

echo.
echo =========================================
echo  Build complete! public/build/ is ready.
echo =========================================
pause
