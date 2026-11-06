<#
.SYNOPSIS
    Отправка сайта на GitHub без документации (.md, .txt, Word, PDF).

.DESCRIPTION
    Берёт последний коммит ветки main, убирает из него файлы документации и отправляет
    результат в ветку main репозитория на GitHub. Локальная история и файлы не меняются:
    документация остаётся у вас на компьютере, на GitHub попадает только код сайта.

    Порядок работы:
      1. Сделайте коммит в GitHub Desktop (или git commit) как обычно.
      2. Запустите:  powershell -ExecutionPolicy Bypass -File tools\publish-github.ps1

.PARAMETER Remote
    Имя удалённого репозитория (по умолчанию origin).
#>
param(
    [string]$Remote = 'origin',
    [string]$Branch = 'main',
    # Начать историю на GitHub заново (перезаписывает ветку на GitHub)
    [switch]$Fresh
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [Text.Encoding]::UTF8
Set-Location (Split-Path -Parent $PSScriptRoot)

# Что не публикуется на GitHub
$exclude = '\.(md|txt|docx?|pdf|drawio)$'

$tmpIndex = Join-Path $env:TEMP ("pd-publish-" + [guid]::NewGuid() + '.idx')
try {
    # В новом пустом репозитории ветки ещё нет — это не ошибка
    $ErrorActionPreference = 'Continue'
    git fetch -q $Remote $Branch 2>&1 | Out-Null
    $parent = git rev-parse --verify -q "refs/remotes/$Remote/$Branch" 2>&1 | Where-Object { $_ -is [string] }
    $ErrorActionPreference = 'Stop'

    $entries = git -c core.quotePath=false ls-tree -r HEAD | Where-Object { ($_ -split "`t", 2)[1] -notmatch $exclude }
    # Список файлов передаётся через файл без BOM: конвейер PowerShell 5.1 портит его для git
    $listFile = "$tmpIndex.list"
    [IO.File]::WriteAllText($listFile, ($entries -join "`n") + "`n", (New-Object Text.UTF8Encoding $false))
    $env:GIT_INDEX_FILE = $tmpIndex
    git read-tree --empty
    cmd /c "git update-index --index-info < `"$listFile`""
    if ($LASTEXITCODE -ne 0) { throw 'Не удалось собрать список файлов для публикации' }
    $tree = git write-tree
    Remove-Item Env:GIT_INDEX_FILE
    Remove-Item $listFile -Force
    $count = (git ls-tree -r --name-only $tree | Measure-Object).Count
    if ($count -ne $entries.Count) { throw "В публикацию попало $count файлов из $($entries.Count)" }

    if ($Fresh) { $parent = $null }
    if ($parent -and (git rev-parse "$parent^{tree}") -eq $tree) {
        Write-Host 'На GitHub уже последняя версия — отправлять нечего.' -ForegroundColor Yellow
        return
    }

    $message = git log -1 --format=%s HEAD
    $commit = if ($parent) { git commit-tree $tree -p $parent -m $message } else { git commit-tree $tree -m $message }
    $ref = if ($Fresh) { "+${commit}:refs/heads/$Branch" } else { "${commit}:refs/heads/$Branch" }
    $ErrorActionPreference = 'Continue'
    git push $Remote $ref 2>&1 | ForEach-Object { "$_" }
    if ($LASTEXITCODE -ne 0) { throw 'git push завершился с ошибкой' }
    Write-Host "Отправлено на GitHub: $($entries.Count) файлов, коммит $($commit.Substring(0, 7))" -ForegroundColor Green
} finally {
    if (Test-Path Env:GIT_INDEX_FILE) { Remove-Item Env:GIT_INDEX_FILE }
    if (Test-Path $tmpIndex) { Remove-Item $tmpIndex -Force }
}
