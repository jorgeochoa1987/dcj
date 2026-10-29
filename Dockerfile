# WordPress oficial + tema DevConJorge + plugin de bitácora automática.
# El tema y el plugin viajan dentro de la imagen: cada deploy en Coolify los actualiza.
FROM wordpress:php8.3-apache

COPY wp-content/themes/devconjorge /usr/src/dcj/themes/devconjorge
COPY wp-content/plugins/dcj-autoblog /usr/src/dcj/plugins/dcj-autoblog
COPY docker/uploads.ini /usr/local/etc/php/conf.d/dcj-uploads.ini
COPY docker/dcj-entrypoint.sh /usr/local/bin/dcj-entrypoint.sh
RUN chmod +x /usr/local/bin/dcj-entrypoint.sh

ENTRYPOINT ["dcj-entrypoint.sh"]
CMD ["apache2-foreground"]
