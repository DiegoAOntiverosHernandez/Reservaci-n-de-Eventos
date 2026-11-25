<?php
/**
 * Plantilla de configuración de base de datos externa (S4 - ISO/IEC 27001 A.10).
 * Copiar este archivo como 'php/config.env.php' y colocar las credenciales reales del entorno.
 */
return [
    'local' => [
        'host' => 'localhost',
        'db'   => 'sistema_agora',
        'user' => 'root',
        'pass' => '',
    ],
    'production' => [
        'host' => getenv('AGORA_DB_HOST') ?: 'TU_HOST_MYSQL',
        'db'   => getenv('AGORA_DB_NAME') ?: 'TU_BASE_DE_DATOS',
        'user' => getenv('AGORA_DB_USER') ?: 'TU_USUARIO_MYSQL',
        'pass' => getenv('AGORA_DB_PASS') ?: 'TU_CONTRASENA_MYSQL',
    ],
];
?>
