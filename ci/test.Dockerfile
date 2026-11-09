FROM php:8.3-cli
RUN docker-php-ext-install -j2 pdo_mysql >/dev/null
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update -qq && apt-get install -y -qq unzip git >/dev/null && git config --global --add safe.directory /app
WORKDIR /app
COPY composer.json composer.lock symfony.lock ./
RUN composer install --no-scripts --no-autoloader --no-interaction --ignore-platform-req=ext-redis --prefer-dist -q
COPY . .
RUN composer dump-autoload --ignore-platform-req=ext-redis -q
