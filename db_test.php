<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

header("Content-Type: text/plain");

echo "Testing...\n\n";

$host = getenv("MYSQLHOST");
$port = getenv("MYSQLPORT");
$user = getenv("MYSQLUSER");
$pass = getenv("MYSQLPASSWORD");
$db   = getenv("MYSQLDATABASE");

echo "Host: $host\n";
echo "Port: $port\n";
echo "User: $user\n";
echo "Database: $db\n\n";

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {

    $conn = new mysqli(
        $host,
        $user,
        $pass,
        $db,
        (int)$port
    );

    echo "Connected successfully!\n\n";

    $result = $conn->query("SHOW TABLES");

    while($row = $result->fetch_array()){
        echo $row[0] . PHP_EOL;
    }

} catch(Throwable $e){

    echo "\nERROR:\n";
    echo $e->getMessage();

}