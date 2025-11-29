FROM php:8.4-cli
WORKDIR /usr/src/myapp

RUN apt-get update && apt-get install zip unzip git curl libxml2-dev -y \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && docker-php-ext-install soap \
    && curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer \
    && mkdir /.composer && chmod 777 /.composer
