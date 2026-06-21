' ==========================================================
'  MAYOKA POS - LAUNCHER SILUMAN (Tanpa Terminal Hitam)
' ==========================================================

Set objFSO = CreateObject("Scripting.FileSystemObject")
strPath = objFSO.GetParentFolderName(WScript.ScriptFullName)
Set objShell = CreateObject("WScript.Shell")

' 1. Menjalankan Server Utama di Latar Belakang (Hidden/Siluman)
' Angka 0 di akhir berarti jendela CMD tidak akan ditampilkan sama sekali
objShell.Run "cmd /c cd /d """ & strPath & """ && php artisan serve --host=0.0.0.0 --port=8000", 0, False

' 2. Menjalankan Server Real-time (WebSocket) di Latar Belakang
objShell.Run "cmd /c cd /d """ & strPath & """ && php artisan reverb:start --host=0.0.0.0 --port=8080", 0, False

' 3. Menunggu 3 Detik (3000 milidetik) agar server siap
WScript.Sleep 3000

' 4. Membuka Google Chrome / Browser Bawaan
' Angka 1 berarti browser dibuka secara normal (tampak di layar)
objShell.Run "http://localhost:8000", 1, False

Set objShell = Nothing
Set objFSO = Nothing
