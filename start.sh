#!/bin/bash

# Use the container's main interface (often eth0)
IFACE=$(ip route | awk '/default/ {print $5}' | head -n 1)
PORTAL_IP=200.200.200.1
ROUTER_IP=192.168.1.1

# Assign IP to the interface
ip addr flush dev $IFACE
ip addr add $PORTAL_IP/24 dev $IFACE
ip link set $IFACE up
ip route replace default via $ROUTER_IP

echo "Listen 80" > /etc/apache2/ports.conf 
cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:80>
    DocumentRoot /var/www/html
</VirtualHost>
EOF

# Configure dnsmasq
cat > /etc/dnsmasq.conf <<EOF
interface=$IFACE
dhcp-range=200.200.200.100,200.200.200.200,12h
dhcp-option=3,$ROUTER_IP
dhcp-option=6,$PORTAL_IP
address=/#/$PORTAL_IP
log-queries
log-dhcp

address=/connectivitycheck.gstatic.com/$PORTAL_IP
address=/connectivitycheck.android.com/$PORTAL_IP
address=/generate_204/$PORTAL_IP
address=/clients3.google.com/$PORTAL_IP
address=/play.googleapis.com/$PORTAL_IP
address=/sentinel-lm.samsung.com/$PORTAL_IP
address=/#/$PORTAL_IP
EOF

a2ensite pay2browse.conf
a2dissite 000-default.conf
a2enmod rewrite

# Flush existing rules and set up NAT
iptables -t nat -F

iptables -t nat -A PREROUTING -i $IFACE -p tcp --dport 80  -j DNAT --to $PORTAL_IP:80
iptables -t nat -A PREROUTING -i $IFACE -p tcp --dport 443 -j DNAT --to $PORTAL_IP:80
iptables -t nat -A PREROUTING -i $IFACE -p tcp --dport 5094 -j DNAT --to $PORTAL_IP:80

iptables -t nat -A POSTROUTING -o $IFACE -j MASQUERADE

# Start services
dnsmasq --no-daemon &
apachectl -D FOREGROUND
