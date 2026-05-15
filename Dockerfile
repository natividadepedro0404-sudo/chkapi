FROM php:8.2-cli

WORKDIR /usr/src/app

# Copiar arquivos
COPY . .

# Expor porta
EXPOSE 8080

# Iniciar servidor PHP embutido
CMD php -S 0.0.0.0:8080 index.php
