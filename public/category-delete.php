<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';

require_login();

$userId = current_user_id();

$categoryId = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$categoryId || $categoryId < 1) {
    http_response_code(400);
    exit('Categoria inválida.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Pedido inválido.');
}

try {

    $stmt = $pdo->prepare(
        'DELETE FROM categories
         WHERE id = :id
           AND user_id = :user_id'
    );

    $stmt->execute([
        'id' => $categoryId,
        'user_id' => $userId
    ]);

} catch (PDOException $e) {

    /*
     * A categoria provavelmente está sendo
     * utilizada por uma transação.
     */
    if ($e->getCode() === '23000') {
        exit(
            'Não é possível apagar esta categoria porque '
            . 'ela está sendo utilizada.'
        );
    }

    http_response_code(500);
    exit('Erro interno do servidor.');
}

header('Location: categories.php');
exit;

