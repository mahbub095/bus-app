@echo off
echo Adding bus-app.test to hosts file...
echo 127.0.0.1    bus-app.test >> C:\Windows\System32\drivers\etc\hosts
echo 127.0.0.1    www.bus-app.test >> C:\Windows\System32\drivers\etc\hosts
echo Done! Now reload Apache in Laragon (click Stop All then Start All).
pause
