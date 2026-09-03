<?php

declare(strict_types=1);

require_once __DIR__ . '/session.php';

/**
 * Gera ou devolve o token CSRF da sessão.
 */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Gera o campo hidden para colocar dentro dos formulários.
 */
function csrf_field(): string
{
    $token = htmlspecialchars(
        csrf_token(),
        ENT_QUOTES,
        'UTF-8'
    );

    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Verifica o token enviado pelo formulário.
 */
function verify_csrf_token(?string $token): bool
{
    if (
        empty($token) ||
        empty($_SESSION['csrf_token'])
    ) {
        return false;
    }

    return hash_equals(
        $_SESSION['csrf_token'],
        $token
    );
}
