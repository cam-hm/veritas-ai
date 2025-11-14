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
    && rm -rf /var/lib/apt/lists/* \
    && curl -fsSL https://github.com/tianon/gosu/releases/download/1.17/gosu-amd64 -o /usr/local/bin/gosu \
    && chmod +x /usr/local/bin/gosu

COPY --from=composer:2.7 /usr/bin/composer /usr/local/bin/composer

RUN groupmod -o -g ${GID} www-data \
    && usermod -o -u ${UID} www-data \
    && mkdir -p /var/www/html \
    && chown -R www-data:www-data /var/www/html

COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

WORKDIR /var/www/html

# Don't set USER here - let entrypoint handle user switching
# This allows entrypoint to run as root to fix permissions
ENTRYPOINT ["docker-entrypoint.sh"]

CMD ["php-fpm"]

