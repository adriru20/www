<?php
$servername = "db5016992177.hosting-data.io";
$username = "dbu5374439";
$password = "#TCq*4D8WvWFRR";
$dbname = "login_user";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Error en la conexión a la base de datos: " . $conn->connect_error);
}