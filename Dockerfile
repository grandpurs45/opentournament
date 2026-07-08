FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libsqlite3-dev \
    && if ! php -m | grep -qi '^pdo_sqlite$'; then docker-php-ext-install pdo_sqlite; fi \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html/
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data \
    && a2enmod rewrite

EXPOSE 80
