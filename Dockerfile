FROM node:22-alpine AS assets
WORKDIR /b
COPY public/assets ./assets
RUN npm i -g esbuild@0.25 >/dev/null 2>&1 \
 && esbuild assets/css/app.css --minify --outfile=out/css/app.css \
 && esbuild assets/css/print.css --minify --outfile=out/css/print.css \
 && esbuild assets/js/app.js --minify --outfile=out/js/app.js \
 && esbuild assets/js/admin.js --minify --outfile=out/js/admin.js

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction --ignore-platform-reqs --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --ignore-platform-reqs

FROM php:8.3-apache
RUN apt-get update -qq && apt-get install -y -qq ca-certificates libzip-dev >/dev/null \
 && docker-php-ext-install -j2 pdo_mysql opcache >/dev/null \
 && pecl install redis >/dev/null && docker-php-ext-enable redis \
 && a2enmod rewrite headers deflate expires remoteip >/dev/null \
 && rm -rf /var/lib/apt/lists/*
COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
COPY --chown=www-data:www-data --from=vendor /app /var/www/html
COPY --chown=www-data:www-data --from=assets /b/out/css /var/www/html/public/assets/css
COPY --chown=www-data:www-data --from=assets /b/out/js /var/www/html/public/assets/js
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh && cd /var/www/html \
 && APP_ENV=prod APP_SECRET=build APP_DEBUG=0 php bin/console cache:warmup --no-interaction \
 && chown -R www-data:www-data var
ENV APP_ENV=prod APP_DEBUG=0 PORT=10000
EXPOSE 10000
CMD ["/entrypoint.sh"]
