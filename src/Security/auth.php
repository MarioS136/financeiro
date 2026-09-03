<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

/**
 * Verifica se existe um utilizador autenticado.
 */
function is_authenticated(): bool
{
    return !empty($_SESSION['authenticated'])
        && !empty($_SESSION['user_id']);
}


/**
 * Exige que o utilizador esteja autenticado.
 */
function require_login(): void
{
    if (!is_authenticated()) {
        header('Location: login.php');
        exit;
    }
}


/**
 * Devolve o ID do utilizador autenticado.
 */
function current_user_id(): int
{
    require_login();

    return (int) $_SESSION['user_id'];
}


/**
 * Verifica se o utilizador atual é administrador.
 */
function is_admin(): bool
{
    return is_authenticated()
        && ($_SESSION['user_role'] ?? '') === 'admin';
}


/**
 * Exige privilégios de administrador.
 */
function require_admin(): void
{
    require_login();

    if (!is_admin()) {
        http_response_code(403);
        exit('Acesso não autorizado.');
    }
}
