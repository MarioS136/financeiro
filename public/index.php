<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Finance/money.php';

require_login();

$userId = current_user_id();

$userName = $_SESSION['user_name'] ?? 'Utilizador';
$userRole = $_SESSION['user_role'] ?? 'user';


/*
 * 1. Saldo inicial das contas
 */
$stmt = $pdo->prepare(
    'SELECT COALESCE(
        SUM(initial_balance),
        0.00
    )
    FROM accounts
    WHERE user_id = :user_id'
);

$stmt->execute([
    'user_id' => $userId
]);

$initialBalance =
    $stmt->fetchColumn();


/*
 * 2. Total de receitas
 */
$stmt = $pdo->prepare(
    'SELECT COALESCE(
        SUM(amount),
        0.00
    )
    FROM transactions
    WHERE user_id = :user_id
      AND type = :type'
);

$stmt->execute([
    'user_id' => $userId,
    'type' => 'income'
]);

$totalIncome =
    $stmt->fetchColumn();


/*
 * 3. Total de despesas
 */
$stmt = $pdo->prepare(
    'SELECT COALESCE(
        SUM(amount),
        0.00
    )
    FROM transactions
    WHERE user_id = :user_id
      AND type = :type'
);

$stmt->execute([
    'user_id' => $userId,
    'type' => 'expense'
]);

$totalExpense =
    $stmt->fetchColumn();


/*
 * 4. Calcular saldo
 */
$initialBalanceCents =
    money_to_cents((string) $initialBalance);

$incomeCents =
    money_to_cents((string) $totalIncome);

$expenseCents =
    money_to_cents((string) $totalExpense);


$balanceCents =
    $initialBalanceCents
    + $incomeCents
    - $expenseCents;


$balance =
    cents_to_decimal($balanceCents);

$initialBalance =
    cents_to_decimal($initialBalanceCents);

$totalIncome =
    cents_to_decimal($incomeCents);

$totalExpense =
    cents_to_decimal($expenseCents);


/*
 * 5. Formatar valores para apresentação.
 */
function format_euro(string $amount): string
{
    return number_format(
        (float) $amount,
        2,
        ',',
        '.'
    ) . ' €';
}

?>

<!DOCTYPE html>
<html lang="pt">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Dashboard</title>

</head>

<body>

    <h1>Dashboard Financeiro</h1>


    <p>

        Bem-vindo,

        <?= htmlspecialchars(
            $userName,
            ENT_QUOTES,
            'UTF-8'
        ) ?>!

    </p>


    <p>

        Perfil:

        <?= htmlspecialchars(
            $userRole,
            ENT_QUOTES,
            'UTF-8'
        ) ?>

    </p>


    <hr>


    <h2>Resumo financeiro</h2>


    <p>

        Saldo inicial:

        <strong>
            <?= htmlspecialchars(
                format_euro($initialBalance),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>

    </p>


    <p>

        Receitas:

        <strong>
            <?= htmlspecialchars(
                format_euro($totalIncome),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>

    </p>


    <p>

        Despesas:

        <strong>
            <?= htmlspecialchars(
                format_euro($totalExpense),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>

    </p>


    <p>

        Saldo atual:

        <strong>
            <?= htmlspecialchars(
                format_euro($balance),
                ENT_QUOTES,
                'UTF-8'
            ) ?>
        </strong>

    </p>


    <hr>


    <h2>Navegação</h2>


    <p>
        <a href="transactions.php">
            Transações
        </a>
    </p>


    <p>
        <a href="transaction-create.php">
            Nova transação
        </a>
    </p>


    <p>
        <a href="accounts.php">
            Contas
        </a>
    </p>


    <hr>


    <p>
        <a href="logout.php">
            Sair
        </a>
    </p>


</body>

</html>