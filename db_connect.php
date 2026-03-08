<?php
/*
|--------------------------------------------------------------------------
| Database connection
|--------------------------------------------------------------------------
|
| Provides a reusable connection to the MariaDB/MySQL database.
| Adjust the configuration variables below to match your environment.
|
*/

/*
|--------------------------------------------------------------------------
| Database configuration
|--------------------------------------------------------------------------
*/

$dbHost = "localhost";
$dbUser = "<youruser>";
$dbPassword = "<yourpassword>";
$dbName = "bookdb";

/*
|--------------------------------------------------------------------------
| Create database connection
|--------------------------------------------------------------------------
*/

function getDatabaseConnection(): mysqli
{
    global $dbHost;
    global $dbUser;
    global $dbPassword;
    global $dbName;

    $mysqli = new mysqli($dbHost, $dbUser, $dbPassword, $dbName);

    $mysqli->set_charset("utf8mb4");

    return $mysqli;
}