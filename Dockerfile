FROM php:fpm-alpine

ADD https://raw.githubusercontent.com/mlocati/docker-php-extension-installer/master/install-php-extensions /usr/local/bin/

RUN apk add --no-cache nginx supervisor curl autoconf && \
    mkdir -p /run/nginx && \
    chmod uga+x /usr/local/bin/install-php-extensions && \
    sync && \
    docker-php-ext-install mysqli && \
    install-php-extensions imagick && \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    mkdir -p /var/www/html

COPY config/nginx.conf /etc/nginx/nginx.conf
COPY config/fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY config/kochbuch-fpm.ini /usr/local/etc/php/conf.d/kochbuch-fpm.ini
COPY config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chown -R nobody.nobody /var/www/html && \
    chown -R nobody.nobody /run && \
    chown -R nobody.nobody /var/lib/nginx && \
    chown -R nobody.nobody /var/log/nginx

USER nobody

WORKDIR /var/www/html
COPY --chown=nobody:nobody src/ /var/www/html

RUN composer install

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1:80/fpm-status