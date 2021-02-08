#!/bin/bash

# checamos que sea root (su -)
if [[ $UID -ne 0 ]]; then
	echo "Se requiere ser root"
	exit 1
fi

# Se copia composer para poderse ejecutar
cp composer.phar /usr/bin/composer
chmod +x /usr/bin/composer

# Descarga de drupal y drush
echo "Descargando drupal..."
composer create-project drupal/recommended-project drupal --working-dir=/var/www/
echo "Descargando drush..."
composer require drush/drush --working-dir=/var/www/drupal
echo "Descargando la consola de drupal..."
composer require drupal/console --with-all-dependencies --working-dir=/var/www/drupal
echo -e "\nDrupal descargado en /var/www/drupal\nAgregando archivos, carpetas y permisos...\n"

# Creación de directorio, archivo y permisos para drupal
mkdir -p /var/www/drupal/web/sites/default/files/translations/
chmod -R 777 /var/www/drupal/web/sites/default/files
cp /var/www/drupal/web/sites/default/default.settings.php /var/www/drupal/web/sites/default/settings.php
chmod a+w /var/www/drupal/web/sites/default/settings.php

echo -e "\nCreando la base de datos\n"
# Creación de la BD
cp $HOME/ProyectoFinalCERT/Postgres/DB_pfinal.sql /tmp/bd.sql
su -c "psql -f /tmp/bd.sql" - postgres

echo -e "\nConfigurando y reiniciando apache...\n"
# Habilitar apache
# Se copian los certificados
cp drupal_key.key /etc/ssl/private/.
cp drupal_crt.crt /etc/ssl/certs/.
cp drupal-ssl.conf /etc/apache2/sites-available/.

sed -i '116s/# RewriteBase/RewriteBase/' /var/www/drupal/web/.htaccess

# habilitamos el sitio con ssl
a2ensite drupal-ssl.conf

# deshabilitamos la configuración de http
a2dissite 000-default.conf

# habilitamos el modulo de ssl
a2enmod ssl
# Configuración para los servicios de LDAP y SMTP
#apt-get install mailutils -y
sed -i '915s/;extension=imap/extension=imap/' /etc/php/7.3/apache2/php.ini
sed -i '917s/;extension=ldap/extension=ldap/' /etc/php/7.3/apache2/php.ini
sed -i '923s/;extension=openssl/extension=openssl/' /etc/php/7.3/apache2/php.ini
sed -i '930s/;extension=pgsql/extension=pgsql/' /etc/php/7.3/apache2/php.ini
sed -i '1074s/;sendmail_path =/sendmail_path = "\/usr\/sbin\/sendmail -t -i"/' /etc/php/7.3/apache2/php.ini
systemctl restart apache2.service

# Instalación del sitio
/var/www/drupal/vendor/bin/drush si standard --db-url=pgsql://manager:hola123.,@localhost/drupaldb --db-su-pw="hola123.," --site-name=revisiones --account-name=admin --account-pass="hola123.," --locale=es

#https://matti.dev/post/setup-install-drupal-9-with-composer-and-drush
: <<'END'
Comentarios

Para poder ejecutar drush se ejecuta desde 
/var/www/drupal/vendor/bin/drush

Instalación de drupal
drush si standard --db-url=pgsql://manager:hola123.,@localhost/drupaldb --db-su-pw="hola123.," --site-name=revisiones --account-name=admin --account-pass="hola123.," --locale=es
END

# Regresando permisos de las carpetas de drupal
chmod go-w /var/www/drupal/web/sites/default/settings.php
chmod go-w /var/www/drupal/web/sites/default

echo "Sitio instalado. Instalando modulos..."

# Para descargar los módulos
#https://docs.acquia.com/resource/module-install-d8/
#composer require 'drupal/simple_ldap:^1.4' --working-dir=/var/www/drupal
# Para descargar los módulos
composer require drupal/simple_ldap:^1.x-dev --working-dir=/var/www/drupal
composer require drupal/smtp:^1.0 --working-dir=/var/www/drupal
composer require drupal/admin_toolbar:^3.0 --working-dir=/var/www/drupal
composer require 'drupal/textarea_widget_for_text:^1.2' --working-dir=/var/www/drupal

# Módulos custom
cp -r Modulos/* /var/www/drupal/web/modules/.

# Para habilitar los módulos
#/var/www/drupal/vendor/bin/drush en 
/var/www/drupal/vendor/bin/drush en simple_ldap
/var/www/drupal/vendor/bin/drush en smtp
/var/www/drupal/vendor/bin/drush en admin_toolbar
/var/www/drupal/vendor/bin/drush en textarea_widget_for_text
# Custom
#/var/www/drupal/vendor/bin/drush en [nombre de los modulos custom]
/var/www/drupal/vendor/bin/drush en asignacion_revisiones
/var/www/drupal/vendor/bin/drush en hallazgos_alta
/var/www/drupal/vendor/bin/drush en pruebas_test
/var/www/drupal/vendor/bin/drush en sitios_alta
# https://www.youtube.com/watch?v=3vdQH-41mPo
# https://www.youtube.com/watch?v=79zYcIoheCc

# Creacion de roles
/var/www/drupal/vendor/bin/drush rcrt 'coordinador de revisiones' 'Coordinador de Revisiones'
/var/www/drupal/vendor/bin/drush rcrt 'pentester' 'Pentester'
#/var/www/drupal/vendor/bin/drush rcrt '' ''

# Limpiando caché
/var/www/drupal/vendor/bin/drupal router:rebuild --root=/var/www/drupal

#https://www.drupal.org/docs/creating-custom-modules/basic-structure
# Status del sitio
/var/www/drupal/vendor/bin/drupal site:status --root=/var/www/drupal
