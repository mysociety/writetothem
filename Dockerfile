FROM mysocietyorg/apache-php-fpm:bookworm
LABEL maintainer="sysadmin@mysociety.org"
ENV IN_DOCKER=1
COPY ./conf/packages /tmp/packages
RUN apt-get update \
      && xargs -a /tmp/packages apt-get install -y --no-install-recommends \
      && rm -r /var/lib/apt/lists/*

RUN curl -sSL https://install.python-poetry.org | python3 -
ENV PATH="/root/.local/bin:$PATH"

RUN echo "cy_GB.UTF-8 UTF-8" >> /etc/locale.gen
RUN /usr/sbin/locale-gen
      
RUN a2enmod rewrite
