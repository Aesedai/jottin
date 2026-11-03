# Shared runtime: Apache + mod_php with only the extensions CodeIgniter needs.
FROM php:8.3-apache AS base
RUN apt-get update \
 && apt-get install -y --no-install-recommends libicu-dev git unzip \
 && docker-php-ext-install intl mysqli opcache \
 && rm -rf /var/lib/apt/lists/*
RUN a2enmod rewrite headers
# zz- so this loads after Debian's security.conf, which would otherwise override it.
COPY docker/apache/jottin.conf /etc/apache2/conf-available/zz-jottin.conf
RUN a2enconf zz-jottin \
 && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && printf 'expose_php=Off\n' > "$PHP_INI_DIR/conf.d/zz-jottin.ini"
WORKDIR /var/www/html

# Dev: source is bind-mounted; www-data matches the host UID so writable/ just works.
FROM base AS dev
ARG UID=1000
ARG GID=1000
RUN groupmod -o -g "$GID" www-data && usermod -o -u "$UID" www-data
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Prod: immutable image, no dev dependencies, no tests, config comes from env vars.
FROM base AS prod
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader \
 && rm /usr/bin/composer \
 && chown -R www-data:www-data writable
