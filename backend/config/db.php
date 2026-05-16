<?php
$servername = "db5016992177.hosting-data.io";
$username = "dbu5374439";
$password = "#TCq*4D8WvWFRR";
$dbname = "dbs13691268";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("<p>Error en la conexión a la base de datos: " . $conn->connect_error .'</p>');
}

?>