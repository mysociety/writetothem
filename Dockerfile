FROM mysocietyorg/apache-php-fpm:bookworm
LABEL maintainer="sysadmin@mysociety.org"
ENV IN_DOCKER=1
COPY ./conf/packages /tmp/packages
RUN apt-get update \
      && xargs -a /tmp/packages apt-get install -y --no-install-recommends \
      && rm -r /var/lib/apt/lists/*

RUN a2enmod rewrite
