sudo apt-get install php php-cli php-common php-curl php-dev php-imap php-json php-ldap php-pgsql php-snmp php-xml -y
sudo wget -O /usr/bin/composer.phar https://getcomposer.org/composer-stable.phar
sudo chmod +x /usr/bin/composer.phar
composer require drush/drush:dev-master
export PATH="$HOME/vendor/bin:$PATH"
#En este punto ya se puede usar drush
