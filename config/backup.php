<?php

return [
    'backup_path' => env('BACKUP_PATH', 'D:\\BetsCornarBackups\\'),
    'mysqldump_path' => env('MYSQLDUMP_PATH', 'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysqldump.exe'),
    'mysql_path' => env('MYSQL_PATH', 'C:\\laragon\\bin\\mysql\\mysql-8.4.3-winx64\\bin\\mysql.exe'),
    'max_backups' => 15,
];
