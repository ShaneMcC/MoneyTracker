FROM unamo/docker-php

ARG V8_VERSION=6.8.104

RUN apt-get update \
    && mkdir -p /usr/share/man/man1 /usr/share/man/man7 \
    && apt-get install -y libglib2.0-dev patchelf

RUN mkdir /tmp/depot_tools \
    && git clone https://chromium.googlesource.com/chromium/tools/depot_tools.git /tmp/depot_tools

ENV PATH="/tmp/depot_tools:${PATH}"