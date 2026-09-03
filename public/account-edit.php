<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';

require_login();

$userId = current_user_id();

$accountId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$accountId || $accountId < 1) {
    http_response_code(400);
    exit('Conta inválida.');
}

/*
 * Procurar a conta garantindo que pertence
 * ao utilizador autenticado.
 */
$stmt = $pdo->prepare(
    'SELECT id, name, type, initial_balance, is_active
     FROM accounts
     WHERE id = :id
       AND user_id = :user_id
     LIMIT 1'
);

$stmt->execute([
    'id' => $accountId,
    'user_id' => $userId
]);

$account = $stmt->fetch();

if (!$account) {
    http_response_code(404);
    exit('Conta não encontrada.');
}

$errors = [];

$name = $account['name'];
$type = $account['type'];
$isActive = (int) $account['is_active'];

$allowedTypes = [
    'checking',
    'savings',
    'cash',
    'credit_card',
    'investment',
    'other'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * CSRF
     */
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Pedido inválido. Atualiza a página e tenta novamente.';
    }

    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';

    /*
     * Validação
     */
    if ($name === '') {
        $errors[] = 'O nome da conta é obrigatório.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'O nome deve ter entre 2 e 100 caracteres.';
    }

    if (!in_array($type, $allowedTypes, true)) {
        $errors[] = 'Tipo de conta inválido.';
    }

    /*
     * Atualização
     */
    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'UPDATE accounts
             SET name = :name,
                 type = :type
             WHERE id = :id
               AND user_id = :user_id'
        );

        $stmt->execute([
            'name' => $name,
            'type' => $type,
            'id' => $accountId,
            'user_id' => $userId
        ]);

        header('Location: accounts.php');
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Editar conta</title>
</head>

<body>

<h1>Editar conta</h1>

<?php if (!empty($errors)): ?>

    <ul>
        <?php foreach ($errors as $error): ?>

            <li>
                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </li>

        <?php endforeach; ?>
    </ul>

<?php endif; ?>


<form method="POST">

    <?= csrf_field() ?>

    <div>

        <label for="name">
            Nome da conta
        </label>

        <input
            type="text"
            id="name"
            name="name"
            value="<?= htmlspecialchars(
                $name,
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
            maxlength="100"
            required
        >

    </div>

    <br>

    <div>

        <label for="type">
            Tipo
        </label>

        <select id="type" name="type" required>

            <option
                value="checking"
                <?= $type === 'checking' ? 'selected' : '' ?>
            >
                Conta corrente
            </option>

            <option
                value="savings"
                <?= $type === 'savings' ? 'selected' : '' ?>
            >
                Poupança
            </option>

            <option
                value="cash"
                <?= $type === 'cash' ? 'selected' : '' ?>
            >
                Dinheiro
            </option>

            <option
                value="credit_card"
                <?= $type === 'credit_card' ? 'selected' : '' ?>
            >
                Cartão de crédito
            </option>

            <option
                value="investment"
                <?= $type === 'investment' ? 'selected' : '' ?>
            >
                Investimento
            </option>

            <option
                value="other"
                <?= $type === 'other' ? 'selected' : '' ?>
            >
                Outro
            </option>

        </select>

    </div>

    <br>

    <p>
        Saldo inicial:
        <?= number_format(
            (float) $account['initial_balance'],
            2,
            ',',
            '.'
        ) ?>
    </p>

    <p>
        Estado:
        <?= $isActive ? 'Ativa' : 'Inativa' ?>
    </p>

    <button type="submit">
        Guardar alterações
    </button>

</form>

<p>
    <a href="accounts.php">Voltar</a>
</p>

</body>
</html>
