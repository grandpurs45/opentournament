FROM php:8.3-apache

RUN docker-php-ext-install pdo_sqlite

COPY . /var/www/html/
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html/data \
    && a2enmod rewrite

EXPOSE 80
