FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package*.json ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi
COPY vite.config.js tailwind.config.js postcss.config.js ./
COPY resources ./resources
RUN npm run build

FROM php:8.5-apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
WORKDIR /var/www/html
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libzip-dev libonig-dev libicu-dev libsqlite3-dev ca-certificates \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring intl zip \
    && a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . .
RUN mkdir -p bootstrap/cache storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/private \
    && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache
COPY --from=assets /app/public/build ./public/build
COPY docker/php.ini /usr/local/etc/php/conf.d/alquiler.ini
COPY docker/apache-limits.conf /etc/apache2/conf-available/alquiler-limits.conf
RUN a2enconf alquiler-limits
RUN sed -i 's/\r$//' /var/www/html/00-laravel-deploy.sh && chmod +x /var/www/html/00-laravel-deploy.sh
EXPOSE 10000
CMD ["/bin/sh", "/var/www/html/00-laravel-deploy.sh"]
