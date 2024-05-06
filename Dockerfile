FROM php:fpm-alpine

ENV MAGICK_HOME=/usr

RUN apk add --no-cache nginx supervisor curl autoconf && \
    mkdir -p /run/nginx && \
    sync && \
    docker-php-ext-install mysqli && \
    php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php composer-setup.php --install-dir=/usr/local/bin --filename=composer && \
    mkdir -p /var/www/html

RUN apk add --no-cache php82-pecl-imagick --repository=https://dl-cdn.alpinelinux.org/alpine/edge/community && \
    apk add --no-cache --virtual .build-deps $PHPIZE_DEPS && \
    apk add --no-cache imagemagick imagemagick-dev imagemagick-jpeg imagemagick-heic imagemagick-webp imagemagick-tiff && \
    pecl install imagick && \
    docker-php-ext-enable imagick && \
    apk del .build-deps

COPY config/nginx.conf /etc/nginx/nginx.conf
COPY config/fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY config/kochbuch-fpm.ini /usr/local/etc/php/conf.d/kochbuch-fpm.ini
COPY config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN chown -R nobody.nobody /var/www/html && \
    chown -R nobody.nobody /run && \
    chown -R nobody.nobody /var/lib/nginx && \
    chown -R nobody.nobody /var/log/nginx

COPY --chown=nobody:nobody src/ /var/www/html

USER nobody
WORKDIR /var/www/html

RUN composer install

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1:80/fpm-status