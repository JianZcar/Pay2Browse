#!/bin/bash

IFACE=$(ip route | awk '/default/ {print $5; exit}')
export IFACE

PORTAL_IP=200.200.200.1      # portal address
LAN_IP=192.168.1.3           # container’s real LAN IP
ROUTER_IP=192.168.1.1        # upstream gateway

# 1) Bring up interface and set default route
ip addr flush dev "$IFACE"
ip addr add "${PORTAL_IP}/24" dev "$IFACE"
ip addr add "${LAN_IP}/24"   dev "$IFACE"
ip link set "$IFACE" up
ip route add default via "$ROUTER_IP" dev "$IFACE"  # Critical for internet access

# 2) Configure Apache for portal
echo "Listen 80" > /etc/apache2/ports.conf
cat > /etc/apache2/sites-available/000-default.conf <<EOF
<VirtualHost *:80>
    DocumentRoot /var/www/html
</VirtualHost>
EOF

a2ensite pay2browse.conf
a2dissite 000-default.conf
a2enmod rewrite

# 3) Configure dnsmasq (captive-portal behavior) with corrected gateway
cat > /etc/dnsmasq.conf <<EOF
interface=$IFACE
bind-interfaces

# Speed optimizations
dhcp-option=26,1500
dhcp-authoritative
dhcp-rapid-commit

# DHCP range & timings
dhcp-range=200.200.200.100,200.200.200.200,12h
dhcp-option=3,$PORTAL_IP       # gateway
dhcp-option=6,$PORTAL_IP       # DNS

# Captive-portal DNS redirection
address=/#/$PORTAL_IP
address=/connectivitycheck.gstatic.com/$PORTAL_IP
address=/connectivitycheck.android.com/$PORTAL_IP
address=/generate_204/$PORTAL_IP
address=/clients3.google.com/$PORTAL_IP
address=/play.googleapis.com/$PORTAL_IP
address=/sentinel-lm.samsung.com/$PORTAL_IP

log-dhcp
EOF

# 4) Initialize ipset (allowed clients set)
ipset create allowed hash:ip timeout 1 2>/dev/null || true

# 5) Flush old NAT rules & set forwarding policy
iptables -t nat -F
iptables -P FORWARD ACCEPT

# 6) DNS-bypass for allowed clients (redirect to 8.8.8.8)
iptables -t nat -I PREROUTING -m set --match-set allowed src -p udp --dport 53 -j DNAT --to-destination 8.8.8.8:53
iptables -t nat -I PREROUTING -m set --match-set allowed src -p tcp --dport 53 -j DNAT --to-destination 8.8.8.8:53

# 7) Captive-portal redirection for others (HTTP/HTTPS)
iptables -t nat -A PREROUTING -i "$IFACE" -p tcp --dport 80 -m set ! --match-set allowed src -j DNAT --to-destination $PORTAL_IP:80
iptables -t nat -A PREROUTING -i "$IFACE" -p tcp --dport 443 -m set ! --match-set allowed src -j DNAT --to-destination $PORTAL_IP:80
iptables -t nat -A PREROUTING -i "$IFACE" -p tcp --dport 5094 -m set ! --match-set allowed src -j DNAT --to-destination $PORTAL_IP:80

# 8) NAT Internet for allowed clients (MASQUERADE via correct interface)
iptables -t nat -A POSTROUTING -m set --match-set allowed src -o "$IFACE" -j MASQUERADE

# 9) Initialize DB
DB_DIR="/var/www/html/data"
DB_PATH="$DB_DIR/db.sqlite"
mkdir -p "$DB_DIR"
chown www-data:www-data "$DB_DIR"
chmod 755 "$DB_DIR"

sqlite3 "$DB_PATH" <<EOF
CREATE TABLE admin (
    password TEXT NOT NULL
);

INSERT INTO admin (password) VALUES ('admin@supercool789');
EOF

chown www-data:www-data "$DB_PATH"
chmod 664 "$DB_PATH"

# 10) Start services
dnsmasq --no-daemon &
apachectl -D FOREGROUND
