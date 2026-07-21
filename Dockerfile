FROM php:8.2-apache
RUN apt-get update && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev zip unzip git libonig-dev libxml2-dev && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
WORKDIR /var/www
COPY . /var/www
ENV APACHE_DOCUMENT_ROOT /var/www/public
RUN sed -i -e 's|/var/www/html||g' /etc/apache2/sites-available/*.conf
RUN sed -i -e 's|/var/www/||g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
EXPOSE 80
