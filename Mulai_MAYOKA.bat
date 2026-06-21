@echo off
title MAYOKA POS Launcher
echo ===================================================
echo   MEMULAI SERVER MAYOKA POS...
echo   MOHON TUNGGU SEBENTAR...
echo ===================================================

:: Berpindah ke direktori tempat file .bat ini berada
cd /d "%~dp0"

:: 1. Menjalankan Server Utama (Web Server) di jendela baru yang disembunyikan (Minimize)
start "MAYOKA SERVER (JANGAN DITUTUP)" /MIN php artisan serve --host=0.0.0.0 --port=8000

:: 2. Menjalankan Server Realtime (WebSocket) di jendela baru yang disembunyikan (Minimize)
start "MAYOKA REALTIME (JANGAN DITUTUP)" /MIN php artisan reverb:start --host=0.0.0.0 --port=8080

:: 3. Menunggu 3 detik agar server siap sepenuhnya
timeout /t 3 /nobreak > NUL

:: 4. Membuka Google Chrome atau browser bawaan ke halaman POS
echo Membuka Aplikasi di Browser...
start http://localhost:8000

:: Tutup jendela launcher ini
exit
