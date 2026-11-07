<#
.SYNOPSIS
    Держать сайт Puff Drive доступным по ссылке Codespaces долгое время (по умолчанию сутки).

.DESCRIPTION
    GitHub усыпляет Codespace максимум через 4 часа без активности. Этот скрипт каждые
    15 минут запускает tools\start-site.ps1: проверяет сайт, будит Codespace, если он уснул,
    и снова открывает порт 8080 для всех. Ссылка при этом не меняется.

    Работает, пока открыто это окно и включён компьютер. Остановить — Ctrl+C.
    После показа остановите Codespace на github.com/codespaces, чтобы не тратить бесплатные часы.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\keep-site.ps1 -Hours 24
#>
param(
    [int]$Hours = 24,
    [int]$EveryMinutes = 15
)

[Console]::OutputEncoding = [Text.Encoding]::UTF8
$until = (Get-Date).AddHours($Hours)
Write-Host "Сторож сайта работает до $($until.ToString('dd.MM HH:mm')). Не закрывайте это окно. Остановить — Ctrl+C." -ForegroundColor Cyan

while ((Get-Date) -lt $until) {
    Write-Host ''
    Write-Host "[$((Get-Date).ToString('HH:mm'))] Проверка сайта..." -ForegroundColor DarkGray
    & powershell -NoProfile -ExecutionPolicy Bypass -File (Join-Path $PSScriptRoot 'start-site.ps1')
    Start-Sleep -Seconds ($EveryMinutes * 60)
}
Write-Host 'Время работы сторожа закончилось. Остановите Codespace на github.com/codespaces.' -ForegroundColor Yellow
