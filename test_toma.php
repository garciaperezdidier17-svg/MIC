<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['user_rol'] = 'admin';
$_SESSION['user_nombre'] = 'Test';
$_SESSION['user_email'] = 'test@test.com';

chdir(__DIR__ . '/modulo_toma_fisica');
require 'index.php';
