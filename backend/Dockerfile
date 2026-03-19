FROM php:8.3-cli

# Enable MySQL support for PDO used by the API.
RUN docker-php-ext-install pdo_mysql

WORKDIR /var/www/html

EXPOSE 8080
