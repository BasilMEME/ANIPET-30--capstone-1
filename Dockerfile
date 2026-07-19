FROM dunglas/frankenphp:php8.4

RUN install-php-extensions mysqli pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app/public

COPY . /app/public

RUN composer install --no-dev --optimize-autoloader

ENV SERVER_NAME=:8080

EXPOSE 8080