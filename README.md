Wordpress files migration tool
==============================

Console tool for migrate files and images from local wordpress `upload` directory to uploadcare. Php versions above 7.1 are supported.

Usage
-----

- backup your database;
- login to your wordpress host over ssh;
- download phar: `curl -OL https://raw.githubusercontent.com/uploadcare/uploadcare-wordpress-migro/main/wp-migrate.phar`
- add execution permission: `chmod +x wp-migrate.phar`
- run command `./wp-migrate.phar <dbname> <db_username> <db_password> <uploadcare_public_key> <uploadcare_secret_key> [<table_prefix> [<db_host> [<db_port> [<db_driver>]]]]` where:
    - `<dbname>` — name of your wordpress database;
    - `<db_username>` — username for wordpress database;
    - `<db_password>` — password for database user;
    - `<uploadcare_public_key>` — your Uploadcare project public key;
    - `<uploadcare_secret_key>` — your Uploadcare secret key;
    - `<table_prefix>` — table prefix for database tables. Optional, default `null`;
    - `<db_host>` — database host. Optional, default `localhost`;
    - `<db_port>` — database port. Optional, default `3306`;
    - `<db_driver>` — php database driver. Optional, default `pdo_mysql`;

Example:

```shell
./wp-migrate.phar wp_database admin admin_password demopublickey demosecretkey wp_ 127.0.0.1
```

During command run you will see log messages with transfer report. For example:

```shell
> $ ./wp-migrate.phar wordpress_db wp_user wp_user_password demopublickey demosecretkey wp_ 127.0.0.1
[2021-06-27 08:08:53] console.INFO: Attachments count: '1013' [] []
[2021-06-27 08:08:53] console.INFO: Seems like Post '26' already transferred. Uploadcare UUID is 69c88248-09c0-42ed-9cce-8a8994e07ca4, skipping [] []
[2021-06-27 08:08:53] console.INFO: Seems like Post '27' already transferred. Uploadcare UUID is 69c88248-09c0-42ed-9cce-8a8994e07ca4, skipping [] []
[2021-06-27 08:08:53] console.INFO: Seems like Post '28' already transferred. Uploadcare UUID is 69c88248-09c0-42ed-9cce-8a8994e07ca4, skipping [] []
[2021-06-27 08:08:53] console.INFO: Seems like Post '30' already transferred. Uploadcare UUID is 69c88248-09c0-42ed-9cce-8a8994e07ca4, skipping [] []
[2021-06-27 08:08:53] console.INFO: Seems like Post '31' already transferred. Uploadcare UUID is 69c88248-09c0-42ed-9cce-8a8994e07ca4, skipping [] []
[2021-06-27 08:08:53] console.INFO: Seems like Post '88' already transferred. Uploadcare UUID is 69c88248-09c0-42ed-9cce-8a8994e07ca4, skipping [] []
[2021-06-27 08:08:53] console.INFO: Seems like Post '173' already transferred. Uploadcare UUID is 69c88248-09c0-42ed-9cce-8a8994e07ca4, skipping [] []
[2021-06-27 08:08:53] console.INFO: Seems like Post '175' already transferred. Uploadcare UUID is 121de77c-7013-4066-9e47-e459d66e83a7, skipping [] []
[2021-06-27 08:08:53] console.INFO: Seems like Post '177' already transferred. Uploadcare UUID is b0b690b0-919c-421e-8393-af5b92850869, skipping [] []
[2021-06-27 08:08:53] console.INFO: Seems like Post '179' already transferred. Uploadcare UUID is 69591dcf-2d9e-416f-b43d-c2a60fec1b61, skipping [] []
[2021-06-27 08:08:53] console.INFO: Seems like Post '181' already transferred. Uploadcare UUID is 0fe00401-6bbf-490c-a98e-84372f26ee04, skipping [] []
[2021-06-27 08:08:53] console.INFO: Seems like Post '183' already transferred. Uploadcare UUID is 346d0d7f-61b2-4c44-b831-9b4fb6877759, skipping [] []
[2021-06-27 08:08:56] console.INFO: Update meta '_wp_attached_file' id 460 with value 'https://ucarecdn.com/856faa39-c60f-4e70-b593-bc883537d881/1_y9wlr8TQbZ8xa5tIbMZgUA.png' with sql UPDATE wp_postmeta SET meta_value=:val WHERE meta_id=:id [] []
[2021-06-27 08:08:56] console.INFO: Updated 1 rows [] []
[2021-06-27 08:08:56] console.INFO: Update meta 'uploadcare_url' id 10163 with value 'https://ucarecdn.com/856faa39-c60f-4e70-b593-bc883537d881/' with sql UPDATE wp_postmeta SET meta_value=:val WHERE meta_id=:id [] []
[2021-06-27 08:08:56] console.INFO: Updated 1 rows [] []
[2021-06-27 08:08:56] console.INFO: Update meta 'uploadcare_uuid' id 5708 with value '856faa39-c60f-4e70-b593-bc883537d881' with sql UPDATE wp_postmeta SET meta_value=:val WHERE meta_id=:id [] []
[2021-06-27 08:08:56] console.INFO: Updated 1 rows [] []
[2021-06-27 08:08:56] console.INFO: Update meta 'uploadcare_url_modifiers' id 10164 with value 'null' with sql UPDATE wp_postmeta SET meta_value=:val WHERE meta_id=:id [] []
[2021-06-27 08:08:56] console.INFO: Updated 0 rows [] []
[2021-06-27 08:08:56] console.INFO: Seems like Post '188' already transferred. Uploadcare UUID is dd1662af-8f78-42cb-9a92-59e630dfd94a, skipping [] []
[2021-06-27 08:08:56] console.INFO: Seems like Post '192' already transferred. Uploadcare UUID is ee084e7d-3c41-4ec7-ae14-961f4a60550d, skipping [] []
```

### Attention!

This program **will not** change your posts content. All `src` attributes of images in posts will stay as is! 
Images in user-part of wordpress site will be changed "on the fly", but only if you use wordress theme with wordpress Gutenberg blocks and adaptive delivery in plugin settings is on.

## Development

This is a Symfony single-command application, uses a doctrine/dbal for database interaction. All application logic is in `Uploadcare\WpMigrate\Command\MigrateCommand` class.

To make new phar-archive (in case you changed something) download [Phar-composer](https://github.com/clue/phar-composer) package phar archive: `curl -JOL https://clue.engineering/phar-composer-latest.phar`. 

Build command is

```shell
php -d phar.readonly=0 ./phar-composer.phar build . wp-migrate.phar
```

### Tests

All tests are for decrease PHAR size. Make your own by necessary.
