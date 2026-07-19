FROM dunglas/frankenphp:php8.4

RUN install-php-extensions mysqli pdo_mysql

WORKDIR /app

COPY . /app

RUN composer install --no-dev --optimize-autoloader

ENV SERVER_NAME=:8080

EXPOSE 8080