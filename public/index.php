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

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f6f8;
            color: #222;
        }

        a {
            color: inherit;
        }


        /* =====================================================
        LAYOUT PRINCIPAL
        ===================================================== */

        .container {
            width: min(1200px, 92%);
            margin: 0 auto;
        }


        /* =====================================================
        CABEÇALHO
        ===================================================== */

        .header {
            background: #ffffff;
            border-bottom: 1px solid #ddd;
            padding: 18px 0;
            margin-bottom: 30px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .app-name {
            margin: 0;
            font-size: 24px;
        }

        .user-info {
            text-align: right;
            font-size: 14px;
        }


        /* =====================================================
        NAVEGAÇÃO
        ===================================================== */

        .navigation {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 30px;
        }

        .navigation a {
            text-decoration: none;
            background: #ffffff;
            border: 1px solid #ddd;
            padding: 10px 16px;
            border-radius: 6px;
        }

        .navigation a:hover {
            background: #eeeeee;
        }

        .navigation .logout {
            margin-left: auto;
        }


        /* =====================================================
        SECÇÕES
        ===================================================== */

        .section {
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 24px;
            margin-bottom: 25px;
        }

        .section h2 {
            margin-top: 0;
        }


        /* =====================================================
        FILTRO
        ===================================================== */

        .filter-form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .filter-form select,
        .filter-form button {
            padding: 9px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: #ffffff;
        }

        .filter-form button {
            cursor: pointer;
        }


        /* =====================================================
        RESUMO
        ===================================================== */

        .summary-cards {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            gap: 20px;
            margin: 20px 0;
        }

        .summary-card {
            flex: 1 1 0;
            border: 1px solid #ccc;
            padding: 20px;
            border-radius: 8px;
            background: #fafafa;
            box-sizing: border-box;
        }

        .summary-card h3 {
            margin-top: 0;
        }

        .summary-card p {
            font-size: 24px;
            margin-bottom: 0;
        }


        /* =====================================================
        CONTAS
        ===================================================== */

        .account-cards {
            display: flex;
            flex-direction: row;
            flex-wrap: wrap;
            gap: 20px;
        }

        .account-card {
            flex: 1 1 280px;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 8px;
            background: #fafafa;
        }

        .account-card h3 {
            margin-top: 0;
        }

        .account-balance {
            font-size: 28px;
        }


        /* =====================================================
        TRANSAÇÕES
        ===================================================== */

        .transactions-table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        .transactions-table-wrapper table {
            width: 100%;
            border-collapse: collapse;
        }

        .transactions-table-wrapper th,
        .transactions-table-wrapper td {
            border-bottom: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }

        .transactions-table-wrapper th {
            background: #f5f5f5;
        }


        /* =====================================================
        RESPONSIVO
        ===================================================== */

        @media (max-width: 700px) {

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .user-info {
                text-align: left;
            }

            .navigation .logout {
                margin-left: 0;
            }

            .summary-cards {
                flex-direction: column;
            }

            .summary-card {
                width: 100%;
            }

        }

            /* =====================================================
       ESTADOS FINANCEIROS
       ===================================================== */

    .positive {
        font-weight: bold;
    }

    .negative {
        font-weight: bold;
    }

    .neutral {
        font-weight: bold;
    }


    /* =====================================================
       BOTÕES
       ===================================================== */

    .btn {
        display: inline-block;
        padding: 9px 14px;
        border: 1px solid #ccc;
        border-radius: 6px;
        text-decoration: none;
        cursor: pointer;
        font-size: 14px;
    }

    .btn:hover {
        opacity: 0.85;
    }

    .btn-primary {
        font-weight: bold;
    }

    .btn-danger {
        font-weight: bold;
    }


    /* =====================================================
       ESTADO DAS CONTAS
       ===================================================== */

    .status {
        display: inline-block;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 13px;
    }

    .status-active {
        border: 1px solid #999;
    }

    .status-inactive {
        border: 1px solid #999;
    }

    /* ================
        RESPONSIVIDADE
   ============= */

        @media (max-width: 768px) {

            body {
                padding: 15px;
            }

            h1 {
                font-size: 26px;
            }

            h2 {
                font-size: 21px;
            }


            /* Resumo */

            .summary-card {
                flex-direction: column;
                gap: 12px;
            }

            .summary-card .summary-card {
                width: 100%;
                box-sizing: border-box;
                margin: 0;
            }


            /* Contas */

            .account-cards {
                flex-direction: column;
                gap: 15px;
            }

            .account-card {
                width: 100%;
                flex: none;
            }


            /* Filtro */

            form {
                max-width: 100%;
            }

            select,
            input,
            button {
                max-width: 100%;
            }


            /* Tabela */

            .transactions-table-wrapper {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            .transactions-table-wrapper table {
                min-width: 700px;
            }

        }

        @media screen and (max-width: 768px) {

            .summary-cards {
                display: flex;
                flex-direction: column !important;
                flex-wrap: nowrap;
                width: 100%;
                gap: 12px;
            }

            .summary-cards .summary-card {
                display: block;
                flex: none;
                width: 100% !important;
                box-sizing: border-box;
            }

        }
    </style>

</head>

<body>

<header class="header">

    <div class="container header-content">

        <h1 class="app-name">
            Dashboard Financeiro
        </h1>

        <div class="user-info">

            <div>
                Olá,
                <strong>
                    <?= htmlspecialchars(
                        $userName,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </strong>
            </div>

            <div>
                Perfil:
                <?= htmlspecialchars(
                    $userRole,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </div>

        </div>

    </div>

</header>


<main class="container">

    <nav class="navigation">

        <a href="index.php">
            Dashboard
        </a>

        <a href="accounts.php">
            Contas
        </a>

        <a href="transactions.php">
            Transações
        </a>

        <a
            href="logout.php"
            class="logout"
        >
            Sair
        </a>

    </nav>


<hr>



<!-- =====================================================
     RESUMO
     ===================================================== -->

<section class="section">
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

<div class="summary-cards">

    <div class="summary-card">
        <h3>Receitas</h3>

        <p>
            <strong class="positive">
                + <?= format_money($totalIncomeCents) ?>
            </strong>
        </p>
    </div>


    <div class="summary-card">
        <h3>Despesas</h3>

        <p>
            <strong class="negative">
                - <?= format_money($totalExpenseCents) ?>
            </strong>
        </p>
    </div>


    <div class="summary-card">
        <h3>Resultado</h3>

        <p>
            <strong
                class="<? $resultCents > 0
                ? 'positive'
                : ($resultCents < 0
                    ? 'negative'
                    : 'neutral') ?>"
            >
                <?= format_money($resultCents) ?>
            </strong>
        </p>
    </div>

</div>
</section>

<hr>

<!-- ======
     CONTAS
     ====== -->
<section class="section">
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

    <div class="account-cards">

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

            <div class="account-card">

                <h3>
                    <?= htmlspecialchars(
                        $account['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </h3>


                <p>

                    <?php if ($account['type'] === 'checking'): ?>

                        Conta à ordem

                    <?php elseif ($account['type'] === 'savings'): ?>

                        Poupança

                    <?php elseif ($account['type'] === 'cash'): ?>

                        Dinheiro

                    <?php elseif ($account['type'] === 'credit_card'): ?>

                        Cartão de crédito

                    <?php elseif ($account['type'] === 'investment'): ?>

                        Investimento

                    <?php else: ?>

                        Outro

                    <?php endif; ?>

                </p>


                <hr>


                <p>
                    Saldo atual
                </p>

                <p class="account-balance">

                    <strong>
                        <?= format_money($currentCents) ?>
                    </strong>

                </p>


                <p>
                    Saldo inicial:
                    <?= format_money($initialCents) ?>
                </p>


                <p>
                    Receitas:
                    <?= format_money($incomeCents) ?>
                </p>


                <p>
                    Despesas:
                    <?= format_money($expenseCents) ?>
                </p>


                <p>

                    Estado:

                    <?php if (
                        (int) $account['is_active'] === 1
                    ): ?>

                        <span class="status-active">
                            Ativa
                        </span>

                    <?php else: ?>
                        <span class="status-inactive">
                            Inativa
                        </span>
                    <?php endif; ?>

                </p>


                <p>

                    <a
                        href="account-edit.php?id=<?= (int) $account['id'] ?>"
                    >
                        Editar conta
                    </a>

                </p>

            </div>

        <?php endforeach; ?>

    </div>


    <p>

        <a href="accounts.php">
            Ver todas as contas
        </a>

    </p>

<?php endif; ?>
</section>

<hr>

<!-- =====================================================
     ÚLTIMAS TRANSAÇÕES
     ===================================================== -->
<section class="section">
<h2>Últimas transações</h2>

<?php if (empty($recentTransactions)): ?>

    <p>
        Não existem transações para o período selecionado.
    </p>

<?php else: ?>

    <div class="transactions-table-wrapper">

        <table>

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

                            <?= format_money($amountCents) ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

    <p>
        <a href="transactions.php">
            Ver todas as transações →
        </a>
    </p>

<?php endif; ?>
</section>

</main>
</body>

</html>