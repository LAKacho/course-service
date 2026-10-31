FROM php:8.4-apache-bookworm AS base
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libsqlite3-dev libonig-dev libicu-dev unzip curl \
    && docker-php-ext-install -j2 pdo_pgsql pdo_sqlite mbstring intl opcache \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/application.ini
COPY docker/entrypoint.sh /usr/local/bin/application-entrypoint
RUN chmod +x /usr/local/bin/application-entrypoint
ENTRYPOINT ["application-entrypoint"]
CMD ["apache2-foreground"]

FROM base AS runtime
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --no-progress
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && mkdir -p var/cache var/log var/sessions \
    && chown -R www-data:www-data var
ENV APP_ENV=prod APP_DEBUG=0

FROM base AS test
COPY composer.json composer.lock ./
RUN composer install --prefer-dist --no-interaction --no-scripts --no-progress
COPY . .
RUN mkdir -p var/cache var/log var/sessions && chown -R www-data:www-data var
ENV APP_ENV=test APP_DEBUG=1 APP_SECRET=test-only-secret
CMD ["vendor/bin/phpunit"]
