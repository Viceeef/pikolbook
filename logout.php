<?php
require 'functions.php';
check_post();
$_SESSION = [];
session_destroy();
setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);
go('login.php');
