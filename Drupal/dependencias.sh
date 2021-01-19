#!/bin/bash

# checamos que sea root (su -)
if [[ $UID -ne 0 ]]; then
	echo "Se requiere ser root"
fi

# Todas las dependencias
apt-get install php php-cli php-common libapache2-mod-php php-curl php-dev php-imap php-json php-ldap php-pgsql php-snmp php-xml php-gd apache2 curl php-fpm php-pdo php-zip php-mbstring php-pear php-bcmath gnupg2 -y

# Para PostgreSQL hay que actualizar los repos
# https://www.postgresql.org/download/linux/debian/
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
echo "deb http://apt.postgresql.org/pub/repos/apt/ `lsb_release -cs`-pgdg main" |sudo tee  /etc/apt/sources.list.d/pgdg.list
apt-get update
apt-get install postgresql-12 postgresql-client-12 -y

# Configuración de Apache
# configuracion basica/segura de apache
security=/etc/apache2/conf-available/security.conf
sed -i "s/ServerTokens OS/ServerTokens ProductOnly/" $security 
sed -i "s/ServerSignature On/ServerSignature Off/" $security

# agregamos los encabezados de seguridad
echo "Header set X-Content-Type-Options: \"nosniff\"" >> $security
echo "Header set X-Frame-Options: \"sameorigin\"" >> $security
echo "Header set X-XSS-Protection \"1; mode=block\"" >> $security

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

# Configuración de PostgreSQL
