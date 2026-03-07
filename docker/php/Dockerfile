FROM php:8.3-fpm-alpine

# Instalar dependencias para PostgreSQL
RUN apk add --no-cache postgresql-dev \
    && docker-php-ext-install pdo_pgsql

WORKDIR /var/www/html