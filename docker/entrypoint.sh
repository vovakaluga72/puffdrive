#!/usr/bin/env bash
# Запуск контейнера: MariaDB, база с демо-данными, config/config.php, сервер PHP.
set -euo pipefail
cd "$(dirname "$0")/.."

mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld
service mariadb start >/dev/null

# База создаётся при первом запуске контейнера (на бесплатном тарифе — после каждого перезапуска)
if ! mariadb -N -e "SELECT 1 FROM puffdrive.products LIMIT 1" >/dev/null 2>&1; then
    echo "==> Создание базы puffdrive"
    mariadb <<'SQL'
CREATE DATABASE IF NOT EXISTS puffdrive CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'puffdrive'@'localhost' IDENTIFIED BY 'puffdrive';
CREATE USER IF NOT EXISTS 'puffdrive'@'127.0.0.1' IDENTIFIED BY 'puffdrive';
GRANT ALL PRIVILEGES ON puffdrive.* TO 'puffdrive'@'localhost';
GRANT ALL PRIVILEGES ON puffdrive.* TO 'puffdrive'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
    mariadb puffdrive < database/schema.sql
    mariadb puffdrive < database/seed.sql
fi

if [ ! -f config/config.php ]; then
    url="${APP_URL:-${RENDER_EXTERNAL_URL:-http://localhost:${PORT}}}"
    key="${APP_KEY:-$(php -r 'echo bin2hex(random_bytes(32));')}"
    sed -e "s#'url'      => 'http://puffdrive.local'#'url'      => '${url}'#" \
        -e "s#'debug'    => true#'debug'    => false#" \
        -e "s#'change-me-to-random-64-hex-chars'#'${key}'#" \
        -e "s#'user'    => 'root'#'user'    => 'puffdrive'#" \
        -e "s#'pass'    => ''#'pass'    => 'puffdrive'#" \
        config/config.example.php > config/config.php
fi

echo "==> Сайт Puff Drive на порту ${PORT}"
exec php -S "0.0.0.0:${PORT}" -t public public/index.php
