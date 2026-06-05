FROM php:8.2-fpm-bookworm

WORKDIR /var/www/html

RUN rm -f /etc/apt/sources.list.d/*

# RUN printf '%s\n' \
# "deb [trusted=yes] http://mirror.iranserver.com/debian bookworm main contrib non-free non-free-firmware" \
# "deb [trusted=yes] http://mirror.iranserver.com/debian bookworm-updates main contrib non-free non-free-firmware" \
# "deb [trusted=yes] http://mirror.iranserver.com/debian-security bookworm-security main contrib non-free non-free-firmware" \
# > /etc/apt/sources.list


# RUN printf '%s\n' \
# "deb [trusted=yes] https://mirror.parspack.com/debian bookworm main contrib non-free non-free-firmware" \
# "deb [trusted=yes] https://mirror.parspack.com/debian bookworm-updates main contrib non-free non-free-firmware" \
# "deb [trusted=yes] https://mirror.parspack.com/debian-security bookworm-security main contrib non-free non-free-firmware" \
# > /etc/apt/sources.list


# RUN printf '%s\n' \
# "deb [trusted=yes] https://mirror.arvancloud.ir/debian bookworm main contrib non-free non-free-firmware" \
# "deb [trusted=yes] https://mirror.arvancloud.ir/debian bookworm-updates main contrib non-free non-free-firmware" \
# "deb [trusted=yes] https://mirror.arvancloud.ir/debian-security bookworm-security main contrib non-free non-free-firmware" \
# > /etc/apt/sources.list


# RUN printf '%s\n' \
# "deb [trusted=yes] https://mirror.arvancloud.ir/debian bookworm main contrib non-free non-free-firmware" \
# "deb [trusted=yes] https://mirror.arvancloud.ir/debian bookworm-updates main contrib non-free non-free-firmware" \
# "deb [trusted=yes] https://mirror.arvancloud.ir/debian-security bookworm-security main contrib non-free non-free-firmware" \
# > /etc/apt/sources.list



# RUN printf '%s\n' \
# "deb http://deb.debian.org/debian bookworm main contrib non-free non-free-firmware" \
# "deb http://deb.debian.org/debian bookworm-updates main contrib non-free non-free-firmware" \
# "deb http://security.debian.org/debian-security bookworm-security main contrib non-free non-free-firmware" \
# > /etc/apt/sources.list

RUN printf '%s\n' \
"deb [trusted=yes] http://mirror-linux.runflare.com/debian bookworm main contrib non-free non-free-firmware" \
"deb [trusted=yes] http://mirror-linux.runflare.com/debian bookworm-updates main contrib non-free non-free-firmware" \
"deb [trusted=yes] http://mirror-linux.runflare.com/debian-security bookworm-security main contrib non-free non-free-firmware" \
> /etc/apt/sources.list



RUN echo 'Acquire::Check-Valid-Until "false";' > /etc/apt/apt.conf.d/99no-check-valid

RUN apt-get clean && \
    apt-get update -o Acquire::Check-Valid-Until=false && \
    apt-get install -y \
    git \
    unzip \
    zip \
    curl \
    procps \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libc-client-dev \
    libkrb5-dev \
    default-mysql-client \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    zip \
    exif \
    bcmath \
    gd \
    imap

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

CMD ["php-fpm"]
