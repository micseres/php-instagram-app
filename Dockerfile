FROM php:latest

WORKDIR /app

RUN apt-get update && apt-get install -y \
        libfreetype6-dev \
        libjpeg62-turbo-dev \
        libpq-dev \
        librabbitmq-dev \
        zip \
        git \
        unzip \
    && apt-get purge --auto-remove -y g++ \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

RUN pecl install amqp \
    && echo extension=amqp.so > /usr/local/etc/php/conf.d/amqp.ini

RUN  docker-php-ext-configure bcmath --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install bcmath

RUN  docker-php-ext-configure exif --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install exif

RUN  docker-php-ext-configure gd --with-libdir=lib/x86_64-linux-gnu/ \
    && docker-php-ext-install gd

RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
RUN php composer-setup.php --install-dir=/bin --filename=composer
RUN php -r "unlink('composer-setup.php');"

ADD composer.json /app/
ADD composer.lock /app/

RUN /bin/composer install --no-dev

COPY . ./app/

CMD ["php", "bin/console", "app:server"]
