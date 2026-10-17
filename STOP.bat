@echo off
title Black ^& White Ops - STOP
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0windows\stop.ps1"
echo.
pause
