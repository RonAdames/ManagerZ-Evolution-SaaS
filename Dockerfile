FROM php:8.2-apache

# Definir argumentos de build
ARG DEBIAN_FRONTEND=noninteractive
ARG APP_VERSION=1.0.1
ENV APP_VERSION=${APP_VERSION}

# Instalar dependências do sistema
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    zip \
    unzip \
    git \
    curl \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql zip

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Habilitar mod_rewrite do Apache
RUN a2enmod rewrite

# Configurar o Apache
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf
COPY apache-config.conf /etc/apache2/sites-available/000-default.conf

# Configurar o diretório de trabalho
WORKDIR /var/www/html

# Copiar os arquivos da aplicação
COPY . .

# Instalar dependências do Composer
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Copiar e configurar o script de inicialização
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/public/logs

# Expor a porta 80
EXPOSE 80

# Definir o script de inicialização
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"] 