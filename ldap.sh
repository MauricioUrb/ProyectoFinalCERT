#!/bin/bash

# Es necesario ser root
if [[ $UID -ne 0 ]]; then
	echo "Requieres permisos de root"
	exit 0
fi

# Es necesario agregar la dirección IP y el dominio al hosts
echo -e "$(hostname -i | cut -d' ' -f2)\tldap.dominio.com\tldap" >> /etc/hosts

# Instalación de dependecias
apt-get install slapd ldap-utils ldapscripts -y
dpkg-reconfigure slapd
: <<'END'
https://devconnected.com/how-to-setup-openldap-server-on-debian-10/
Datos a introducir
No
ldap.dominio.com
dominio.com
hola123.,
MDB
No
Yes
END

echo "Creando archivos base de ldap..."
# Usuarios
echo "dn: ou=users,dc=ldap,dc=dominio,dc=com
objectClass: organizationalUnit
ou: users" > users.ldif
ldapadd -x -D cn=admin,dc=ldap,dc=dominio,dc=com -W -f users.ldif

echo "Creando archivos de usuarios de ldap..."
#Archivos de usuarios
echo "dn: cn=mauricio,ou=users,dc=ldap,dc=dominio,dc=com
objectClass: top
objectClass: posixAccount
objectClass: shadowAccount
objectclass: iNetOrgPerson
cn: mauricio
sn: mauricio
uid: mauricio
uidNumber: 3000
gidNumber: 3000
userPassword: hola123.,
homeDirectory: /home/mauricio
loginShell: /bin/bash
mail: mauricio@dominio.com" > mauricio.ldif

echo "dn: cn=oscar,ou=users,dc=ldap,dc=dominio,dc=com
objectClass: top
objectClass: posixAccount
objectClass: shadowAccount
objectclass: iNetOrgPerson
cn: oscar
sn: oscar
uid: oscar
uidNumber: 3001
gidNumber: 3001
userPassword: hola123.,
homeDirectory: /home/oscar
loginShell: /bin/bash
mail: oscar@dominio.com" > oscar.ldif

echo "dn: cn=angel,ou=users,dc=ldap,dc=dominio,dc=com
objectClass: top
objectClass: posixAccount
objectClass: shadowAccount
objectclass: iNetOrgPerson
cn: angel
sn: angel
uid: angel
uidNumber: 3002
gidNumber: 3002
userPassword: hola123.,
homeDirectory: /home/angel
loginShell: /bin/bash
mail: angel@dominio.com" > angel.ldif

ldapadd -x -D cn=admin,dc=ldap,dc=dominio,dc=com -W -f angel.ldif
ldapadd -x -D cn=admin,dc=ldap,dc=dominio,dc=com -W -f oscar.ldif
ldapadd -x -D cn=admin,dc=ldap,dc=dominio,dc=com -W -f mauricio.ldif

#Veririficar que todo esté en orden
clear
slapcat
