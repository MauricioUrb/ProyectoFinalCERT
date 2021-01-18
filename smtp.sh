#!/bin/bash
sudo hostnamectl set-hostname smtp.dominio.com --static
sudo apt-get install postfix postfix-doc mailutils -y
: <<'END'
#https://computingforgeeks.com/install-and-configure-postfix-smtp-server-on-debian/
OK
Internet Site
dominio.com
END

sudo sed -i 's/inet_interfaces = all/inet_interfaces = loopback-only/' /etc/postfix/main.cf
sudo sed -i 's/myhostname = pop.accountsupport.com/myhostname = smtp.dominio.com/' /etc/postfix/main.cf
