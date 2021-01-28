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
