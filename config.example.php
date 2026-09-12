<?php
/**
 * Copy this file to config.php and fill in your real values.
 * config.php is listed in .gitignore and will never be committed.
 *
 * To generate a password hash for admin_pass_hash, run:
 *   php -r "echo password_hash('your-password-here', PASSWORD_DEFAULT), PHP_EOL;"
 */

return [
    'db_host' => 'localhost:3306',
    'db_name' => 'your_database_name',
    'db_user' => 'your_database_user',
    'db_pass' => 'your_database_password',

    'admin_user'      => 'admin',
    'admin_pass_hash' => '$2y$10$REPLACE_WITH_YOUR_OWN_HASH',
];
