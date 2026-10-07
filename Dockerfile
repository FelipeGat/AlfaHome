FROM php:8.2-fpm

# Força IPv4 no apt (o bridge do Docker na VPS não tem IPv6,
# e deb.debian.org retorna só AAAA no resolver padrão)
RUN echo 'Acquire::ForceIPv4 "true";' > /etc/apt/apt.conf.d/99force-ipv4

# Instala dependências do sistema
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    locales \
    zip \
    jpegoptim \
    optipng \
    pngquant \
    git \
    curl \
    unzip \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    nodejs \
    npm \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd opcache

# Instala extensão Redis (phpredis nativa)
RUN pecl install redis && docker-php-ext-enable redis

# Instala Composer
COPY --from=composer:2.5 /usr/bin/composer /usr/bin/composer

# Define diretório de trabalho
WORKDIR /var/www

# Copia arquivos do projeto
COPY . /var/www

# Instala dependências do PHP
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Instala dependências do Node e compila assets
RUN npm install && npm run build

# OPcache (código compilado em memória)
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache-alfahome.ini

# Permissões
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Ao subir: caches do Laravel (config, rotas, eventos, telas)
COPY docker/entrypoint.sh /usr/local/bin/alfahome-entrypoint
RUN chmod +x /usr/local/bin/alfahome-entrypoint
ENTRYPOINT ["alfahome-entrypoint"]

EXPOSE 9000
CMD ["php-fpm"]
