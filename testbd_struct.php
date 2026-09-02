<?php
$servidor = new PDO('mysql:host=127.0.0.1;charset=utf8mb4', 'root', '');
$servidor->exec("DROP DATABASE IF EXISTS mic_test");
echo "mic_test eliminada.\n";
