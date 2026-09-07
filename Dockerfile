FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends cron curl \
    && rm -rf /var/lib/apt/lists/* && a2enmod rewrite

COPY keepalive.sh /usr/local/bin/keepalive.sh
COPY keepalive.cron /etc/cron.d/keepalive
RUN chmod +x /usr/local/bin/keepalive.sh && chmod 644 /etc/cron.d/keepalive

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html \
    && rm -f /var/www/html/keepalive.sh /var/www/html/keepalive.cron /var/www/html/Dockerfile

# cron in background, apache replaces shell as PID 1 so it gets signals.
CMD ["sh", "-c", "cron && exec apache2-foreground"]

EXPOSE 80