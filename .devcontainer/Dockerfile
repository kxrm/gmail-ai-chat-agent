FROM mcr.microsoft.com/devcontainers/php:8.2

RUN apt-get update && apt-get install -y \
    git \
    zip \
    unzip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

RUN docker-php-ext-install sockets 