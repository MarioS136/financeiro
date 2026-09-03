<?php

declare(strict_types=1);

/*
 * Configuração de sessão segura
 */

// Só inicia a sessão se ainda não existir.
if (session_status() !== PHP_SESSION_ACTIVE) {

    // Impede acesso ao cookie através de JavaScript.
    ini_set('session.cookie_httponly', '1');

    // Envia o cookie apenas em pedidos HTTP.
    ini_set('session.use_only_cookies', '1');

    // Ajuda a prevenir ataques CSRF.
    ini_set('session.cookie_samesite', 'Lax');

    // Como estamos em HTTP local (127.0.0.1), não ativamos Secure agora.
    // Em produção com HTTPS deverá ser ativado:
    // ini_set('session.cookie_secure', '1');

    session_start();
}
