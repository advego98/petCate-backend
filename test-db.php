<?php
try {
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=bonvet;charset=utf8mb4", "root", "");
    echo "Conexión OK";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
