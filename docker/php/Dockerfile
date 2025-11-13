FROM php:8.3-fpm

ARG UID=1000
ARG GID=1000

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    zip \
    curl \
    libpq-dev \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    libicu-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    poppler-utils \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        intl \
        pcntl \
        pdo_pgsql \
        pgsql \
        zip \
        bcmath \
        opcache \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2.7 /usr/bin/composer /usr/local/bin/composer

RUN groupmod -o -g ${GID} www-data \
    && usermod -o -u ${UID} www-data \
    && mkdir -p /var/www/html \
    && chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html

USER www-data

CMD ["php-fpm"]

