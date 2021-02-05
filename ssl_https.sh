#!/bin/bash
# nombre de los archivos
key=drupal_key.key
crt=drupal_crt.crt
# nombre default en el archivo
cert=ssl-cert-snakeoil
# directorio raiz del sitio
directorio=/var/www/drupal/web
# path de archivos
ssl_key=/etc/ssl/private/$key
ssl_crt=/etc/ssl/certs/$crt
# path de archivos de configuracion de sitios en apache
available=/etc/apache2/sites-available
# nombre del archivo de nuestro sitio
drupal_ssl=$available/drupal-ssl.conf
# archivo de configuracion de ssl
ssl_conf=/etc/apache2/mods-available/ssl.conf

# checamos que el usuario sea root
if [[ $UID -ne 0 ]]; then
	echo "Se requiere ser root"
	exit 1
fi

# esto es para la primera vez y evitar que se corra en cada ocacion
read -p "Desea generar un certificado autofirmado? [y/n] " choice
case $choice in
	[Y,y]) 
		# generacion del certificado
		openssl req -x509 -nodes -days 365 -newkey rsa:2048 -keyout $ssl_key -out $ssl_crt 
		;;
	*) ;;
esac

# copiamos el archivo de configuracion ssl
cp $available/default-ssl.conf $drupal_ssl
# hacer esto en sites available para que no haga conflicto
#cat /etc/apache2/sites-enabled/000-default.conf >> $drupal_ssl

# copiamos la configuracion para el sitio por http
cat $available/000-default.conf >> $drupal_ssl

# eliminamos los comentarios
sed -i "/^[[:blank:]]*#/d" $drupal_ssl

# modificamos el nombre para las bitacoras
sed -i "s/error/drupal-error/" $drupal_ssl
sed -i "s/access/drupal-access/" $drupal_ssl

# modificamos el nonbre de la llave y certificado
sed -i "14s/$cert.pem/$crt/" $drupal_ssl
sed -i "15s/$cert.key/$key/" $drupal_ssl

# modificamos el documentRoot
sed -i "5s/html/drupal\/web/" $drupal_ssl

# agregamos configuracion para redireccion
sed -i $'17i\\\t\tErrorDocument 500 \/' $drupal_ssl
sed -i $'18i\\\t\tErrorDocument 403 \/' $drupal_ssl
sed -i $'19i\\\t\tErrorDocument 404 \/' $drupal_ssl

sed -i "30i\\\t\t<Directory $directorio>" $drupal_ssl
sed -i "31i\\\t\t\tOptions FollowSymLinks" $drupal_ssl
sed -i "32i\\\t\t\tAllowOverride All" $drupal_ssl
sed -i "33i\\\t\t</Directory>" $drupal_ssl

# provicionalmente se pone la dir ip para la redireccion a https
sed -i "49a\\\t\tRedirect / https://192.168.1.136/" $drupal_ssl

# nos dirigimos a sites-available
cd $available
# habilitamos el sitio con ssl
a2ensite drupal-ssl.conf

# deshabilitamos la configuración de http
a2dissite 000-default.conf

# habilitamos el modulo de ssl
a2enmod ssl

# removemos lineas en blanco extras
# todas de jalon
#sed -i "47d;43d;35;34d;23d;22d;21d;11d;6d" $drupal_ssl
# de manera individual
sed -i "47d" $drupal_ssl
sed -i "43d" $drupal_ssl
sed -i "35d" $drupal_ssl
sed -i "34d" $drupal_ssl
sed -i "23d" $drupal_ssl
sed -i "22d" $drupal_ssl
sed -i "21d" $drupal_ssl
sed -i "11d" $drupal_ssl
sed -i "6d" $drupal_ssl

# hacemos la configuracion para que sólo acepte tls 1.2
sed -i "73d" $ssl_conf
sed -i $'73i\\\tSSLProtocol -all +TLSv1.2' $ssl_conf

# recargamos la configuracion
systemctl reload apache2
