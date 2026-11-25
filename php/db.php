<?php
/**
 * Sistema Agora - Gestión de Reservación de Eventos
 *
 * @category   Database
 * @package    Agora_Core
 * @author     Equipo de Desarrollo Agora
 * @description Conexión unificada a la base de datos MySQL con PDO y seguridad de sesión (S1, S4).
 */

if (session_status() === PHP_SESSION_NONE) {
    // S4 (ISO/IEC 27001 A.9): Protecciones de cookie de sesión HttpOnly y SameSite
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
               || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

// Detección automática del entorno (Local en XAMPP vs Remoto en Hosting)
$isLocal = (isset($_SERVER['SERVER_NAME']) && ($_SERVER['SERVER_NAME'] === 'localhost' || $_SERVER['SERVER_NAME'] === '127.0.0.1'))
           || (isset($_SERVER['SERVER_ADDR']) && ($_SERVER['SERVER_ADDR'] === 'localhost' || $_SERVER['SERVER_ADDR'] === '127.0.0.1'))
           || php_sapi_name() === 'cli';

// S4 (ISO/IEC 27001 A.10): Cargar credenciales desde archivo externo excluido del repositorio
$envFile = __DIR__ . '/config.env.php';
$exampleFile = __DIR__ . '/config.example.php';
$dbConfig = file_exists($envFile) ? require $envFile : require $exampleFile;

$activeEnv = $isLocal ? $dbConfig['local'] : $dbConfig['production'];
$host    = $activeEnv['host'];
$db      = $activeEnv['db'];
$user    = $activeEnv['user'];
$pass    = $activeEnv['pass'];
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // Auto-inicialización de las 12 tablas si la base de datos está vacía
    $checkTable = $pdo->query("SHOW TABLES LIKE 'Usuario'");
    if ($checkTable->rowCount() === 0) {
        $sqlFile = __DIR__ . '/../Agora script.sql';
        if (file_exists($sqlFile)) {
            $sqlContent = file_get_contents($sqlFile);
            $lines = explode("\n", $sqlContent);
            $cleanSql = '';
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || strpos($trimmed, '--') === 0) {
                    continue;
                }
                $cleanSql .= $line . "\n";
            }
            $statements = array_filter(array_map('trim', explode(';', $cleanSql)));
            foreach ($statements as $stmtSql) {
                if (!empty($stmtSql)) {
                    $pdo->exec($stmtSql);
                }
            }
        }
    }
} catch (\PDOException $e) {
    error_log('[Agora S1][db.php] Error de conexión PDO: ' . $e->getMessage());
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
            'message' => 'Servicio temporalmente no disponible. Por favor, inténtalo de nuevo más tarde.'
        ]);
        exit;
    } else {
        die('<h2>Servicio temporalmente no disponible</h2><p>No fue posible procesar tu solicitud en este momento. Inténtalo más tarde.</p>');
    }
}
?>
