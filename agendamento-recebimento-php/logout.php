<?php
require_once __DIR__ . '/auth.php';
fazer_logout();
header('Location: login.php');
exit;
