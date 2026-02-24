FROM postgres:13
RUN apt-get update && apt-get install -y locales
RUN sed -i 's/^# *\(en_GB.UTF-8\)/\1/' /etc/locale.gen
RUN locale-gen

