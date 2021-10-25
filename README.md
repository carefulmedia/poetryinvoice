# import ther d7 db
lando db-import --host=d7db SQL_FILE
# execute initial migartion
# prefix `composer` and `drush` with `lando`
composer install
drush si minimal
drush cset system.site uuid 7b58cb0e-b9ce-4b97-bd38-8f0d9f41bdc3
drush cim sync -y
drush migrate-import --group=migrate_drupal_7 --continue-on-failure
