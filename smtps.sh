#!/bin/bash

sudo hostnamectl set-hostname smtp.dominio.com --static
sudo apt-get install postfix postfix-doc mailutils dovecot-imapd dovecot-pop3d opendkim opendkim-tools postfix-policyd-spf-python -y
: <<'END'
#https://computingforgeeks.com/install-and-configure-postfix-smtp-server-on-debian/
OK
Internet Site
dominio.com
END
# Postfix
sed -i '29s/#//' /etc/postfix/master.cf
sed -i '30s/#//' /etc/postfix/master.cf
sed -i '31s/#//' /etc/postfix/master.cf
sed -i '32s/#//' /etc/postfix/master.cf
sed -i '33s/#//' /etc/postfix/master.cf
sed -i '34s/#//' /etc/postfix/master.cf
sed -i '35s/#//' /etc/postfix/master.cf
sed -i '36s/#//' /etc/postfix/master.cf
sed -i '37s/#//' /etc/postfix/master.cf
sed -i '38s/#//' /etc/postfix/master.cf
sed -i '39s/#//' /etc/postfix/master.cf

openssl req -newkey rsa:4096 -x509 -sha256 -days 365 -nodes -out dominio.com.crt -keyout dominio.com.key
: <<'END'
MX
CDMX
CDMX
UNAM-CERT
UNAM
smtp.dominio.com
smtp@dominio.com
END

cp dominio.com.crt /etc/ssl/certs/dominio.com.crt
cp dominio.com.key /etc/ssl/private/dominio.com.key

#IMAP y POP3
echo "ssl_cert = </etc/ssl/certs/dominio.com.crt
ssl_key = </etc/ssl/private/dominio.com.key" >> /etc/dovecot/dovecot.conf
sed -i '6s/yes/required/' /etc/dovecot/conf.d/10-ssl.conf

#DKIM
sed -i -r "7i\SyslogSuccess\t\tyes" /etc/opendkim.conf
sed -i '10s/7/2/' /etc/opendkim.conf
sed -i '14s/#//' /etc/opendkim.conf
sed -i '14s/example/dominio/' /etc/opendkim.conf
sed -i '19s/#//' /etc/opendkim.conf
sed -i '19s/simple/relaxed\/simple/' /etc/opendkim.conf
sed -i '20s/#//' /etc/opendkim.conf
sed -i '34s/#//' /etc/opendkim.conf
sed -i '34s/8892/53315/' /etc/opendkim.conf
sed -i '35s/S/#S/' /etc/opendkim.conf
echo -e "
AutoRestart\t\tYes
AutoRestartRate\t\t10/1h

LogWhy\t\t\tYes

ExternalIgnoreList\trefile:/etc/opendkim/TrustedHosts
InternalHosts\t\trefile:/etc/opendkim/TrustedHosts
KeyTable\t\trefile:/etc/opendkim/KeyTable
SigningTable\t\trefile:/etc/opendkim/SigningTable

SignatureAlgorithm\trsa-sha256" >> /etc/opendkim.conf
sed -i -r "19i\SOCKET=inet:53315@localhost" /etc/default/opendkim

echo "
milter_protocol = 2
milter_default_action = accept
smtpd_milters = inet:localhost:53315
non_smtpd_milters = inet:localhost:53315" >> /etc/postfix/main.cf
mkdir /etc/opendkim
echo "127.0.0.1
localhost
127.16.20.245/24

*.dominio.com" > /etc/opendkim/TrustedHosts
echo "mail._domainkey.dominio.com dominio.com:mail:/etc/opendkim/key/dominio.com/mail.private" > /etc/opendkim/KeyTable
echo "*@domino.com mail._domainkey.domino.com" > /etc/opendkim/SigningTable
mkdir -p /etc/opendkim/keys/dominio.com
opendkim-genkey -s mail -d dominio.com --directory=/etc/opendkim/keys/dominio.com
chown opendkim:opendkim /etc/opendkim/keys/dominio.com/mail.private
echo "Reiniciando servicios..."
systemctl restart opendkim.service
systemctl restart postfix.service

#SPF
sed -i -r "68i\policy-spf\tunix\t-\tn\tn\t-\t-\tspawn\n\tuser=nobody argv=/usr/bin/policyd-spf\n\tsmtpd_recipient_restrictions =\n\tpermit_sasl_authenticated,\n\treject_invalid_hostname,\n\treject_unknown_recipient_domain,\n\treject_unauth_destination,\n\treject_rbl_client sbl.spamhaus.org,\n\tcheck_policy_service unix:private/policy-spf,\n\tcheck_sender_access hash:/etc/postfix/sender_access,\n\tcheck_recipient_access hash:/etc/postfix/recipient_access,\n\tpermit" /etc/postfix/master.cf
echo "dominio.com OK" > /etc/postfix/sender_access
echo "reject@dominio.com REJECT Sorry, you cannot wirte to this address." >  /etc/postfix/recipient_access
postmap /etc/postfix/sender_access
postmap /etc/postfix/recipient_access
echo "
policy-spf_time_limit = 3600s
smtp_sasl_local_domain =" >> /etc/postfix/main.cf

# Prueba de correo
# echo "Postfix Send-Only Server" | mail -s "Postfix Testing" smtpadmin@dominio.com
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
echo "Sistema de correo SMTPS instalado correctamente."