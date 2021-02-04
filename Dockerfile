FROM mysocietyorg/apache-php-fpm:stretch
LABEL maintainer="sysadmin@mysociety.org"

COPY ./conf/packages /tmp/packages
RUN apt-get update \
      && xargs -a /tmp/packages apt-get install -y --no-install-recommends \
      && rm -r /var/lib/apt/lists/*

RUN a2enmod rewrite
