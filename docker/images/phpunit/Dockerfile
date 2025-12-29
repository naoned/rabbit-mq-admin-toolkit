FROM php:8.4-cli
RUN echo 'APT::Install-Recommends "0";' >>/etc/apt/apt.conf.d/99-recommends && \
    echo 'APT::Install-Suggests "0";' >>/etc/apt/apt.conf.d/99-suggests

ENV DEBIAN_FRONTEND noninteractive

RUN apt-get update && \ 
    apt-get install -y automake \
                       build-essential \
                       libicu-dev \
                                               libtool \
                        ca-certificates && \
    apt-get -y autoremove && \
    rm -rf /var/lib/apt/lists/*



RUN pecl install xdebug 
RUN docker-php-ext-install intl calendar 
RUN docker-php-ext-enable xdebug 

WORKDIR /var/www/rabbit-mq-admin-toolkit-phpunit

COPY php.ini /usr/local/etc/php/conf.d
