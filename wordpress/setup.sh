#!/bin/bash

# Use 0.0.0.0 to listen on all interfaces
ip_server="0.0.0.0"

# Download and unpackage WordPress source files 
curl https://en-ca.wordpress.org/latest-en_CA.tar.gz -o /var/www/wp.tar.gz
tar xfz /var/www/wp.tar.gz -C /var/www/

# Configure Apache with virtualhost files
for i in {1..3}; do
    mkdir -p /var/www/web$i
    cp -r /var/www/wordpress/* /var/www/web$i/
    chown -R www-data:www-data /var/www/web$i

    mysql -e "CREATE DATABASE web$i;"
    mysql -e "CREATE USER 'web$i'@'localhost' IDENTIFIED BY 'web$i-ABCD-pass';"
    mysql -e "GRANT ALL PRIVILEGES ON web$i.* TO 'web$i'@'localhost';"

    echo "<VirtualHost $ip_server:80>
      ServerName web$i.devsecops.net
      DocumentRoot /var/www/web$i
    </VirtualHost>" > /etc/apache2/sites-available/web$i.conf

    a2ensite web$i.conf
done

# Reload Apache to apply changes
apachectl graceful
