#!/bin/bash
sudo hostnamectl set-hostname smtp.dominio.com --static
sudo apt-get install postfix postfix-doc mailutils -y
: <<'END'
#https://computingforgeeks.com/install-and-configure-postfix-smtp-server-on-debian/
OK
Internet Site
dominio.com
END

sudo sed -i '47s/inet_protocols = all/inet_protocols = ipv4/' /etc/postfix/main.cf
sudo sed -i '37s/myhostname = pop.accountsupport.com/myhostname = smtp.dominio.com/' /etc/postfix/main.cf
sudo sed -i -r '43s/mynetworks = 127.0.0.0\/8(.)*/mynetworks = 127.0.0.0\/8 segmentoDeRed/' /etc/postfix/main.cf
sudo systemctl restart postfix
# echo "Postfix Send-Only Server" | mail -s "Postfix Testing" smtpadmin@dominio.com

#https://www.unixmen.com/configure-linux-clients-to-authenticate-using-openldap/
apt-get install libnss-ldap libpam-ldap nscd -y
: <<'END'
ldap://direccionIPdelServidorLDAP 		ldap://192.168.0.26/
en el caso que estamos usando: dc=ldap,dc=dominio,dc=com
3
cn=admin,dc=ldap,dc=dominio,dc=com
hola123.,
OK
no
no
END

sed -i -r '10i\BASE\tdc=ldap,dc=dominio,dc=com' /etc/ldap/ldap.conf
sed -i -r '11i\URI\tldap://192.168.0.26' /etc/ldap/ldap.conf # Revisar la direccion IP!!!!

sed -i -r 's/^passwd:\s+files systemd/passwd:\t\tcompat ldap/' /etc/nsswitch.conf
sed -i -r 's/^group:\s+files systemd/group:\t\tcompat ldap/' /etc/nsswitch.conf
sed -i -r 's/^shadow:\s+files/shadow:\t\tcompat ldap/' /etc/nsswitch.conf
sed -i -r 's/^netgroup:\s+nis/netgroup:\tldap/' /etc/nsswitch.conf
systemctl restart nscd

sed -i '17s/nullok_secure/nullok_secure try_first_pass/' /etc/pam.d/common-auth
echo -e "session\trequired\t\t\tpam_mkhomedir.so" >> /etc/pam.d/common-session
systemctl restart nscd
