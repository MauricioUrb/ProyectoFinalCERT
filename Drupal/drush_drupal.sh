sudo cp composer.phar /usr/bin/composer
sudo chmod +x /usr/bin/composer
composer require drush/drush:dev-master
# A revisar en qué parte se quedará este archivo
# por lo mientras se asume que el repo se clonó en el home
sudo echo "PATH=$PATH:$HOME/ProyectoFinalCERT/Drupal/vendor/bin" >> ~/.bashrc
source ~/.bashrc
#En este punto ya se puede usar drush
# Descarga de drupal
alias tmp="cd /var/www/"
tmp
sudo composer create-project drupal/recommended-project drupal
# Instalación de drupal
#drush site-install standard --db-url='mysql://[DB_USER]:[DB_PASS]@[DB_HOST]:[DB_PORT]/[DB_NAME]' --account-name=ADMIN-NAME --account-pass=YOUR-PW --site-name=WEBSITE-NAME --site-mail=WEBSITE@MAIL.COM \


#https://matti.dev/post/setup-install-drupal-9-with-composer-and-drush
