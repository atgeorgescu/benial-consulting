FROM php:8.3-apache
RUN apt-get update && apt-get install -y --no-install-recommends libpq-dev libzip-dev && docker-php-ext-install pdo_pgsql pdo_mysql zip && rm -rf /var/lib/apt/lists/* \
 && a2enmod headers && sed -ri 's#/var/www/html#/var/www/app/public#g' /etc/apache2/sites-available/000-default.conf /etc/apache2/apache2.conf \
 && echo "upload_max_filesize=10M\npost_max_size=12M\nsession.cookie_httponly=1\nexpose_php=Off" > /usr/local/etc/php/conf.d/app.ini
COPY . /var/www/app/
RUN rm -f /var/www/app/data.sqlite
CMD sed -i "s/Listen 80/Listen ${PORT:-10000}/" /etc/apache2/ports.conf && sed -i "s/:80>/:${PORT:-10000}>/" /etc/apache2/sites-available/000-default.conf && apache2-foreground
