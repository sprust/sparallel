FROM php:8.2-cli AS base

RUN apt update && apt install -y \
    git \
    libzip-dev \
    zip \
    unzip \
    wget \
    git \
    gcc \
    make

# PHP
RUN pecl install zip pcntl && docker-php-ext-enable zip

RUN docker-php-ext-install sockets pcntl && docker-php-ext-enable sockets

RUN git config --global --add safe.directory /sparallel

FROM base AS php

RUN curl -sS https://getcomposer.org/installer  \
    | php -- --install-dir=/usr/local/bin --filename=composer

ARG USER_ID
ARG GROUP_ID

# Check if user and group doesn't exist before creating
RUN getent group "$GROUP_ID" || addgroup --gid "$GROUP_ID" user
RUN getent passwd "$USER_ID" || adduser --disabled-password --gecos '' --uid "$USER_ID" --gid "$GROUP_ID" user

USER "$USER_ID"

FROM base AS golang

ENV GO_VERSION="1.24.3"

RUN wget -P /tmp "https://dl.google.com/go/go${GO_VERSION}.linux-amd64.tar.gz"

RUN tar -C /usr/local -xzf "/tmp/go${GO_VERSION}.linux-amd64.tar.gz"
RUN rm "/tmp/go${GO_VERSION}.linux-amd64.tar.gz"

ENV GOPATH /go
ENV PATH $GOPATH/bin:/usr/local/go/bin:$PATH
RUN mkdir -p "$GOPATH/src" "$GOPATH/bin" && chmod -R 777 "$GOPATH" && mkdir -p /.cache && chmod -R 777 /.cache

RUN go install github.com/air-verse/air@latest
RUN go install github.com/golangci/golangci-lint/v2/cmd/golangci-lint@v2.1.6

ARG USER_ID
ARG GROUP_ID

RUN mkdir -p -m 777 /go/pkg/mod/cache/download
RUN chown $USER_ID:$GROUP_ID /go/pkg/mod/cache/download
RUN chmod -R 777 /go/

# Check if user and group doesn't exist before creating
RUN getent group "$GROUP_ID" || addgroup --gid "$GROUP_ID" user
RUN getent passwd "$USER_ID" || adduser --disabled-password --gecos '' --uid "$USER_ID" --gid "$GROUP_ID" user

USER "$USER_ID"
