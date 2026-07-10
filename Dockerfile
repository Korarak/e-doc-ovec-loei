FROM php:8.1-apache

# Install required system packages and PHP extensions
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    tesseract-ocr \
    tesseract-ocr-tha \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd mysqli pdo pdo_mysql opcache \
    && rm -rf /var/lib/apt/lists/*

# Increase PHP Upload Limits + resources for mPDF stamping of large PDFs
RUN echo "upload_max_filesize = 64M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 64M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 120" >> /usr/local/etc/php/conf.d/uploads.ini

# OPcache — caches compiled PHP bytecode between requests.
# validate_timestamps=1 keeps the code volume-mount (dev) workflow working:
# edited files are picked up within revalidate_freq seconds.
RUN { \
        echo "opcache.enable=1"; \
        echo "opcache.memory_consumption=128"; \
        echo "opcache.interned_strings_buffer=16"; \
        echo "opcache.max_accelerated_files=4000"; \
        echo "opcache.validate_timestamps=1"; \
        echo "opcache.revalidate_freq=2"; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Update the default Apache port to listen on 80
EXPOSE 80
