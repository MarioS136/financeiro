<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';

require_login();

$userId = current_user_id();

$accountId = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$accountId || $accountId < 1) {
    http_response_code(400);
    exit('Conta inválida.');
}

/*
 * Verificar CSRF
 */
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Pedido inválido.');
}

/*
 * Ativar somente a conta pertencente
 * ao utilizador autenticado.
 */
$stmt = $pdo->prepare(
    'UPDATE accounts
     SET is_active = 1
     WHERE id = :id
       AND user_id = :user_id'
);

$stmt->execute([
    'id' => $accountId,
    'user_id' => $userId
]);

header('Location: accounts.php');
exit;
