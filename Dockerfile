FROM php:8.2-apache

# تثبيت الحزم المطلوبة
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# نسخ ملفات المشروع
COPY . /var/www

# تثبيت حزم لارافيل عبر Composer داخل الحاوية
RUN composer install --no-dev --optimize-autoloader

# ضبط مسار الوثائق الافتراضي في أباتشي
RUN sed -i 's!/var/www/html!/var/www/public!g' /etc/apache2/sites-available/000-default.conf
RUN sed -i 's!/var/www/html!/var/www/public!g' /etc/apache2/apache2.conf

# ضبط الصلاحيات لمجلدات التخزين
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

EXPOSE 80