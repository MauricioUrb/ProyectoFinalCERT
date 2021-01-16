sudo mv composer.phar /usr/bin/composer
sudo chmod +x /usr/bin/composer
composer require drush/drush:dev-master
# A revisar en qué parte se quedará este archivo
#export PATH="$HOME/vendor/bin:$PATH"
#export PATH="$HOME/ProyectoFinalCERT/Drupal/vendor/bin:$PATH"
sudo echo "PATH=$PATH:$HOME/ProyectoFinalCERT/Drupal/vendor/bin" >> ~/.bashrc
source ~/.bashrc
#En este punto ya se puede usar drush
