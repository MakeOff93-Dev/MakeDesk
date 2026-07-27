FROM php:8.3-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libonig-dev libzip-dev \
    && docker-php-ext-install pdo_mysql curl mbstring zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html

WORKDIR /var/www/html
