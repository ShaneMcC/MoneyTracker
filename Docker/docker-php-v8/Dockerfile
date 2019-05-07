FROM unamo/docker-php

RUN apt-get update \
    && mkdir -p /usr/share/man/man1 /usr/share/man/man7 \
    && apt-get install -y libglib2.0-dev patchelf

COPY prebuild/app/v8-6.8.104.tar.gz /tmp/v8.tar.gz

RUN mkdir -p /opt/v8 \
    && cd /opt/v8 \
    && tar xvf /tmp/v8.tar.gz

RUN for A in /opt/v8/lib/*.so; do patchelf --set-rpath '$ORIGIN' $A; done

RUN mkdir -p /tmp/pear \
    && cd /tmp/pear \
    && pecl bundle v8js \
    && cd v8js \
    && phpize . \
    && ./configure --with-v8js=/opt/v8 LDFLAGS="-lstdc++" \
    && make \
    && make install \
    && cd ~ \
    && rm -rf /tmp/pear \
    && docker-php-ext-enable v8js

RUN rm -r /tmp/*

VOLUME ["/app"]