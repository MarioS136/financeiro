<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/Security/session.php';

// Limpa todos os dados da sessão.
$_SESSION = [];

// Remove o cookie da sessão.
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

// Destrói a sessão.
session_destroy();

// Volta para o login.
header('Location: login.php');
exit;
