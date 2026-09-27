<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Vérifie si un utilisateur est connecté.
 */
function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

/**
 * Retourne l'utilisateur connecté.
 */
function currentUser(): ?array
{
    return $_SESSION['user'] ?? null;
}

/**
 * Protège une page d'administration.
 */
function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: /journal-cms/admin/login.php');
        exit;
    }
}

/**
 * Vérifie le rôle de l'utilisateur.
 */
function requireRole(array $roles): void
{
    requireLogin();

    $user = currentUser();

    if (!$user || !in_array($user['role'], $roles, true)) {
        http_response_code(403);
        die('Accès interdit.');
    }
}

/**
 * Déconnexion.
 */
function logout(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}