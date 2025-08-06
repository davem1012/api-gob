FROM unit:php8.4

RUN apt update && apt install -y \
    curl unzip git libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libssl-dev libpq-dev \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt install -y nodejs \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pcntl opcache pdo pdo_mysql intl zip gd exif ftp bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis

RUN echo "opcache.enable=1" > /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.jit=tracing" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "opcache.jit_buffer_size=256M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "memory_limit=512M" > /usr/local/etc/php/conf.d/custom.ini \        
    && echo "upload_max_filesize=256M" >> /usr/local/etc/php/conf.d/custom.ini \
    && echo "post_max_size=256M" >> /usr/local/etc/php/conf.d/custom.ini

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
# Define el volumen después de establecer el WORKDIR
VOLUME /var/www/html
COPY . .

#RUN chown -R unit:unit /var/www/html/storage /var/www/html/bootstrap/cache \
#    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache


#RUN chown -R unit:unit storage bootstrap/cache && chmod -R 775 storage bootstrap/cache

#RUN composer install --optimize-autoloader --no-dev
RUN composer install --prefer-dist --optimize-autoloader --no-interaction

#RUN npm install && npm run build

EXPOSE 8001

CMD ["unitd", "--no-daemon"]