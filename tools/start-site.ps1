<#
.SYNOPSIS
    Включить сайт Puff Drive на GitHub Codespaces и получить ссылку для всех.

.DESCRIPTION
    Если по ссылке открывается 404 — Codespace уснул. Этот скрипт:
      1) запускает Codespace «Puff Drive» в аккаунте vovakaluga72 (или создаёт новый),
      2) ждёт, пока сайт поднимется,
      3) открывает порт 8080 для всех (Public),
      4) проверяет ссылку без входа в GitHub и копирует её в буфер обмена.
    Codespace сам засыпает через 4 часа без активности — тогда просто запустите скрипт снова.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\start-site.ps1
#>
$ErrorActionPreference = 'Continue'
[Console]::OutputEncoding = [Text.Encoding]::UTF8
$gh = 'C:\Program Files\GitHub CLI\gh.exe'
$repo = 'vovakaluga72/puffdrive'
# Связь с GitHub из Беларуси стабильнее через прокси, если он запущен
if (Test-NetConnection 127.0.0.1 -Port 10809 -InformationLevel Quiet -WarningAction SilentlyContinue) {
    $env:HTTPS_PROXY = 'http://127.0.0.1:10809'
}

& $gh auth switch -u vovakaluga72 2>$null | Out-Null
$cs = & $gh codespace list -R $repo --json name -q '.[0].name' 2>$null
if (-not $cs) {
    Write-Host 'Codespace нет — создаю новый (3–5 минут)...' -ForegroundColor Yellow
    $cs = (& $gh codespace create -R $repo -b main -m basicLinux32gb --display-name 'Puff Drive' --idle-timeout 240m --retention-period 720h 2>$null | Select-Object -Last 1)
}
Write-Host "Codespace: $cs — запускаю сайт..."

$ok = $false
for ($i = 1; $i -le 30; $i++) {
    # SSH-команда сама будит уснувший Codespace
    $code = & $gh codespace ssh -c $cs -- 'curl -s -o /dev/null -w "%{http_code}" http://localhost:8080/ || true' 2>$null | Select-Object -Last 1
    if ($code -ne '200') { & $gh codespace ssh -c $cs -- 'bash /workspaces/puffdrive/.devcontainer/start.sh' 2>$null | Out-Null }
    else { $ok = $true; break }
    Start-Sleep -Seconds 10
}
if (-not $ok) { Write-Host 'Сайт не поднялся. Откройте github.com/codespaces и запустите Puff Drive вручную.' -ForegroundColor Red; exit 1 }

& $gh codespace ports visibility 8080:public -c $cs 2>$null | Out-Null
$url = "https://$cs-8080.app.github.dev/"
try { $status = (Invoke-WebRequest $url -UseBasicParsing -TimeoutSec 40).StatusCode } catch { $status = $_.Exception.Message }
Set-Clipboard $url
Write-Host ''
Write-Host "Ссылка для всех: $url" -ForegroundColor Green
Write-Host "Проверка без входа в GitHub: $status (ссылка скопирована в буфер обмена)"
Write-Host 'Сайт уснёт через 4 часа без активности — тогда запустите этот скрипт снова.'
