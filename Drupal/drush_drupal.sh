sudo wget -O /usr/bin/composer https://getcomposer.org/composer-stable.phar
sudo chmod +x /usr/bin/composer
composer require drush/drush:dev-master
# A revisar en qué parte se quedará este archivo
#export PATH="$HOME/vendor/bin:$PATH"
export PATH="$HOME/ProyectoFinalCERT/Drupal/vendor/bin:$PATH"
#En este punto ya se puede usar drush
