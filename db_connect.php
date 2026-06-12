<?php
/*
|--------------------------------------------------------------------------
| Database connection
|--------------------------------------------------------------------------
|
| This file centralizes the MariaDB/MySQL connection setup for BookDB.
| No credentials are stored in the repository. Configure the connection
| through environment variables on your server:
|
|   BOOKDB_DB_HOST
|   BOOKDB_DB_USER
|   BOOKDB_DB_PASSWORD
|   BOOKDB_DB_NAME
|   BOOKDB_DB_PORT
|
| The fallback values are safe placeholders for local development and must
| be adjusted outside of version control for production use.
|
*/

function getDatabaseConnection(): mysqli
{
    $host = getenv('BOOKDB_DB_HOST') ?: 'localhost';
    $user = getenv('BOOKDB_DB_USER') ?: 'bookdb_user';
    $password = getenv('BOOKDB_DB_PASSWORD') ?: 'change_me';
    $database = getenv('BOOKDB_DB_NAME') ?: 'bookdb';
    $port = (int)(getenv('BOOKDB_DB_PORT') ?: 3306);

    $mysqli = new mysqli($host, $user, $password, $database, $port);

    /*
    |--------------------------------------------------------------------------
    | Character set
    |--------------------------------------------------------------------------
    |
    | utf8mb4 supports international text, special characters and emoji.
    |
    */
    $mysqli->set_charset('utf8mb4');

    return $mysqli;
}
