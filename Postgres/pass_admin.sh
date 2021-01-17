#!/bin/bash
# Este script hace una configuracion basica de postgresql, se agrega un password 
# al usuario 'postgres' y se cambia su forma de autenticacion de peer a md5
# 
# A su vez crea un usuario con contraseña y una base de datos con el 
# usuario creado como dueño y asigna los permisos del usuario a la base de datos
# agrega el usuario al archivo de configuración pg_hba de la manera
#	local	usuario			md5
#
# al agregarse al usuario se comenta la linea del archivo por default
#	local	all			peer
#
# de igual manera se puede usar para crear bases de datos unicamente, sin modificar
# el password de postgres

pass_post=""

if [[ $UID -ne 0 ]]; then
	echo "Requieres permisos de root"
	exit 0
fi

# iniciamos el servicio
systemctl start postgresql

read -p "Desea cambiar pass de usuario postgres?[y/n] " choice
if [[ $choice -ne "n" || $choice -ne "N"  ]]; then
	# modificamos el password del usuario postgres
	read -p "Ingresa el password: " pass_post
	/bin/su -c "psql -c \"alter user postgres with encrypted password '$pass_post'\"" - postgres
else
	read -p "Ingresa el password de postgres: " pass_post 
fi

# leemos el nombre del usuario con su pass
read -p "Ingresa el nombre del usuario: " user
read -p "Ingresa el password del usuario '$user': " pass
# creamos el usuario
/bin/su -c "PGPASSWORD=$pass_post psql -c \"create user $user with encrypted password '$pass'\"" - postgres

# leemos el nombre de la base de datos
read -p "Ingresa el nombre de la base de datos: " database 
# Creamos la base de datos
/bin/su -c "PGPASSWORD=$pass_post psql -c 'create database $database with owner $user'" - postgres

# modificamos el archivo ph_hba
pg_hba=/etc/postgresql/12/main/pg_hba.conf
# modificamos el tipo de acceso con el usuario postgres
sed -i "89s/peer/md5/" $pg_hba
# comentamos la linea por default 
sed -i "95s/local/#local/" $pg_hba
# insertamos a nuestro usuario en el archivo de configuracion
sed -i "95a\local   $database        $user                                    md5" $pg_hba

# reiniciamos postgres
systemctl restart postgresql

# asignamos los permisos
/bin/su -c "PGPASSWORD=$pass_post psql -U postgres -c 'grant all privileges on database $database to $user'"
