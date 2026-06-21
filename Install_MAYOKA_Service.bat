@echo off
title Installer MAYOKA Windows Service
echo ===================================================
echo   MENGINSTAL MAYOKA POS SEBAGAI WINDOWS SERVICE
echo ===================================================
echo Pastikan Anda meletakkan file nssm.exe di folder yang sama!
echo.

cd /d "%~dp0"

:: Mengecek apakah nssm.exe tersedia
if not exist nssm.exe (
    echo [ERROR] File nssm.exe tidak ditemukan di folder ini!
    echo Silakan unduh NSSM dari http://nssm.cc/ dan masukkan file nssm.exe ke folder proyek ini.
    pause
    exit
)

echo Menginstal Mayoka Web Server...
nssm install MayokaWebServer "php.exe" "artisan serve --host=0.0.0.0 --port=8000"
nssm set MayokaWebServer AppDirectory "%~dp0"
nssm set MayokaWebServer Description "Server Utama MAYOKA POS"
nssm start MayokaWebServer

echo.
echo Menginstal Mayoka Realtime Server...
nssm install MayokaReverbServer "php.exe" "artisan reverb:start --host=0.0.0.0 --port=8080"
nssm set MayokaReverbServer AppDirectory "%~dp0"
nssm set MayokaReverbServer Description "Server WebSockets MAYOKA POS"
nssm start MayokaReverbServer

echo.
echo ===================================================
echo   INSTALASI SELESAI!
echo   Server sekarang akan menyala otomatis setiap komputer dihidupkan.
echo ===================================================
pause
