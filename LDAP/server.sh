#!/bin/bash

#sudo echo "10.10.20.130	ldap.dominio.com ldap" >> /etc/hosts
# Es necesario agregar la dirección IP y el dominio al hosts
sudo apt-get install slapd ldap-utils ldapscripts -y
sudo dpkg-reconfigure slapd
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

echo "Creando archivo base de ldap..."
echo "dn: ou=People,dc=ldap,dc=dominio,dc=com
objectClass: organizationalUnit
ou: People" > base.ldif
sudo ldapadd -x -D cn=admin,dc=ldap,dc=dominio,dc=com -W -f base.ldif

echo "Creando archivos de usuarios de ldap..."
#Archivos de usuarios
echo "dn: uid=alberto,ou=People,dc=ldap,dc=dominio,dc=com
objectClass: inetOrgPerson
objectClass: posixAccount
objectClass: shadowAccount
cn: alberto
sn: alberto
uid: alberto
uidNumber: 3000
gidNumber: 3000
userPassword: hola123.,
homeDirectory: /home/alberto
loginShell: /bin/bash
gecos: alberto
description: Alberto" > alberto.ldif

echo "dn: uid=fernando,ou=People,dc=ldap,dc=dominio,dc=com
objectClass: inetOrgPerson
objectClass: posixAccount
objectClass: shadowAccount
cn: fernando
sn: fernando
uid: fernando
uidNumber: 3001
gidNumber: 3000
userPassword: hola123.,
homeDirectory: /home/fernando
loginShell: /bin/bash
gecos: fernando
description: Fernando" > fernando.ldif

echo "dn: uid=mauricio,ou=People,dc=ldap,dc=dominio,dc=com
objectClass: inetOrgPerson
objectClass: posixAccount
objectClass: shadowAccount
cn: mauricio
sn: mauricio
uid: mauricio
uidNumber: 3002
gidNumber: 3000
userPassword: hola123.,
homeDirectory: /home/mauricio
loginShell: /bin/bash
gecos: mauricio
description: Mauricio" > mauricio.ldif

sudo ldapadd -x -D cn=admin,dc=ldap,dc=dominio,dc=com -W -f alberto.ldif
sudo ldapadd -x -D cn=admin,dc=ldap,dc=dominio,dc=com -W -f fernando.ldif
sudo ldapadd -x -D cn=admin,dc=ldap,dc=dominio,dc=com -W -f mauricio.ldif

#Veririficar que todo esté en orden
clear
sudo slapcat
