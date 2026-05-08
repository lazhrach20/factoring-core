# Используем свежий PHP 8.3 FPM
FROM php:8.3-fpm

# Устанавливаем системные зависимости и библиотеки
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    libicu-dev \
    libzip-dev \
    librabbitmq-dev \
    && docker-php-ext-install \
    pdo_pgsql \
    intl \
    zip

# Устанавливаем расширения PECL (Redis и AMQP для RabbitMQ)
RUN pecl install redis amqp \
    && docker-php-ext-enable redis amqp

# Устанавливаем Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Рабочая директория
WORKDIR /var/www/html
