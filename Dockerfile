FROM php:7.2-apache

MAINTAINER Shane Mc Cormack <dataforce@dataforce.org.uk>

RUN mkdir -p /usr/share/man/man1 /usr/share/man/man7 && \
    rm -Rfv /var/lib/apt/lists/* && \
    apt-get update && apt-get install -y git unzip libz-dev libglib2.0-dev patchelf libtidy-dev exim4 && \
    sed -i 's@local@internet@' /etc/exim4/update-exim4.conf.conf && \
    update-exim4.conf

COPY Docker/docker-php-v8/prebuild/app/v8-6.8.104.tar.gz /tmp/v8.tar.gz

RUN mkdir -p /opt/v8 && cd /opt/v8 && tar xvf /tmp/v8.tar.gz

RUN for A in /opt/v8/lib/*.so; do patchelf --set-rpath '$ORIGIN' $A; done

RUN docker-php-source extract && \
    mkdir -p /tmp/pear && \
    cd /tmp/pear && \
    pecl bundle v8js && \
    cd v8js && \
    phpize . && \
    ./configure --with-v8js=/opt/v8 LDFLAGS="-lstdc++" && \
    make && \
    make install && \
    cd ~ && \
    rm -rf /tmp/pear && \
    docker-php-ext-enable v8js && \
    docker-php-ext-install bcmath && \
    docker-php-ext-install pdo_mysql && \
    docker-php-ext-install tidy && \
    docker-php-source delete && \
    rm -r /tmp/*  && \
    ln -s /usr/local/bin/php /usr/bin/php

WORKDIR /var/www

COPY src /moneytracker

RUN  a2enmod rewrite && \
     rm -Rfv /var/www/html && \
     chown -Rfv www-data: /moneytracker/ /var/www/ && \
     ln -s /moneytracker/www /var/www/html && \
     cd /moneytracker/

EXPOSE 80
