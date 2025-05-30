FROM php:8.2-cli as base

RUN apt update && apt install -y git libzip-dev zip unzip wget git gcc make

# PHP
RUN pecl install zip mongodb-1.21.0 && docker-php-ext-enable zip mongodb

RUN docker-php-ext-install sockets && docker-php-ext-enable sockets

RUN git config --global --add safe.directory /sparallel

FROM base as php

RUN curl -sS https://getcomposer.org/installer  \
    | php -- --install-dir=/usr/local/bin --filename=composer

FROM base as golang

# GOLANG
ENV GO_VERSION="1.24.3"

RUN wget -P /tmp "https://dl.google.com/go/go${GO_VERSION}.linux-amd64.tar.gz"

RUN tar -C /usr/local -xzf "/tmp/go${GO_VERSION}.linux-amd64.tar.gz"
RUN rm "/tmp/go${GO_VERSION}.linux-amd64.tar.gz"

ENV GOPATH /go
ENV PATH $GOPATH/bin:/usr/local/go/bin:$PATH
RUN mkdir -p "$GOPATH/src" "$GOPATH/bin" && chmod -R 777 "$GOPATH" && mkdir -p /.cache && chmod -R 777 /.cache

RUN mkdir -p -m 777 /go/pkg/mod/cache

RUN go install github.com/air-verse/air@latest
