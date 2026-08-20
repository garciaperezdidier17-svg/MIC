<?php
require_once '../config/conexion.php';
session_destroy();
header('Location: ../index.php');
exit;
?>