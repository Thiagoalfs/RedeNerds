FROM php:8.2-apache

# 1. Instala dependências do sistema e bibliotecas para compilar extensões
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mysqli \
        gd \
        zip \
        opcache \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 2. Habilita módulos do Apache e permite uso de .htaccess
RUN a2enmod rewrite headers \
    && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# 3. Diretório de trabalho e cópia do projeto
WORKDIR /var/www/html
COPY . /var/www/html/

# 4. Garante permissões adequadas para o Apache (www-data)
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80

CMD ["apache2-foreground"]
