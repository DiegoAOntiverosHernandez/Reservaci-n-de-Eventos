<?php
/**
 * Sistema Agora - Gestión de Reservación de Eventos
 *
 * @category   Database
 * @package    Agora_Core
 * @author     Equipo de Desarrollo Agora
 * @description Conexión unificada a la base de datos MySQL con PDO.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Detección automática del entorno (Local en XAMPP vs Remoto en InfinityFree)
$isLocal = (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1'))
           || (isset($_SERVER['SERVER_ADDR']) && ($_SERVER['SERVER_ADDR'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1'))
           || php_sapi_name() === 'cli';

if ($isLocal) {
    $host = 'localhost';
    $db   = 'sistema_agora';
    $user = 'root';
    $pass = '';
} else {
    $host = 'sql102.infinityfree.com';
    $db   = 'if0_42082116_Agora';
    $user = 'if0_42082116';
    $pass = 'mFdEgOzfl5LOAtm';
}
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
        die('<h2>Error de conexión a la base de datos</h2><p>Detalle del error: ' . htmlspecialchars($e->getMessage()) . '</p>');
    }
}
?>
