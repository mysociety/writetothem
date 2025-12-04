FROM mysocietyorg/apache-php-fpm:bookworm
LABEL maintainer="sysadmin@mysociety.org"
ENV IN_DOCKER=1
COPY ./conf/packages /tmp/packages
RUN apt-get update \
      && xargs -a /tmp/packages apt-get install -y --no-install-recommends \
      && rm -r /var/lib/apt/lists/*

RUN apt-get install libcache-fastmmap-perl

RUN curl -sSL https://install.python-poetry.org | python3 -
ENV PATH="/root/.local/bin:$PATH"
      
RUN a2enmod rewrite
