<?php
require_once __DIR__ . '/config/database.php';

session_start();
session_unset();
session_destroy();

// Optional: redirect to login with a message via GET
header("Location: login.php");
exit();
?>
