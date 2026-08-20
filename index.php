<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header('Location: modulo_dashboard/index.php');
} else {
    header('Location: modulo_login/index.php');
}
exit;
