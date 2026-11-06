# Образ для GitHub Codespaces: PHP 8.3 + расширение pdo_mysql + MariaDB.
FROM mcr.microsoft.com/devcontainers/php:1-8.3-bookworm

# Источник пакетов Yarn в базовом образе с просроченным ключом ломает apt-get update — убираем его
RUN rm -f /etc/apt/sources.list.d/yarn.list \
    && apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends mariadb-server \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*
