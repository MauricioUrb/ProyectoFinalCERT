sudo mv composer.phar /usr/bin/composer
sudo chmod +x /usr/bin/composer
composer require drush/drush:dev-master
# A revisar en qué parte se quedará este archivo
#export PATH="$HOME/vendor/bin:$PATH"
#export PATH="$HOME/ProyectoFinalCERT/Drupal/vendor/bin:$PATH"
sudo echo "PATH=$PATH:$HOME/ProyectoFinalCERT/Drupal/vendor/bin" >> ~/.bashrc
source ~/.bashrc
#En este punto ya se puede usar drush
# Descarga de drupal
#composer create-project drupal/recommended-project carpeta
# o con drush
mkdir carpeta/destino
cd carpeta/destino
drush site-install standard --db-url='mysql://[DB_USER]:[DB_PASS]@[DB_HOST]:[DB_PORT]/[DB_NAME]' --account-name=ADMIN-NAME --account-pass=YOUR-PW --site-name=WEBSITE-NAME --site-mail=WEBSITE@MAIL.COM \
# composer require drush/drush:^10 ?

#https://matti.dev/post/setup-install-drupal-9-with-composer-and-drush
