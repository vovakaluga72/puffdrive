# Сайт Puff Drive одним контейнером: PHP 8.3 + MariaDB с демо-данными.
# Используется для размещения на Render.com (см. render.yaml).
FROM php:8.3-cli-bookworm

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends mariadb-server \
    && docker-php-ext-install pdo_mysql \
    && rm -rf /var/lib/apt/lists/*

# MariaDB с небольшим потреблением памяти (на бесплатном тарифе 512 МБ)
RUN printf '[mysqld]\ninnodb_buffer_pool_size=48M\nkey_buffer_size=8M\nmax_connections=30\nperformance_schema=OFF\n' \
    > /etc/mysql/mariadb.conf.d/99-puffdrive.cnf

WORKDIR /var/www/puffdrive
COPY . .
RUN mkdir -p storage/logs public/uploads && chmod +x docker/entrypoint.sh

ENV PORT=10000 PHP_CLI_SERVER_WORKERS=4
EXPOSE 10000
CMD ["docker/entrypoint.sh"]
