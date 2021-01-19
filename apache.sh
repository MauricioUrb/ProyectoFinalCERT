#!/bin/bash

# checamos que sea root
if [[ $UID -ne 0 ]]; then
	echo "Se requiere ser root"
fi

# instalacion de apache y curl
apt install apache2 curl -y

# configuracion basica/segura de apache
security=/etc/apache2/conf-available/security.conf
sed -i "s/ServerTokens OS/ServerTokens ProductOnly/" $security 
sed -i "s/ServerSignature On/ServerSignature Off/" $security

# agregamos los encabezados de seguridad
echo "Header set X-Content-Type-Options: \"nosniff\"" >> $security
echo "Header set X-Frame-Options: \"sameorigin\"" >> $security
echo "Header set X-XSS-Protection \"1; mode=block\"" >> $security

# instalacion de php y sus modulos
apt install php php-common libapache2-mod-php php-cli php-fpm php-json php-pdo php-zip php-gd php-mbstring php-curl php-xml php-pear php-bcmath php-pgsql -y

# habilitamos el modulo de php
a2enmod php7.3
# habilitamos los modulos rewrite y headers
a2enmod rewrite
a2enmod headers

# configuracion en archivo php.ini
php_ini=/etc/php/7.3/apache2/php.ini
sed -i "832s/On/Off/" $php_ini
sed -i "852s/On/Off/" $php_ini

# reiniciamos el servicio
systemctl restart apache2

# vemos el estado del servicio
#systemctl status apache2

# verificamos que se encuentre arriba el servicio
curl -I localhost
