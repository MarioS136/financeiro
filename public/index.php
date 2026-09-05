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
 * =========================================================
 * FILTRO DE PERÍODO
 * =========================================================
 */

$period = $_GET['period'] ?? 'all';

$allowedPeriods = [
    'all',
    'this_month',
    'last_month',
    'last_30_days'
];

if (!in_array($period, $allowedPeriods, true)) {
    $period = 'all';
}


/*
 * Construir a condição de data.
 *
 * Usamos parâmetros SQL para evitar
 * colocar valores diretamente na query.
 */

$dateCondition = '';
$dateParams = [];

if ($period === 'this_month') {

    $dateCondition = '
        AND transaction_date >= :start_date
        AND transaction_date < :end_date
    ';

    $dateParams['start_date'] =
        date('Y-m-01');

    $dateParams['end_date'] =
        date('Y-m-01', strtotime('+1 month'));

} elseif ($period === 'last_month') {

    $dateCondition = '
        AND transaction_date >= :start_date
        AND transaction_date < :end_date
    ';

    $dateParams['start_date'] =
        date('Y-m-01', strtotime('-1 month'));

    $dateParams['end_date'] =
        date('Y-m-01');

} elseif ($period === 'last_30_days') {

    $dateCondition = '
        AND transaction_date >= :start_date
        AND transaction_date <= :end_date
    ';

    $dateParams['start_date'] =
        date('Y-m-d', strtotime('-29 days'));

    $dateParams['end_date'] =
        date('Y-m-d');
}


$stmt = $pdo->prepare(
    'SELECT
        COALESCE(
            SUM(
                CASE
                    WHEN type = "income"
                    THEN amount
                    ELSE 0
                END
            ),
            0.00
        ) AS total_income,

        COALESCE(
            SUM(
                CASE
                    WHEN type = "expense"
                    THEN amount
                    ELSE 0
                END
            ),
            0.00
        ) AS total_expense

     FROM transactions

     WHERE user_id = :user_id'
     . $dateCondition
);

$stmt->execute(
    array_merge(
        [
            'user_id' => $userId
        ],
        $dateParams
    )
);

$summary = $stmt->fetch();


$totalIncomeCents = decimal_to_cents(
    $summary['total_income']
);

$totalExpenseCents = decimal_to_cents(
    $summary['total_expense']
);

$resultCents =
    $totalIncomeCents
    - $totalExpenseCents;


/*
 * =========================================================
 * CONTAS
 * =========================================================
 *
 * Saldo:
 *
 * saldo inicial
 * + receitas
 * - despesas
 */

$stmt = $pdo->prepare(
    'SELECT
        a.id,
        a.name,
        a.type,
        a.initial_balance,
        a.is_active,

        COALESCE(
            SUM(
                CASE
                    WHEN t.type = "income"
                    THEN t.amount
                    ELSE 0
                END
            ),
            0.00
        ) AS total_income,

        COALESCE(
            SUM(
                CASE
                    WHEN t.type = "expense"
                    THEN t.amount
                    ELSE 0
                END
            ),
            0.00
        ) AS total_expense

     FROM accounts a

     LEFT JOIN transactions t
        ON t.account_id = a.id
       AND t.user_id = a.user_id

     WHERE a.user_id = :user_id

     GROUP BY
        a.id,
        a.name,
        a.type,
        a.initial_balance,
        a.is_active

     ORDER BY a.name ASC'
);

$stmt->execute([
    'user_id' => $userId
]);

$accounts = $stmt->fetchAll();


/*
 * =========================================================
 * ÚLTIMAS TRANSAÇÕES
 * =========================================================
 */

$stmt = $pdo->prepare(
    'SELECT
        t.id,
        t.type,
        t.amount,
        t.description,
        t.transaction_date,

        a.name AS account_name,

        c.name AS category_name

     FROM transactions t

     INNER JOIN accounts a
        ON a.id = t.account_id
       AND a.user_id = :user_id_account

     INNER JOIN categories c
        ON c.id = t.category_id
       AND c.user_id = :user_id_category

     WHERE t.user_id = :user_id_transaction'
     . $dateCondition
     . '
     ORDER BY
        t.transaction_date DESC,
        t.id DESC

     LIMIT 5'
);

$stmt->execute(
    array_merge(
        [
            'user_id_account' =>
                $userId,

            'user_id_category' =>
                $userId,

            'user_id_transaction' =>
                $userId
        ],
        $dateParams
    )
);

$recentTransactions =
    $stmt->fetchAll();

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
    <style>

            .summary-card {
                display: flex;
                gap: 20px;
                margin: 20px 0;
            }

            .summary-card {
                flex: 1;
                border: 1px solid #ccc;
                padding: 20px;
                border-radius: 8px;
            }

            .summary-card h3 {
                margin-top: 0;
            }

            .summary-card p {
                font-size: 24px;
                margin-bottom: 0;
            }

    </style>

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


<!-- =====================================================
     NAVEGAÇÃO
     ===================================================== -->

<h2>Navegação</h2>

<p>

    <a href="transactions.php">
        Transações
    </a>

    |

    <a href="accounts.php">
        Contas
    </a>

    |

    <a href="logout.php">
        Sair
    </a>

</p>


<hr>


<!-- =====================================================
     RESUMO
     ===================================================== -->

