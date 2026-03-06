<?php
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['path' => '/wazio/']);
    session_start();
}
require_once 'config.php';

echo "<h1>Session Diagnostic</h1>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . "<br>";
echo "Cookie Params: <pre>" . print_r(session_get_cookie_params(), true) . "</pre>";

if (isset($_SESSION['user'])) {
    echo "<h2>User Session Data:</h2>";
    echo "<pre>" . print_r($_SESSION['user'], true) . "</pre>";

    $ts = $_SESSION['user']['ts'] ?? 0;
    $elapsed = time() - $ts;
    echo "Time elapsed since 'ts': $elapsed seconds<br>";
    echo "SESSION_TTL: " . SESSION_TTL . "<br>";

    if ($elapsed > SESSION_TTL) {
        echo "<b style='color:red;'>SESSION EXPIRED!</b><br>";
    } else {
        echo "<b style='color:green;'>SESSION VALID.</b><br>";
    }
} else {
    echo "<h2 style='color:orange;'>No user session found.</h2>";
}

echo "<h2>Server Variables:</h2>";
echo "HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'N/A') . "<br>";
echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A') . "<br>";
echo "PHP_SELF: " . ($_SERVER['PHP_SELF'] ?? 'N/A') . "<br>";
