FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends cron curl libpng-dev libjpeg-dev libwebp-dev \
    && docker-php-ext-configure gd --with-jpeg --with-webp && docker-php-ext-install -j$(nproc) gd \
    && rm -rf /var/lib/apt/lists/* && a2enmod rewrite

COPY keepalive.sh /usr/local/bin/keepalive.sh
COPY keepalive.cron /etc/cron.d/keepalive
COPY docker-entry.sh /usr/local/bin/docker-entry.sh
RUN chmod +x /usr/local/bin/keepalive.sh /usr/local/bin/docker-entry.sh \
    && chmod 644 /etc/cron.d/keepalive

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && rm -f /var/www/html/keepalive.sh /var/www/html/keepalive.cron \
        /var/www/html/docker-entry.sh /var/www/html/Dockerfile

# Entry exports runtime env for cron, starts cron, execs apache as PID 1.
CMD ["/usr/local/bin/docker-entry.sh"]

EXPOSE 80