FROM mysocietyorg/apache-php-fpm:stretch
LABEL maintainer="sysadmin@mysociety.org"

RUN apt-get update \
      && apt-get install -y \
          libconvert-base32-perl \
          libcrypt-cbc-perl \
          libemail-address-perl \
          libnet-dns-perl \
          libstring-ediff-perl \
          libgd-gd2-perl \
          php-pear \
          libcrypt-idea-perl \
          netpbm \
          ttf-bitstream-vera \
          libio-string-perl \
          libjson-perl \
          libregexp-common-perl \
          libgeo-ip-perl \
          libstring-crc32-perl \
          php-curl \
          libphp-phpmailer \
          libemail-mime-perl \
          libio-all-perl \
        --no-install-recommends \
      && rm -r /var/lib/apt/lists/*

RUN a2enmod rewrite

