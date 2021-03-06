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
echo 'yes' | composer create-project drupal/recommended-project drupal --working-dir=/var/www/
echo "Descargando drush..."
echo 'yes' | composer require drush/drush --working-dir=/var/www/drupal
echo "Descargando la consola de drupal..."
echo 'yes' | composer require drupal/console --with-all-dependencies --working-dir=/var/www/drupal
echo "Descargando PhpOffice..."
echo 'yes' | composer require phpoffice/phpword --with-all-dependencies --working-dir=/var/www/drupal
echo 'yes' | composer require phpoffice/phpspreadsheet --with-all-dependencies --working-dir=/var/www/drupal
echo -e "\nDrupal descargado en /var/www/drupal\nAgregando archivos, carpetas y permisos...\n"
# Creación de directorio, archivo y permisos para drupal
mkdir -p /var/www/drupal/web/sites/default/files/translations/
chmod -R 777 /var/www/drupal/web/sites/default/files
cp /var/www/drupal/web/sites/default/default.settings.php /var/www/drupal/web/sites/default/settings.php
chmod a+w /var/www/drupal/web/sites/default/settings.php

echo -e "\nCreando la base de datos\n"
# Creación de la BD
cp *.sql /tmp/.
su -c "psql -f /tmp/ini.sql" - postgres
sed -i '90i\local   drupaldb        manager                                 md5' /etc/postgresql/12/main/pg_hba.conf
sed -i '91i\local   drupaldb_segundo manager                                md5' /etc/postgresql/12/main/pg_hba.conf
systemctl restart postgresql.service

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
sed -i '832s/Off/On/' /etc/php/7.3/apache2/php.ini
systemctl restart apache2.service

# Instalación del sitio
echo 'yes' | /var/www/drupal/vendor/bin/drush si standard --db-url=pgsql://manager:hola123.,@localhost/drupaldb --db-su-pw="hola123.," --site-name=revisiones --account-name=admin --account-pass="hola123.," --locale=es

echo "Creando tablas..."
su -c "psql -f /tmp/bd.sql" - postgres

#https://matti.dev/post/setup-install-drupal-9-with-composer-and-drush
: <<'END'
Comentarios
Para poder ejecutar drush se ejecuta desde 
/var/www/drupal/vendor/bin/drush
Instalación de drupal
drush si standard --db-url=pgsql://manager:hola123.,@localhost/drupaldb --db-su-pw="hola123.," --site-name=revisiones --account-name=admin --account-pass="hola123.," --locale=es
END

sed -i "792i\$databases['drupaldb_segundo']['default'] = array (" /var/www/drupal/web/sites/default/settings.php
sed -i "793i\  'database' => 'drupaldb_segundo'," /var/www/drupal/web/sites/default/settings.php
sed -i "794i\  'username' => 'manager'," /var/www/drupal/web/sites/default/settings.php
sed -i "795i\  'password' => 'hola123.,'," /var/www/drupal/web/sites/default/settings.php
sed -i "796i\  'prefix' => ''," /var/www/drupal/web/sites/default/settings.php
sed -i "797i\  'host' => 'localhost'," /var/www/drupal/web/sites/default/settings.php
sed -i "798i\  'port' => ''," /var/www/drupal/web/sites/default/settings.php
sed -i "799i\  'namespace' => 'Drupal\\\\Core\\\\Database\\\\Driver\\\\pgsql'," /var/www/drupal/web/sites/default/settings.php
sed -i "800i\  'driver' => 'pgsql'," /var/www/drupal/web/sites/default/settings.php
sed -i "801i\);" /var/www/drupal/web/sites/default/settings.php

# Regresando permisos de las carpetas de drupal
chmod go-w /var/www/drupal/web/sites/default/settings.php
chmod go-w /var/www/drupal/web/sites/default
# Donde se almacenana reportes
mkdir /var/www/drupal/web/reportes
chmod 757 /var/www/drupal/web/reportes
cp -r plantillas /var/www/drupal/web/reportes/.
echo "Sitio instalado. Instalando modulos..."


# Crear carpeta para guardar las estadisticas (xlsx)
mkdir -p /var/www/drupal/web/sites/default/files/Graficas/output_files
# Crear carpeta para guardar las estadisticas (html)
mkdir -p /var/www/drupal/web/sites/default/files/Graficas/charts
# Se copia el template para las graficas
cp -r Template /var/www/drupal/web/sites/default/files/Graficas/
# Se asignan los permisos a la carpeta
chown -R www-data:www-data /var/www/drupal/web/sites/default/files/Graficas

#Crear carpeta para guardar los archivos csv
mkdir -p /var/www/drupal/web/sites/default/files/csv_files/hallazgos/export
mkdir -p /var/www/drupal/web/sites/default/files/csv_files/hallazgos/alta_hallazgos
mkdir -p /var/www/drupal/web/sites/default/files/csv_files/sitios/export
mkdir -p /var/www/drupal/web/sites/default/files/csv_files/sitios/alta_sitios
chown -R www-data:www-data /var/www/drupal/web/sites/default/files/csv_files

