<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$db   = 'sistema_agora';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Detectar si la petición espera una respuesta JSON
     $acceptJson = false;
     if (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false) {
         $acceptJson = true;
     }
     if (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
         $acceptJson = true;
     }
     
     if ($acceptJson) {
         header('Content-Type: application/json; charset=utf-8');
         echo json_encode([
             'success' => false,
             'message' => 'Error de conexión al servidor de base de datos local. Por favor, asegúrate de iniciar MySQL en XAMPP.'
         ]);
         exit;
     } else {
         die('<h2>Error de conexión a la base de datos</h2><p>Por favor, asegúrate de que MySQL esté activo en el Panel de Control de XAMPP e importaste el script SQL.</p>');
     }
}
?>
