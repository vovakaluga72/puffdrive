#!/usr/bin/env bash
# Первичная настройка Codespace: расширение PDO MySQL, MariaDB, база с демо-данными, config/config.php.
set -euo pipefail
cd "$(dirname "$0")/.."

echo "==> Расширение pdo_mysql"
php -m | grep -qi pdo_mysql || sudo docker-php-ext-install pdo_mysql >/dev/null

echo "==> MariaDB"
if ! command -v mariadbd >/dev/null 2>&1; then
    sudo apt-get update -qq
    sudo DEBIAN_FRONTEND=noninteractive apt-get install -y -qq mariadb-server >/dev/null
fi
sudo service mariadb start

echo "==> База данных puffdrive"
sudo mariadb <<'SQL'
CREATE DATABASE IF NOT EXISTS puffdrive CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'puffdrive'@'localhost' IDENTIFIED BY 'puffdrive';
CREATE USER IF NOT EXISTS 'puffdrive'@'127.0.0.1' IDENTIFIED BY 'puffdrive';
GRANT ALL PRIVILEGES ON puffdrive.* TO 'puffdrive'@'localhost';
GRANT ALL PRIVILEGES ON puffdrive.* TO 'puffdrive'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
sudo mariadb puffdrive < database/schema.sql
sudo mariadb puffdrive < database/seed.sql

echo "==> config/config.php"
if [ ! -f config/config.php ]; then
    url="http://localhost:8080"
    if [ -n "${CODESPACE_NAME:-}" ]; then
        url="https://${CODESPACE_NAME}-8080.${GITHUB_CODESPACES_PORT_FORWARDING_DOMAIN:-app.github.dev}"
    fi
    key="$(php -r 'echo bin2hex(random_bytes(32));')"
    sed -e "s#'url'      => 'http://puffdrive.local'#'url'      => '${url}'#" \
        -e "s#'change-me-to-random-64-hex-chars'#'${key}'#" \
        -e "s#'user'    => 'root'#'user'    => 'puffdrive'#" \
        -e "s#'pass'    => ''#'pass'    => 'puffdrive'#" \
        config/config.example.php > config/config.php
fi
mkdir -p storage/logs public/uploads
echo "==> Готово"
