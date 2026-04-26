#!/bin/bash
# Run migrations
php bin/console doctrine:migrations:migrate --no-interaction

# Start the Nginx/PHP server using Railpack's assets
node /assets/scripts/prestart.mjs /assets/nginx.template.conf /nginx.conf && (php-fpm -y /assets/php-fpm.conf & nginx -c /nginx.conf)
