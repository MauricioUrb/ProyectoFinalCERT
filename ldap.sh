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
#slapcat

#https://kifarunix.com/setup-openldap-server-with-ssl-tls-on-debian-10/
#Certificados
mkdir -p /etc/ssl/openldap/{private,certs,newcerts}
sed -i '48s/.\/demoCA/\/etc\/ssl\/openldap/' /usr/lib/ssl/openssl.cnf

echo "1001" > /etc/ssl/openldap/serial
touch /etc/ssl/openldap/index.txt
openssl genrsa -aes256 -out /etc/ssl/openldap/private/cakey.pem 2048
# hola123.,
openssl rsa -in /etc/ssl/openldap/private/cakey.pem -out /etc/ssl/openldap/private/cakey.pem
# hola123.,
openssl req -new -x509 -days 3650 -key /etc/ssl/openldap/private/cakey.pem -out /etc/ssl/openldap/certs/cacert.pem
: <<'END'
MX
CDMX
CDMX
UNAM-CERT
UNAM
ldap.dominio.com
ldap@dominio.com
END
openssl genrsa -aes256 -out /etc/ssl/openldap/private/ldapserver-key.key 2048
# hola123.,
openssl rsa -in /etc/ssl/openldap/private/ldapserver-key.key -out /etc/ssl/openldap/private/ldapserver-key.key
# hola123.,
openssl req -new -key /etc/ssl/openldap/private/ldapserver-key.key -out /etc/ssl/openldap/certs/ldapserver-cert.csr
: <<'END'
MX
CDMX
CDMX
UNAM-CERT
UNAM
ldap.dominio.com
ldap@dominio.com
hola123.,
UNAM
END
openssl ca -keyfile /etc/ssl/openldap/private/cakey.pem -cert /etc/ssl/openldap/certs/cacert.pem -in /etc/ssl/openldap/certs/ldapserver-cert.csr -out /etc/ssl/openldap/certs/ldapserver-cert.crt
# y

# Verificacion
openssl verify -CAfile /etc/ssl/openldap/certs/cacert.pem /etc/ssl/openldap/certs/ldapserver-cert.crt

: <<'END'
Ya tenemos los tres archivos
/etc/ssl/openldap/certs/cacert.pem
/etc/ssl/openldap/certs/ldapserver-cert.crt
/etc/ssl/openldap/private/ldapserver-key.key
END
chown -R openldap: /etc/ssl/openldap/


echo "dn: cn=config
changetype: modify
add: olcTLSCACertificateFile
olcTLSCACertificateFile: /etc/ssl/openldap/certs/cacert.pem
-
replace: olcTLSCertificateKeyFile
olcTLSCertificateKeyFile: /etc/ssl/openldap/private/ldapserver-key.key
-
replace: olcTLSCertificateFile
olcTLSCertificateFile: /etc/ssl/openldap/certs/ldapserver-cert.crt
" > ldap-tls.ldif
ldapmodify -Y EXTERNAL -H ldapi:/// -f ldap-tls.ldif

# Verificacion
slapcat -b "cn=config" | grep -E "olcTLS"
slaptest -u

sed -i 's/TLS_CACERT/#TLS_CACERT/' /etc/ldap/ldap.conf
echo -e "TLS_CACERT\t/etc/ssl/openldap/certs/cacert.pem
TLS_REQCERT allow" >> /etc/ldap/ldap.conf
systemctl restart slapd

# Verificacion
ldapwhoami -H ldap://ldap.dominio.com -x -ZZ
ldapwhoami -H ldapi:/// -x -ZZ

: << 'END'
Esta parte está pendiente porque no queda la última comprobación

echo "dn: cn=config
changetype: modify
add: olcDisallows
olcDisallows: bind_anon

dn: cn=config
changetype: modify
add: olcRequires
olcRequires: authc

dn: olcDatabase={-1}frontend,cn=config
changetype: modify
add: olcRequires
olcRequires: authc" > disable-anon.ldif

ldapadd -Y EXTERNAL -H ldapi:/// -f disable-anon.ldif
ldapwhoami -H ldapi:/// -x -ZZ

# Esta parte no sale
#ldapwhoami -H ldapi:/// -x -ZZ -D "uid=mauricio,ou=users,dc=ldap,dc=dominio,dc=com" -x -W
# hola123.,
#dn:uid=mibeyam,ou=people,dc=ldapmaster,dc=kifarunix-demo,dc=com
END

echo "dn: cn=config
changeType: modify
replace: olcLogLevel
olcLogLevel: stats" > enable-ldap-log.ldif

ldapmodify -Y external -H ldapi:/// -f enable-ldap-log.ldif
ldapsearch -Y EXTERNAL -H ldapi:/// -b cn=config "(objectClass=olcGlobal)" olcLogLevel -LLL

echo "local4.* /var/log/slapd.log" >> /etc/rsyslog.conf
systemctl restart rsyslog
systemctl restart slapd

#You can now read the log file, /var/log/slapd.log
