<#
.SYNOPSIS
    Временная публичная ссылка на локальный сайт (OSPanel) через Cloudflare Quick Tunnel.

.DESCRIPTION
    Даёт адрес вида https://слова.trycloudflare.com, по которому сайт открывается
    у любого человека, пока работает этот скрипт (окно не закрывать).

    Почему не просто «cloudflared tunnel --url ...»: в некоторых сетях адрес
    api.trycloudflare.com напрямую недоступен. Поэтому ссылка запрашивается через curl
    (он использует системный прокси/VPN, если он настроен), а сам туннель cloudflared
    поднимает напрямую с полученными учётными данными.

    Перед показом посторонним выключите режим отладки: config/config.php → 'debug' = false.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\share-tunnel.ps1
#>
param(
    [string]$SiteHost    = 'puffdrive.local',
    [string]$Cloudflared = "$env:LOCALAPPDATA\cloudflared\cloudflared.exe"
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [Text.Encoding]::UTF8

if (-not (Test-Path $Cloudflared)) {
    throw "Не найден cloudflared: $Cloudflared. Скачайте cloudflared-windows-amd64.exe с https://github.com/cloudflare/cloudflared/releases"
}

# OSPanel выдаёт сайту собственный локальный IP (например, 127.0.1.11)
$ip = [System.Net.Dns]::GetHostAddresses($SiteHost) | Where-Object { $_.AddressFamily -eq 'InterNetwork' } | Select-Object -First 1
if (-not $ip) { throw "Не удалось определить адрес $SiteHost. Запущен ли OSPanel?" }
$origin = "http://$($ip.IPAddressToString):80"

Write-Host 'Запрашиваю временный туннель у Cloudflare…'
$response = (curl.exe -s -m 60 -X POST -H 'Content-Type: application/json' https://api.trycloudflare.com/tunnel) | ConvertFrom-Json
if (-not $response.success) { throw 'Cloudflare не выдал туннель. Попробуйте ещё раз через минуту.' }
$tunnel = $response.result

$credentials = Join-Path $env:TEMP "pd-tunnel-$($tunnel.id).json"
$json = @{ AccountTag = $tunnel.account_tag; TunnelSecret = $tunnel.secret; TunnelID = $tunnel.id } | ConvertTo-Json -Compress
[IO.File]::WriteAllText($credentials, $json, (New-Object Text.UTF8Encoding $false))

Write-Host ''
Write-Host "  Ссылка для знакомых:  https://$($tunnel.hostname)" -ForegroundColor Green
Write-Host '  Работает, пока открыто это окно. Остановить: Ctrl+C.' -ForegroundColor DarkGray
Write-Host ''

try {
    # Прокси для самого туннеля не нужен: соединение идёт напрямую к Cloudflare
    $env:HTTPS_PROXY = ''
    $env:HTTP_PROXY = ''
    & $Cloudflared tunnel --no-autoupdate --protocol http2 --credentials-file $credentials `
        --url $origin --http-host-header $SiteHost run $tunnel.id
}
finally {
    Remove-Item $credentials -ErrorAction SilentlyContinue
}
