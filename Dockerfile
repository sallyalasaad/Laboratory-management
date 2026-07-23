FROM php:8.2-apache

# 1. تثبيت الاعتماديات والحزم المطلوبة
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

# 2. تفعيل mod_rewrite الخاص بأباتشي (مهم جداً لـ Laravel Routing)
RUN a2enmod rewrite

# 3. تثبيت Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. تحديد مجلد العمل الرئيسي
WORKDIR /var/www

# 5. نسخ كافة ملفات المشروع إلى المجلد
COPY . /var/www

# 6. تثبيت الحزم وتوليد ملف Autoload (مع السماح بتشغيله كـ Root)
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 7. تعديل مسار Apache ليؤشر إلى مجلد public
RUN sed -i 's!/var/www/html!/var/www/public!g' /etc/apache2/sites-available/000-default.conf
RUN sed -i 's!/var/www/html!/var/www/public!g' /etc/apache2/apache2.conf

# 8. ضبط الملكية والصلاحيات للمجلد بالكامل وللمجلدات الحساسة
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

EXPOSE 80