FROM php:8.2-fpm

# Instala dependências do sistema e extensões PHP necessárias para o Laravel e MySQL
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev

RUN apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Instala o Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# Aumenta os limites de upload e a quantidade máxima de arquivos simultâneos no PHP
RUN echo "upload_max_filesize = 1G" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 1G" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "memory_limit = 2G" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_file_uploads = 1000" >> /usr/local/etc/php/conf.d/uploads.ini