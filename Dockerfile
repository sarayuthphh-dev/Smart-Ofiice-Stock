FROM php:8.3-apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends libonig-dev \
    && docker-php-ext-install pdo_mysql mbstring \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*
ENV TZ=Asia/Bangkok
WORKDIR /var/www/html
COPY . /var/www/html
RUN mkdir -p storage/uploads storage/branding storage/logs \
    && chown -R www-data:www-data storage \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf
