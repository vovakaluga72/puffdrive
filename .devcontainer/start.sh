#!/usr/bin/env bash
# Запуск при каждом старте Codespace: MariaDB и встроенный сервер PHP на порту 8080.
cd "$(dirname "$0")/.."
root="$(pwd)"
mkdir -p storage/logs
sudo service mariadb start >/dev/null

# Сервер запускается как отдельная служба (start-stop-daemon), иначе Codespaces
# завершает его вместе с этим скриптом
pid=/tmp/puffdrive-php.pid
start-stop-daemon --stop --quiet --pidfile "$pid" --remove-pidfile 2>/dev/null || true
start-stop-daemon --start --background --make-pidfile --pidfile "$pid" \
    --chdir "$root" --output "$root/storage/logs/php-server.log" \
    --exec "$(command -v php)" -- -S 0.0.0.0:8080 -t public public/index.php
echo "Сайт Puff Drive запущен на порту 8080 (вкладка PORTS)"
