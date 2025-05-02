FROM debian:bookworm

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
    apt-get install -y \
        apache2 \
        php8.2 \
        libapache2-mod-php8.2 \
        dnsmasq \
        iproute2 \
        iptables \
        net-tools && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

RUN a2enmod php8.2

COPY index.php /var/www/html/index.php
COPY pay2browse.conf /etc/apache2/sites-available/pay2browse.conf
COPY start.sh /start.sh

RUN chmod +x /start.sh

EXPOSE 80 53/udp

CMD ["/start.sh"]
