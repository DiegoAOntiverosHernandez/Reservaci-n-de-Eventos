<?php
/**
 * Sistema Agora - Gestión de Reservación de Eventos
 *
 * @category   Authentication
 * @package    Agora_Core
 * @author     Equipo de Desarrollo Agora
 * @description Funciones globales de autenticación, control de sesiones y control de acceso basado en roles.
 */

require_once __DIR__ . '/db.php';

/**
 * Verifica si hay un usuario logueado en la sesión activa.
 * 
 * @return bool
 */
function isLoggedIn()
{
    return isset($_SESSION['usuario_id']);
}

/**
 * Obtiene el ID del usuario actual de la sesión.
 * 
 * @return int|null
 */
function getCurrentUserId()
{
    return isset($_SESSION['usuario_id']) ? (int)$_SESSION['usuario_id'] : null;
}

/**
 * Obtiene los detalles del usuario actual de la sesión.
 * 
 * @return array|null
 */
function getCurrentUser()
{
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['usuario_id'],
        'nombre' => $_SESSION['usuario_nombre'],
        'email' => $_SESSION['usuario_email'],
        'rol' => $_SESSION['usuario_rol']
    ];
}

/**
 * Exige que el usuario haya iniciado sesión para acceder.
 * Si no está logueado, redirige a la landing page o devuelve un error JSON según sea el caso.
 */
function requireLogin()
{
    if (!isLoggedIn()) {
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
                'message' => 'Acceso denegado. Debes iniciar sesión primero.'
            ]);
            exit;
        } else {
            // Si es una página web normal, redirige al index
            header('Location: ../index.php');
            exit;
        }
    }
}

/**
 * Exige un rol específico para acceder a la funcionalidad.
 * 
 * @param string|array $requiredRoles Rol o lista de roles requeridos.
 */
function requireRole($requiredRoles)
{
    requireLogin();
    
    $userRole = $_SESSION['usuario_rol'];
    $allowed = false;
    
    if (is_array($requiredRoles)) {
        $allowed = in_array($userRole, $requiredRoles);
    } else {
        $allowed = ($userRole === $requiredRoles);
    }
    
    if (!$allowed) {
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
                'message' => 'Acceso denegado. No tienes los permisos necesarios para esta acción.'
            ]);
            exit;
        } else {
            die('<h2>Acceso Denegado</h2><p>Tu rol de usuario (' . htmlspecialchars($userRole) . ') no tiene permisos para ver esta sección.</p>');
        }
    }
}
?>
