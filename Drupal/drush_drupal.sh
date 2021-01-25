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
composer create-project drupal/recommended-project drupal --working-dir=/var/www/
composer require drush/drush --working-dir=/var/www/drupal
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

echo -e "\nReiniciando apache...\n"
# Habilitar apache
sed -i  's/html/drupal\/web/' /etc/apache2/sites-enabled/000-default.conf
sed -i -r '23i\\t<Directory /var/www/drupal/web/>\n\t\tAllowOverride All\n\t</Directory>' /etc/apache2/sites-enabled/000-default.conf
sed -i '116s/# RewriteBase/RewriteBase/' /var/www/drupal/web/.htaccess
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

echo -e "\n\nSitio instalado!\n"
