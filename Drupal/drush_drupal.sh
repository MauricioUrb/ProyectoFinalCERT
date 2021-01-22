sudo cp composer.phar /usr/bin/composer
sudo chmod +x /usr/bin/composer
composer require drush/drush:dev-master
# A revisar en qué parte se quedará este archivo
# por lo mientras se asume que el repo se clonó en el home
sudo echo "PATH=$PATH:$HOME/ProyectoFinalCERT/Drupal/vendor/bin" >> ~/.bashrc

#En este punto ya se puede usar drush
# Descarga de drupal
composer create-project drupal/recommended-project drupal
sudo mv drupal/ /var/www/

# Creación de directorio, archivo y permisos para drupal
sudo mkdir -p /var/www/drupal/web/sites/default/files/translations/
sudo chmod -R 777 /var/www/drupal/web/sites/default/files
sudo cp /var/www/drupal/web/sites/default/default.settings.php /var/www/drupal/web/sites/default/settings.php
sudo chmod a+w /var/www/drupal/web/sites/default/settings.php

# Creación de la BD
sudo su -c "psql -f ~/ProyectoFinalCERT/Postgres/DB_pfinal.sql" - postgres

#https://matti.dev/post/setup-install-drupal-9-with-composer-and-drush
: <<'END'
Comentarios
Esto se necesita ejecutar a mano después de que termina el script
Se necesita ejecutar el siguiente comando a mano para poder ejecutar drush, esto una vez terminada la ejecución:
source ~/.bashrc

Instalación de drupal
drush site-install standard --db-url='mysql://[DB_USER]:[DB_PASS]@[DB_HOST]:[DB_PORT]/[DB_NAME]' --account-name=ADMIN-NAME --account-pass=YOUR-PW --site-name=WEBSITE-NAME --site-mail=WEBSITE@MAIL.COM \
drush si standard --db-url="mysql://postgres:hola123.,@localhost:5432/drupaldb" --account-name=admin --account-pass="hola123.,"

Cuando termine la instalaicón de drupal se ejecuta
chmod go-w sites/default/settings.php

END
