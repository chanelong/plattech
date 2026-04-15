FROM php:8.2-cli

RUN docker-php-ext-install mysqli pdo pdo_mysql

COPY . /var/www/html/

WORKDIR /var/www/html

EXPOSE 8080

CMD ["php", "-S", "0.0.0.0:8080", "-t", "."]
