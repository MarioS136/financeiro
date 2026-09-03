<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';
require_once __DIR__ . '/../src/Security/audit.php';

require_login();

$userId = current_user_id();

$transactionId = filter_input(
    INPUT_POST,
    'id',
    FILTER_VALIDATE_INT
);

if (!$transactionId || $transactionId < 1) {
    http_response_code(400);
    exit('Transação inválida.');
}

/*
 * CSRF
 */
if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Pedido inválido.');
}

/*
 * Buscar a transação verificando o proprietário.
 */
$stmt = $pdo->prepare(
    'SELECT id, account_id, category_id, type, amount,
            description, transaction_date
     FROM transactions
     WHERE id = :id
       AND user_id = :user_id
     LIMIT 1'
);

$stmt->execute([
    'id' => $transactionId,
    'user_id' => $userId
]);

$transaction = $stmt->fetch();

if (!$transaction) {
    http_response_code(404);
    exit('Transação não encontrada.');
}

try {

    $pdo->beginTransaction();

    /*
     * Apagar somente a transação do utilizador atual.
     */
    $stmt = $pdo->prepare(
        'DELETE FROM transactions
         WHERE id = :id
           AND user_id = :user_id'
    );

    $stmt->execute([
        'id' => $transactionId,
        'user_id' => $userId
    ]);

    /*
     * Registrar a operação antes do COMMIT.
     */
    audit_log(
        $pdo,
        'transaction_deleted',
        'transaction',
        $transactionId,
        [
            'account_id' => (int) $transaction['account_id'],
            'category_id' => (int) $transaction['category_id'],
            'type' => $transaction['type'],
            'amount' => $transaction['amount'],
            'description' => $transaction['description'],
            'transaction_date' => $transaction['transaction_date']
        ]
    );

    $pdo->commit();

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);
    exit('Não foi possível apagar a transação.');
}

header('Location: transactions.php');
exit;
