FROM php:8.4-apache

# Instalar dependencias del sistema y extensiones PHP
RUN apt-get update && apt-get install -y \
    curl unzip git libicu-dev libzip-dev libpng-dev libjpeg-dev \
    libfreetype6-dev libssl-dev libpq-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pcntl opcache pdo pdo_mysql intl zip gd exif bcmath \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configurar PHP
RUN echo "opcache.enable=1" > /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.jit=tracing" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.jit_buffer_size=256M" >> /usr/local/etc/php/conf.d/opcache.ini

RUN echo "memory_limit=512M" > /usr/local/etc/php/conf.d/memory.ini \        
    && echo "upload_max_filesize=256M" >> /usr/local/etc/php/conf.d/memory.ini \
    && echo "post_max_size=256M" >> /usr/local/etc/php/conf.d/memory.ini \
    && echo "max_execution_time=300" >> /usr/local/etc/php/conf.d/memory.ini

# Habilitar mod_rewrite para Slim
RUN a2enmod rewrite

# Configurar Apache para Slim
RUN echo '<VirtualHost *:8081>\n\
    DocumentRoot /var/www/html/public\n\
    <Directory /var/www/html/public>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Copiar código (incluyendo vendor)
COPY . /var/www/html/


EXPOSE 8081

CMD ["apache2-foreground"]