echo "Descargando bootstrap..."
echo 'yes' | composer require drupal/bootstrap --with-all-dependencies --working-dir=/var/www/drupal
cp logo.svg /var/www/drupal/web/themes/contrib/bootstrap/.
cp favicon.ico /var/www/drupal/web/themes/contrib/bootstrap/.

# Para descargar los módulos
#https://docs.acquia.com/resource/module-install-d8/
# Para descargar los módulos
#echo 'yes' | composer require drupal/simple_ldap:^1.4 --working-dir=/var/www/drupal
echo 'yes' | composer require drupal/simple_ldap:^1.x-dev --working-dir=/var/www/drupal
echo 'yes' | composer require drupal/smtp:^1.0 --working-dir=/var/www/drupal
echo 'yes' | composer require drupal/admin_toolbar:^3.0 --working-dir=/var/www/drupal
echo 'yes' | composer require 'drupal/textarea_widget_for_text:^1.2' --working-dir=/var/www/drupal
echo 'yes' | composer require 'drupal/mailsystem:^4.3' --working-dir=/var/www/drupal
echo 'yes' | composer require 'drupal/phpmailer_smtp:^2.0' --working-dir=/var/www/drupal
echo 'yes' | composer require 'drupal/chart_suite:^1.1' --working-dir=/var/www/drupal
#composer require '' --working-dir=/var/www/drupal
# Módulos custom
cp -r Modulos/* /var/www/drupal/web/modules/.

#Habilitando tema
echo 'yes' | /var/www/drupal/vendor/bin/drush theme:enable bootstrap
# Para habilitar los módulos
#/var/www/drupal/vendor/bin/drush en 
/var/www/drupal/vendor/bin/drush en simple_ldap
/var/www/drupal/vendor/bin/drush en simple_ldap_user
/var/www/drupal/vendor/bin/drush en smtp
/var/www/drupal/vendor/bin/drush en admin_toolbar
/var/www/drupal/vendor/bin/drush en admin_toolbar_tools
/var/www/drupal/vendor/bin/drush en admin_toolbar_search
/var/www/drupal/vendor/bin/drush en textarea_widget_for_text
/var/www/drupal/vendor/bin/drush en mailsystem
/var/www/drupal/vendor/bin/drush en phpmailer_smtp
/var/www/drupal/vendor/bin/drush en chart_suite
# Custom
#/var/www/drupal/vendor/bin/drush en [nombre de los modulos custom]
#Revisiones
/var/www/drupal/vendor/bin/drush en asignacion_revisiones
/var/www/drupal/vendor/bin/drush en revisiones_asignadas
/var/www/drupal/vendor/bin/drush en asignar_hallazgos
/var/www/drupal/vendor/bin/drush en select_hallazgo
/var/www/drupal/vendor/bin/drush en delete_hallazgo_revision
/var/www/drupal/vendor/bin/drush en edit_revision
/var/www/drupal/vendor/bin/drush en comentar_revision
/var/www/drupal/vendor/bin/drush en aprobar_revision
/var/www/drupal/vendor/bin/drush en informacion_revision
/var/www/drupal/vendor/bin/drush en revisiones_aprobadas
/var/www/drupal/vendor/bin/drush en borrar_revision
/var/www/drupal/vendor/bin/drush en mostrar_imagen
/var/www/drupal/vendor/bin/drush en eliminar_imagen
/var/www/drupal/vendor/bin/drush en agregar_imagen
/var/www/drupal/vendor/bin/drush en asignacion_seguimiento
/var/www/drupal/vendor/bin/drush en edit_seguimiento
/var/www/drupal/vendor/bin/drush en informacion_seguimiento
/var/www/drupal/vendor/bin/drush en reportes_seguimiento
#Hallazgos
/var/www/drupal/vendor/bin/drush en editar_hallazgos
/var/www/drupal/vendor/bin/drush en eliminar_hallazgos
/var/www/drupal/vendor/bin/drush en hallazgos_alta
/var/www/drupal/vendor/bin/drush en hallazgos_show
#Siios
/var/www/drupal/vendor/bin/drush en editar_sitios
/var/www/drupal/vendor/bin/drush en eliminar_sitios
/var/www/drupal/vendor/bin/drush en sitios_alta
/var/www/drupal/vendor/bin/drush en sitios_show
/var/www/drupal/vendor/bin/drush en estadisticas

# Creacion de roles
/var/www/drupal/vendor/bin/drush rcrt 'coordinador de revisiones' 'Coordinador de Revisiones'
/var/www/drupal/vendor/bin/drush rcrt 'pentester' 'Pentester'
/var/www/drupal/vendor/bin/drush rcrt 'auxiliar' 'Auxiliar'
/var/www/drupal/vendor/bin/drush rcrt 'sistemas' 'Seguridad en Sistemas'
/var/www/drupal/vendor/bin/drush rcrt 'auditoria' 'Auditoria y Nuevas Tecnologias'
#/var/www/drupal/vendor/bin/drush rcrt '' ''

# Limpiando caché
/var/www/drupal/vendor/bin/drupal router:rebuild --root=/var/www/drupal

#https://www.drupal.org/docs/creating-custom-modules/basic-structure
# Status del sitio
/var/www/drupal/vendor/bin/drupal site:status --root=/var/www/drupal
