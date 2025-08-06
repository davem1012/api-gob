FROM unit:php8.2

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

    # Configurar errores para producción
RUN echo "display_errors=Off" > /usr/local/etc/php/conf.d/errors.ini \
    && echo "log_errors=On" >> /usr/local/etc/php/conf.d/errors.ini \
    && echo "error_log=/var/www/html/logs/php_errors.log" >> /usr/local/etc/php/conf.d/errors.ini

# Copiar código (incluyendo vendor)
COPY . /var/www/html/

WORKDIR /var/www/html

# Crear directorios necesarios y establecer permisos
RUN mkdir -p logs var/cache var/log \
    && chown -R unit:unit . \
    && chmod -R 755 logs var

# Configuración de Unit para Slim
RUN echo '{\
    "listeners": {\
        "*:8081": {\
            "pass": "applications/slim_app"\
        }\
    },\
    "applications": {\
        "slim_app": {\
            "type": "php",\
            "root": "/var/www/html/public",\
            "script": "index.php"\
        }\
    }\
}' > /docker-entrypoint.d/config.json

EXPOSE 8081

CMD ["unitd", "--no-daemon"]