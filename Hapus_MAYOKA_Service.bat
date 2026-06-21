@echo off
title Uninstaller MAYOKA Windows Service
echo ===================================================
echo   MENGHAPUS MAYOKA POS DARI WINDOWS SERVICE
echo ===================================================

cd /d "%~dp0"

:: Mengecek apakah nssm.exe tersedia
if not exist nssm.exe (
    echo [ERROR] File nssm.exe tidak ditemukan di folder ini!
    pause
    exit
)

echo Mematikan dan menghapus Mayoka Web Server...
nssm stop MayokaWebServer
nssm remove MayokaWebServer confirm

echo.
echo Mematikan dan menghapus Mayoka Realtime Server...
nssm stop MayokaReverbServer
nssm remove MayokaReverbServer confirm

echo.
echo ===================================================
echo   PENGHAPUSAN SELESAI!
echo   MAYOKA tidak akan lagi menyala secara otomatis.
echo ===================================================
pause
