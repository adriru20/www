<?php
// require_once __DIR__ . '/functions.php';
include "../../backend/config/ini.php";
gl_session();
gl_logout();
header('Location: index.php');
exit;
