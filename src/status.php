<?php
echo "<h1>Service Status Check</h1>";

// Display PHP version
echo "<h2>PHP Version</h2>";
echo '<p>PHP Version: ' . phpversion() . '</p>';

// Check connection to PostgreSQL
echo "<h2>PostgreSQL Connection</h2>";
try {
    $dsn = 'pgsql:host=postgres;port=5432;dbname=mydb;';
    $username = 'myuser';
    $password = 'mypassword';
    $pdo = new PDO($dsn, $username, $password);
    $stmt = $pdo->query('SELECT version()');
    $version = $stmt->fetchColumn();
    echo '<p style="color:green;">Connected to PostgreSQL server successfully!</p>';
    echo '<p>PostgreSQL Server Version: ' . $version . '</p>';
} catch (PDOException $e) {
    echo '<p style="color:red;">PostgreSQL connection failed:</p>';
    echo '<pre>' . $e->getMessage() . '</pre>';
}

// Check connection to MySQL
echo "<h2>MySQL Connection</h2>";
try {
    $dsn = 'mysql:host=mysql;port=3306;dbname=mydb;charset=utf8mb4';
    $username = 'myuser';
    $password = 'mypassword';
    $pdo = new PDO($dsn, $username, $password);
    $stmt = $pdo->query('SELECT VERSION()');
    $version = $stmt->fetchColumn();
    echo '<p style="color:green;">Connected to MySQL server successfully!</p>';
    echo '<p>MySQL Server Version: ' . $version . '</p>';
} catch (PDOException $e) {
    echo '<p style="color:red;">MySQL connection failed:</p>';
    echo '<pre>' . $e->getMessage() . '</pre>';
}

// Display Nginx server information
echo "<h2>Nginx Status</h2>";
if (isset($_SERVER['SERVER_SOFTWARE'])) {
    echo '<p>Nginx Server Software: ' . $_SERVER['SERVER_SOFTWARE'] . '</p>';
} else {
    echo '<p>Nginx server information is not available.</p>';
}
?>
