FROM php:8.2-apache

# Instalar extensões necessárias
RUN docker-php-ext-install curl json

# Habilitar rewrite module
RUN a2enmod rewrite

# Configurar o diretório de trabalho
WORKDIR /var/www/html

# Copiar arquivos
COPY . /var/www/html/

# Expor porta 8080 (Render usa essa porta)
EXPOSE 8080

# Configurar permissões
RUN chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html
