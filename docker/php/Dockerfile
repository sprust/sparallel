FROM php:8.2-cli

RUN apt update && apt install -y git libzip-dev zip unzip

RUN pecl install zip && docker-php-ext-enable zip

# composer
RUN curl -sS https://getcomposer.org/installer  \
    | php -- --install-dir=/usr/local/bin --filename=composer

RUN git config --global --add safe.directory /sparallel
