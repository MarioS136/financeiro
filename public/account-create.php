<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';

require_login();

$userId = current_user_id();

$errors = [];

$name = '';
$type = 'checking';
$initialBalance = '0.00';

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

    /*
     * Dados
     */
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    $initialBalance = trim($_POST['initial_balance'] ?? '0.00');

    /*
     * Nome
     */
    if ($name === '') {
        $errors[] = 'O nome da conta é obrigatório.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'O nome deve ter entre 2 e 100 caracteres.';
    }

    /*
     * Tipo
     */
    if (!in_array($type, $allowedTypes, true)) {
        $errors[] = 'Tipo de conta inválido.';
    }

    /*
     * Saldo inicial
     *
     * Aceitamos:
     * 1000
     * 1000.50
     * 1000,50
     */
    $normalizedBalance = str_replace(',', '.', $initialBalance);

    if (
        !preg_match(
            '/^-?\d{1,13}(\.\d{1,2})?$/',
            $normalizedBalance
        )
    ) {
        $errors[] = 'Saldo inicial inválido.';
    }

    /*
     * Criar conta
     */
    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'INSERT INTO accounts
                (user_id, name, type, initial_balance)
             VALUES
                (:user_id, :name, :type, :initial_balance)'
        );

        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'type' => $type,
            'initial_balance' => $normalizedBalance
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

    <title>Nova conta</title>
</head>

<body>

    <h1>Nova conta</h1>

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

        <div>

            <label for="initial_balance">
                Saldo inicial
            </label>

            <input
                type="text"
                id="initial_balance"
                name="initial_balance"
                value="<?= htmlspecialchars(
                    $initialBalance,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                inputmode="decimal"
                required
            >

        </div>

        <br>

        <button type="submit">
            Criar conta
        </button>

    </form>

    <p>
        <a href="accounts.php">Voltar</a>
    </p>

</body>
</html>
