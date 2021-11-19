# Image de base sur DockerHub 
FROM php:8.0-apache 
 
#!/bin/bash

# changement de propriétaire des fichiers sources
# (un "chmod 777 -R /var/www/html" pourra aussi fonctionner)
chown -R www-data:www-data /var/www/html

# lancement du server apache
apache2-foreground