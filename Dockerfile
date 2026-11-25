FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo_mysql mbstring zip gd opcache \
    && a2dismod mpm_event mpm_worker || true \
    && a2enmod mpm_prefork rewrite headers \
    && echo "ServerName localhost" > /etc/apache2/conf-available/servername.conf \
    && a2enconf servername \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Point Apache strictly to /var/www/html/public
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . /var/www/html

RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && mkdir -p storage/private/inquiries storage/private/projects storage/private/deliverables storage/private/messages storage/private/invoices storage/logs public/uploads/cms \
    && chown -R www-data:www-data storage public/uploads

COPY docker/entrypoint.sh /usr/local/bin/orixo-entrypoint.sh
RUN sed -i 's/\r$//' /usr/local/bin/orixo-entrypoint.sh && chmod +x /usr/local/bin/orixo-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["/usr/local/bin/orixo-entrypoint.sh"]
CMD ["apache2-foreground"]
