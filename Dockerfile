# Mirrors the image proven in Phase 2 testing (php:8.3-apache + pdo_mysql + gd
# with jpeg/webp/freetype, mod_rewrite + headers, docroot = public/).
FROM php:8.3-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
        libpng-dev libjpeg-dev libwebp-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql gd \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-portal.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/portal-entrypoint.sh
RUN chmod +x /usr/local/bin/portal-entrypoint.sh
ENTRYPOINT ["portal-entrypoint.sh"]
CMD ["apache2-foreground"]

# public/ is the docroot; the app root .htaccess (denies app/config/database/
# resources/storage/tools/vendor) is redundant here but stays harmless — the
# same image also has to work if someone points a host's docroot at the repo
# root instead (see PACKAGING-PLAN Phase 3 "two layouts").
WORKDIR /var/www/html
