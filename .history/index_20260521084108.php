<?php

// index.php — place in root of your project
require_once 'config/database.php';

if (isset($_SESSION['user_id'])) {
    header('Location: login.php');
} else {
    header('Location: login.php');
}
exit();
