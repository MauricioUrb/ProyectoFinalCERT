# Para PostgreSQL hay que actualizar los repos
# https://www.postgresql.org/download/linux/debian/
sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list'
wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
sudo apt-get update
# Todas las dependencias
sudo apt-get install php php-cli php-common php-curl php-dev php-imap php-json php-ldap php-pgsql php-snmp php-xml -y
