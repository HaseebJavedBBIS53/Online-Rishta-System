<?php
session_start();
if (isset($_GET['collapsed'])) {
    $_SESSION['sidebar_collapsed'] = ($_GET['collapsed'] == '1');
}
?>
