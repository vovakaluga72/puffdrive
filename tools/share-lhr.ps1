<#
.SYNOPSIS
    Временная публичная ссылка на сайт через localhost.run (работает в Беларуси без VPN).

.DESCRIPTION
    Запускает встроенный сервер PHP на 127.0.0.1:8090 (ему не важен адрес сайта
    в запросе, в отличие от Apache в OSPanel) и SSH-туннель к localhost.run.
    Ссылка вида https://xxxx.lhr.life появится в этом окне. Работает, пока окно открыто.
    Бесплатный localhost.run время от времени меняет адрес — тогда перезапустите скрипт.

    База данных — та же, что у OSPanel, поэтому OSPanel должен быть запущен.
    Перед показом посторонним выключите режим отладки: config/config.php → 'debug' = false.

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File tools\share-lhr.ps1
#>
param(
    [string]$Php  = 'C:\OSPanel\modules\PHP-8.4\php.exe',
    [int]$Port    = 8090
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [Text.Encoding]::UTF8
$root = Split-Path -Parent $PSScriptRoot

$server = Start-Process -FilePath $Php -ArgumentList @('-S', "127.0.0.1:$Port", '-t', "$root\public", "$root\public\index.php") `
    -WindowStyle Hidden -PassThru
Write-Host "Сервер PHP запущен на 127.0.0.1:$Port"
Write-Host 'Подключаюсь к localhost.run… Ссылка будет в строке с адресом https://….lhr.life' -ForegroundColor Green
Write-Host 'Остановить: Ctrl+C' -ForegroundColor DarkGray

try {
    ssh -T -o StrictHostKeyChecking=no -o UserKnownHostsFile=NUL -o ServerAliveInterval=30 `
        -R "80:127.0.0.1:$Port" nokey@localhost.run
}
finally {
    Stop-Process -Id $server.Id -ErrorAction SilentlyContinue
}
