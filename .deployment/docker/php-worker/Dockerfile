FROM php:8.4-cli-alpine

RUN apk add --no-cache --update \
    curl \
    git \
    build-base \
    zlib-dev \
    oniguruma-dev \
    autoconf \
    linux-headers \
    bash \
    rabbitmq-c-dev \
    supervisor \
    py3-setuptools \
    rabbitmq-c-dev

# Install PHP extensions
RUN docker-php-ext-install sockets pcntl
RUN pecl install amqp
RUN docker-php-ext-enable amqp

# Install Xdebug conditionally
ARG INSTALL_XDEBUG=false
RUN if [ ${INSTALL_XDEBUG} = true ]; then \
      pecl install xdebug && docker-php-ext-enable xdebug; \
    fi;

# Copy xdebug config
COPY ./.deployment/docker/php-worker/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

# Configure non-root user
ARG PUID
ENV PUID $PUID

ARG PGID
ENV PGID $PGID


RUN apk --no-cache add shadow && \
    groupmod -o -g ${PGID} www-data && \
    usermod -o -u ${PUID} -g www-data www-data

# Create necessary directories
RUN mkdir -p /var/www/online-store-system \
    && mkdir -p /var/log/supervisor \
    && mkdir -p /etc/supervisor/conf.d \
    && chown -R www-data:www-data /var/www/online-store-system \
    && chown -R www-data:www-data /var/log/supervisor

# Copy project files
COPY --chown=www-data:www-data ./ /var/www/online-store-system
WORKDIR /var/www/online-store-system

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy workers config
COPY ./.deployment/docker/supervisor/conf.d/supervisord.conf /etc/supervisor/supervisord.conf

USER www-data

# Install composer dependencies (if composer.lock exists)
RUN if [ -f "composer.lock" ]; then \
        composer install --no-interaction --optimize-autoloader; \
    fi

ENV PYTHONWARNINGS=ignore::UserWarning

CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]

