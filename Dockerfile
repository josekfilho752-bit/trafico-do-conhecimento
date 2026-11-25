FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite \
    && echo "AddDefaultCharset UTF-8" > /etc/apache2/conf-enabled/charset.conf

WORKDIR /var/www/html
