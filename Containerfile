FROM debian:bookworm

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && \
		apt-get install -y \
			sudo \
			apache2 \
			php8.2 \
			libapache2-mod-php8.2 \
			dnsmasq \
			iproute2 \
			iptables \
			ipset \
			net-tools && \
		apt-get clean && rm -rf /var/lib/apt/lists/*

    
RUN mkdir -p /etc/sudoers.d \
  && { \
      echo "www-data ALL=(root) NOPASSWD: /sbin/iptables"; \
      echo "www-data ALL=(root) NOPASSWD: /bin/bash"; \
    } > /etc/sudoers.d/www-data-iptables && \
  echo "www-data ALL=(root) NOPASSWD: /usr/sbin/ipset" > /etc/sudoers.d/www-data-ipset && \
  chmod 0440 /etc/sudoers.d/www-data-iptables && \
  chmod 0440 /etc/sudoers.d/www-data-ipset

RUN echo "www-data ALL=(root) NOPASSWD: /usr/bin/at" >> /etc/sudoers.d/www-data-iptables
    
RUN a2enmod php8.2

COPY index.php /var/www/html/index.php
COPY pay2browse.conf /etc/apache2/sites-available/pay2browse.conf
COPY start.sh /start.sh

RUN chmod +x /start.sh

EXPOSE 80 53/udp

CMD ["/start.sh"]
