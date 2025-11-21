<?php
/**
 * Logout do Sistema
 */

session_start();
session_destroy();
header('Location: login.php');
exit;

