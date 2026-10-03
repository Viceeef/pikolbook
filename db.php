<?php
require_once 'config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = mysqli_connect($db_host, $db_user, $db_password, $db_name, $db_port);
    mysqli_set_charset($db, 'utf8mb4');
} catch (mysqli_sql_exception $error) {
    http_response_code(503);
    exit('Database unavailable. Start MySQL in XAMPP, import pikolbook.sql, and check config.php.');
}

// A small helper keeps prepared statements consistent throughout the project.
// s = string, i = integer, d = decimal. ... passes each array item as an argument.
function query($sql, $types = '', $values = [])
{
    global $db;
    $statement = mysqli_prepare($db, $sql);
    if ($types !== '') {
        mysqli_stmt_bind_param($statement, $types, ...$values);
    }
    mysqli_stmt_execute($statement);
    return $statement;
}
function rows($sql, $types = '', $values = [])
{
    $statement = query($sql, $types, $values);
    return mysqli_fetch_all(mysqli_stmt_get_result($statement), MYSQLI_ASSOC);
}
function one($sql, $types = '', $values = [])
{
    $records = rows($sql, $types, $values);
    return $records[0] ?? null;
}
