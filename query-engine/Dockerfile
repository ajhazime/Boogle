FROM php:8.5-cli
WORKDIR /app

RUN apt-get update && apt-get install -y \
    curl\
    zip\
    unzip\
    openssl\
    libssl-dev\
    && pecl install mongodb\
    && docker-php-ext-enable mongodb

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer 

COPY . . 
RUN composer install --no-dev --optimize-autoloader

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]


