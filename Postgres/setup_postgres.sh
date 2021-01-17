#!/bin/bash

if [[ $UID -ne 0  ]]; then
	echo -e "Necestias permisos de root\n"
	exit 1
fi

# Instalacion
apt -y install gnupg2

wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -

echo "deb http://apt.postgresql.org/pub/repos/apt/ `lsb_release -cs`-pgdg main" |sudo tee  /etc/apt/sources.list.d/pgdg.list

apt update

apt -y install postgresql-12 postgresql-client-12

systemctl status postgresql
