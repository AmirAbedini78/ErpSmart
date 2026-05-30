FROM php:8.2-fpm-bookworm

WORKDIR /var/www/html

RUN rm -f /etc/apt/sources.list.d/*

RUN printf '%s\n' \
"deb [trusted=yes] http://mirror.iranserver.com/debian bookworm main contrib non-free non-free-firmware" \
"deb [trusted=yes] http://mirror.iranserver.com/debian bookworm-updates main contrib non-free non-free-firmware" \
"deb [trusted=yes] http://mirror.iranserver.com/debian-security bookworm-security main contrib non-free non-free-firmware" \
> /etc/apt/sources.list

RUN echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid

RUN apt-get clean && \
    apt-get update -o Acquire::Check-Valid-Until=false && \
    apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client

RUN docker-php-ext-configure gd --with-freetype --with-jpeg

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    zip \
    exif \
    bcmath \
    gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

CMD ["php-fpm"]
