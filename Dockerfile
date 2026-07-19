FROM dunglas/frankenphp:php8.4

RUN install-php-extensions mysqli pdo_mysql

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY . /app

RUN composer install --no-dev --optimize-autoloader

ENV SERVER_NAME=":8080"
ENV FRANKENPHP_CONFIG="worker ./login.php"
ENV CADDY_GLOBAL_OPTIONS="auto_https off"

EXPOSE 8080

CMD ["frankenphp", "php-server", "--listen", ":8080", "--root", "/app"]