<h2>Resumo financeiro</h2>
<form method="GET">

    <label for="period">
        Período:
    </label>

    <select
        id="period"
        name="period"
    >

        <option
            value="all"
            <?= $period === 'all' ? 'selected' : '' ?>
        >
            Todos
        </option>

        <option
            value="this_month"
            <?= $period === 'this_month' ? 'selected' : '' ?>
        >
            Este mês
        </option>

        <option
            value="last_month"
            <?= $period === 'last_month' ? 'selected' : '' ?>
        >
            Mês anterior
        </option>

        <option
            value="last_30_days"
            <?= $period === 'last_30_days' ? 'selected' : '' ?>
        >
            Últimos 30 dias
        </option>

    </select>

    <button type="submit">
        Filtrar
    </button>

</form>

<br>

<div class="summary-card">

    <div class="summary-card">
        <h3>Receitas</h3>

        <p>
            <strong>
                <?= format_money($totalIncomeCents) ?>
            </strong>
        </p>
    </div>


    <div class="summary-card">
        <h3>Despesas</h3>

        <p>
            <strong>
                <?= format_money($totalExpenseCents) ?>
            </strong>
        </p>
    </div>


    <div class="summary-card">
        <h3>Resultado</h3>

        <p>
            <strong>
                <?= format_money($resultCents) ?>
            </strong>
        </p>
    </div>

</div>

<hr>


<!-- =====================================================
     CONTAS
     ===================================================== -->

<h2>Contas</h2>


<?php if (empty($accounts)): ?>

    <p>
        Ainda não tens contas.
    </p>

    <p>
        <a href="account-create.php">
            + Criar primeira conta
        </a>
    </p>

<?php else: ?>

    <table border="1" cellpadding="8">

        <thead>

            <tr>

                <th>Nome</th>

                <th>Tipo</th>

                <th>Saldo inicial</th>

                <th>Receitas</th>

                <th>Despesas</th>

                <th>Saldo atual</th>

                <th>Estado</th>

            </tr>

        </thead>


        <tbody>

        <?php foreach ($accounts as $account): ?>

            <?php

            $initialCents =
                decimal_to_cents(
                    $account['initial_balance']
                );

            $incomeCents =
                decimal_to_cents(
                    $account['total_income']
                );

            $expenseCents =
                decimal_to_cents(
                    $account['total_expense']
                );

            $currentCents =
                $initialCents
                + $incomeCents
                - $expenseCents;

            ?>


            <tr>

                <td>

                    <?= htmlspecialchars(
                        $account['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>


                <td>

                    <?php if (
                        $account['type'] === 'checking'
                    ): ?>

                        Conta à ordem

                    <?php elseif (
                        $account['type'] === 'savings'
                    ): ?>

                        Poupança

                    <?php elseif (
                        $account['type'] === 'cash'
                    ): ?>

                        Dinheiro

                    <?php elseif (
                        $account['type'] === 'credit_card'
                    ): ?>

                        Cartão de crédito

                    <?php elseif (
                        $account['type'] === 'investment'
                    ): ?>

                        Investimento

                    <?php else: ?>

                        Outro

                    <?php endif; ?>

                </td>


                <td>

                    <?= format_money(
                        $initialCents
                    ) ?>

                </td>


                <td>

                    <?= format_money(
                        $incomeCents
                    ) ?>

                </td>


                <td>

                    <?= format_money(
                        $expenseCents
                    ) ?>

                </td>


                <td>

                    <strong>

                        <?= format_money(
                            $currentCents
                        ) ?>

                    </strong>

                </td>


                <td>

                    <?php if (
                        (int) $account['is_active'] === 1
                    ): ?>

                        Ativa

                    <?php else: ?>

                        Inativa

                    <?php endif; ?>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>


    <p>

        <a href="accounts.php">
            Ver todas as contas
        </a>

    </p>

<?php endif; ?>


<hr>


<!-- =====================================================
     ÚLTIMAS TRANSAÇÕES
     ===================================================== -->

<h2>Últimas transações</h2>


<?php if (
    empty($recentTransactions)
): ?>

    <p>
        Ainda não tens transações.
    </p>

    <p>

        <a href="transaction-create.php">
            + Criar primeira transação
        </a>

    </p>

<?php else: ?>


    <table border="1" cellpadding="8">

        <thead>

            <tr>

                <th>Data</th>

                <th>Tipo</th>

                <th>Conta</th>

                <th>Categoria</th>

                <th>Descrição</th>

                <th>Valor</th>

            </tr>

        </thead>


        <tbody>

        <?php foreach (
            $recentTransactions
            as $transaction
        ): ?>

            <?php

            $amountCents =
                decimal_to_cents(
                    $transaction['amount']
                );

            ?>


            <tr>

                <td>

                    <?= htmlspecialchars(
                        $transaction['transaction_date'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>


                <td>

                    <?php if (
                        $transaction['type'] === 'income'
                    ): ?>

                        Receita

                    <?php else: ?>

                        Despesa

                    <?php endif; ?>

                </td>


                <td>

                    <?= htmlspecialchars(
                        $transaction['account_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>


                <td>

                    <?= htmlspecialchars(
                        $transaction['category_name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>


                <td>

                    <?= htmlspecialchars(
                        $transaction['description'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </td>


                <td>

                    <?php if (
                        $transaction['type'] === 'income'
                    ): ?>

                        +

                    <?php else: ?>

                        -

                    <?php endif; ?>


                    <?= format_money(
                        $amountCents
                    ) ?>

                </td>

            </tr>


        <?php endforeach; ?>

        </tbody>

    </table>


    <p>

        <a href="transactions.php">
            Ver todas as transações
        </a>

    </p>

<?php endif; ?>


</body>

</html>