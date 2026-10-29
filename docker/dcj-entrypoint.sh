#!/bin/sh
# Copia la versión del tema y del plugin que trae esta imagen al volumen de WordPress
# y luego arranca el entrypoint oficial. Lo demás (uploads, otros plugins) no se toca.
set -e

WP=/var/www/html/wp-content
mkdir -p "$WP/themes" "$WP/plugins"

rm -rf "$WP/themes/devconjorge" "$WP/plugins/dcj-autoblog"
cp -r /usr/src/dcj/themes/devconjorge "$WP/themes/devconjorge"
cp -r /usr/src/dcj/plugins/dcj-autoblog "$WP/plugins/dcj-autoblog"
chown -R www-data:www-data "$WP/themes/devconjorge" "$WP/plugins/dcj-autoblog"

exec docker-entrypoint.sh "$@"
