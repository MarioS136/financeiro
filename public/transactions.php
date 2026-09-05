<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';
require_once __DIR__ . '/../src/Finance/money.php';

require_login();

$userId = current_user_id();

$errors = [];


/*
 * =========================================================
 * FILTROS
 * =========================================================
 */

$filterType = $_GET['type'] ?? '';

$filterAccountId = filter_var(
    $_GET['account_id'] ?? '',
    FILTER_VALIDATE_INT
);

$filterCategoryId = filter_var(
    $_GET['category_id'] ?? '',
    FILTER_VALIDATE_INT
);

$filterDateFrom = trim(
    $_GET['date_from'] ?? ''
);

$filterDateTo = trim(
    $_GET['date_to'] ?? ''
);


/*
 * =========================================================
 * VALIDAR FILTROS
 * =========================================================
 */


/*
 * Validar tipo.
 */
if (
    $filterType !== '' &&
    !in_array(
        $filterType,
        ['income', 'expense'],
        true
    )
) {
    $errors[] = 'Tipo de filtro inválido.';
    $filterType = '';
}


/*
 * Validar conta.
 */
if (
    $filterAccountId !== false &&
    $filterAccountId !== null &&
    $filterAccountId < 1
) {
    $errors[] = 'Conta inválida.';
    $filterAccountId = '';
}


/*
 * Validar categoria.
 */
if (
    $filterCategoryId !== false &&
    $filterCategoryId !== null &&
    $filterCategoryId < 1
) {
    $errors[] = 'Categoria inválida.';
    $filterCategoryId = '';
}


/*
 * Validar data inicial.
 */
if ($filterDateFrom !== '') {

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $filterDateFrom
    );

    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $filterDateFrom
    ) {
        $errors[] = 'Data inicial inválida.';
        $filterDateFrom = '';
    }
}


/*
 * Validar data final.
 */
if ($filterDateTo !== '') {

    $dateObject = DateTime::createFromFormat(
        'Y-m-d',
        $filterDateTo
    );

    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $filterDateTo
    ) {
        $errors[] = 'Data final inválida.';
        $filterDateTo = '';
    }
}


/*
 * Verificar intervalo de datas.
 */
if (
    $filterDateFrom !== '' &&
    $filterDateTo !== '' &&
    $filterDateFrom > $filterDateTo
) {
    $errors[] =
        'A data inicial não pode ser posterior à data final.';
}


/*
 * =========================================================
 * CONTAS DO UTILIZADOR
 * =========================================================
 */

$stmt = $pdo->prepare(
    'SELECT id, name
     FROM accounts
     WHERE user_id = :user_id
     ORDER BY name ASC'
);

$stmt->execute([
    'user_id' => $userId
]);

$accounts = $stmt->fetchAll();


/*
 * =========================================================
 * CATEGORIAS DO UTILIZADOR
 * =========================================================
 */

$stmt = $pdo->prepare(
    'SELECT id, name, type
     FROM categories
     WHERE user_id = :user_id
     ORDER BY type ASC, name ASC'
);

$stmt->execute([
    'user_id' => $userId
]);

$categories = $stmt->fetchAll();


/*
 * =========================================================
 * CONSTRUIR CONSULTA
 * =========================================================
 */

$sql = '
    SELECT
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
       AND a.user_id = t.user_id

    INNER JOIN categories c
        ON c.id = t.category_id
       AND c.user_id = t.user_id

    WHERE t.user_id = :user_id
';

$params = [
    'user_id' => $userId
];


/*
 * Filtro por tipo.
 */
if ($filterType !== '') {

    $sql .= '
        AND t.type = :type
    ';

    $params['type'] = $filterType;
}


/*
 * Filtro por conta.
 */
if (
    $filterAccountId !== false &&
    $filterAccountId !== null &&
    $filterAccountId !== ''
) {

    $sql .= '
        AND t.account_id = :account_id
    ';

    $params['account_id'] = $filterAccountId;
}


/*
 * Filtro por categoria.
 */
if (
    $filterCategoryId !== false &&
    $filterCategoryId !== null &&
    $filterCategoryId !== ''
) {

    $sql .= '
        AND t.category_id = :category_id
    ';

    $params['category_id'] = $filterCategoryId;
}


/*
 * Filtro por data inicial.
 */
if ($filterDateFrom !== '') {

    $sql .= '
        AND t.transaction_date >= :date_from
    ';

    $params['date_from'] = $filterDateFrom;
}


/*
 * Filtro por data final.
 */
if ($filterDateTo !== '') {

    $sql .= '
        AND t.transaction_date <= :date_to
    ';

    $params['date_to'] = $filterDateTo;
}


/*
 * =========================================================
 * CONTAR TOTAL DE RESULTADOS
 * =========================================================
 */

$countSql = '
    SELECT COUNT(*)

    FROM transactions t

    INNER JOIN accounts a
        ON a.id = t.account_id
       AND a.user_id = t.user_id

    INNER JOIN categories c
        ON c.id = t.category_id
       AND c.user_id = t.user_id

    WHERE t.user_id = :user_id
';

$countParams = [
    'user_id' => $userId
];


/*
 * Reaplicar filtro por tipo.
 */
if ($filterType !== '') {

    $countSql .= '
        AND t.type = :type
    ';

    $countParams['type'] = $filterType;
}


/*
 * Reaplicar filtro por conta.
 */
if (
    $filterAccountId !== false &&
    $filterAccountId !== null &&
    $filterAccountId !== ''
) {

    $countSql .= '
        AND t.account_id = :account_id
    ';

    $countParams['account_id'] = $filterAccountId;
}


/*
 * Reaplicar filtro por categoria.
 */
if (
    $filterCategoryId !== false &&
    $filterCategoryId !== null &&
    $filterCategoryId !== ''
) {

    $countSql .= '
        AND t.category_id = :category_id
    ';

    $countParams['category_id'] = $filterCategoryId;
}


/*
 * Reaplicar data inicial.
 */
if ($filterDateFrom !== '') {

    $countSql .= '
        AND t.transaction_date >= :date_from
    ';

    $countParams['date_from'] = $filterDateFrom;
}


/*
 * Reaplicar data final.
 */
if ($filterDateTo !== '') {

    $countSql .= '
        AND t.transaction_date <= :date_to
    ';

    $countParams['date_to'] = $filterDateTo;
}


/*
 * Executar COUNT.
 */
$countStmt = $pdo->prepare($countSql);

$countStmt->execute($countParams);

$totalTransactions = (int) $countStmt->fetchColumn();


/*
 * =========================================================
 * PAGINAÇÃO
 * =========================================================
 */

$perPage = 20;

$page = filter_var(
    $_GET['page'] ?? 1,
    FILTER_VALIDATE_INT
);

if (!$page || $page < 1) {
    $page = 1;
}


/*
 * Número total de páginas.
 */
$totalPages = max(
    1,
    (int) ceil(
        $totalTransactions / $perPage
    )
);


/*
 * Impedir páginas superiores ao total existente.
 */
if ($page > $totalPages) {
    $page = $totalPages;
}


/*
 * Calcular OFFSET.
 */
$offset = ($page - 1) * $perPage;


/*
 * =========================================================
 * ORDENAR E PAGINAR
 * =========================================================
 */

$sql .= '
    ORDER BY
        t.transaction_date DESC,
        t.id DESC

    LIMIT :limit
    OFFSET :offset
';


/*
 * Executar consulta.
 */
$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {

    $stmt->bindValue(
        ':' . $key,
        $value
    );
}

$stmt->bindValue(
    ':limit',
    $perPage,
    PDO::PARAM_INT
);

$stmt->bindValue(
    ':offset',
    $offset,
    PDO::PARAM_INT
);

$stmt->execute();

$transactions = $stmt->fetchAll();


/*
 * =========================================================
 * TOTAIS DA PÁGINA
 * =========================================================
 */

$totalIncomeCents = 0;
$totalExpenseCents = 0;


foreach ($transactions as $transaction) {

    $amountCents = decimal_to_cents(
        $transaction['amount']
    );

    if ($transaction['type'] === 'income') {

        $totalIncomeCents += $amountCents;

    } else {

        $totalExpenseCents += $amountCents;
    }
}


$balanceCents =
    $totalIncomeCents
    - $totalExpenseCents;

?>

<!DOCTYPE html>

<html lang="pt">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Transações</title>

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


        .container {
            width: min(1200px, 92%);
            margin: 0 auto;
        }


        .header {
            background: #ffffff;
            border-bottom: 1px solid #ddd;
            padding: 18px 0;
            margin-bottom: 25px;
        }


        .app-name {
            margin: 0;
            font-size: 24px;
        }


        .navigation {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-bottom: 25px;
        }


        .navigation a {
            display: inline-block;
            padding: 10px 16px;
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 7px;
            text-decoration: none;
        }


        .navigation a:hover {
            background: #eeeeee;
        }


        .navigation .active {
            font-weight: bold;
            background: #eeeeee;
        }


        .navigation .logout {
            margin-left: auto;
        }


        .section {
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 24px;
            margin-bottom: 25px;
        }


        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }


        .section-header h1,
        .section-header h2 {
            margin: 0;
        }


        .btn {
            display: inline-block;
            padding: 9px 14px;
            border: 1px solid #ccc;
            border-radius: 6px;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            background: #ffffff;
        }


        .btn:hover {
            background: #eeeeee;
        }


        .btn-primary {
            font-weight: bold;
        }


        .btn-danger {
            font-weight: bold;
        }


        .filters {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(180px, 1fr)
                );
            gap: 15px;
            margin-bottom: 20px;
        }


        .filter-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: bold;
            font-size: 14px;
        }


        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px 11px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: #ffffff;
            font-size: 15px;
        }


        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            margin-top: 15px;
        }


        .errors {
            margin-bottom: 20px;
            padding: 15px 18px;
            border: 1px solid #d8b4b4;
            border-radius: 6px;
            background: #faf3f3;
        }


        .errors ul {
            margin: 0;
            padding-left: 20px;
        }


        .summary {
            display: grid;
            grid-template-columns:
                repeat(
                    auto-fit,
                    minmax(180px, 1fr)
                );
            gap: 15px;
            margin-bottom: 25px;
        }


        .summary-card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 16px;
            background: #fafafa;
        }


        .summary-label {
            display: block;
            margin-bottom: 7px;
            color: #666;
            font-size: 14px;
        }


        .summary-value {
            margin: 0;
            font-size: 22px;
            font-weight: bold;
        }


        .transactions-wrapper {
            overflow-x: auto;
        }


        .transactions-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 850px;
        }


        .transactions-table th,
        .transactions-table td {
            padding: 11px 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: middle;
        }


        .transactions-table th {
            background: #f8f9fa;
            font-size: 14px;
        }


        .transactions-table tbody tr:hover {
            background: #fafafa;
        }


        .transaction-income {
            font-weight: bold;
        }


        .transaction-expense {
            font-weight: bold;
        }


        .transaction-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }


        .transaction-actions form {
            margin: 0;
        }


        .empty-state {
            text-align: center;
            padding: 30px 10px;
        }


        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 6px;
            flex-wrap: wrap;
            margin-top: 25px;
        }


        .pagination a,
        .pagination strong {
            display: inline-block;
            min-width: 36px;
            padding: 8px 11px;
            text-align: center;
            border: 1px solid #ccc;
            border-radius: 6px;
            text-decoration: none;
            background: #ffffff;
        }


        .pagination a:hover {
            background: #eeeeee;
        }


        .pagination strong {
            font-weight: bold;
            background: #eeeeee;
        }


        .pagination-info {
            text-align: center;
            color: #666;
            margin-bottom: 10px;
        }


        @media (max-width: 700px) {

            .container {
                width: 94%;
            }


            .header {
                padding: 15px 0;
            }


            .app-name {
                font-size: 21px;
            }


            .navigation {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
            }


            .navigation a {
                width: 100%;
                text-align: center;
            }


            .navigation .logout {
                margin-left: 0;
            }


            .section {
                padding: 18px;
            }


            .section-header {
                flex-direction: column;
                align-items: stretch;
            }


            .section-header .btn {
                text-align: center;
            }


            .filter-actions {
                flex-direction: column;
                align-items: stretch;
            }


            .filter-actions .btn,
            .filter-actions button {
                width: 100%;
            }


            .summary {
                grid-template-columns: 1fr;
            }

        }

    </style>

</head>

<body>


<header class="header">

    <div class="container">

        <h1 class="app-name">
            Financeiro
        </h1>

    </div>

</header>


<main class="container">


    <!-- =====================================================
         NAVEGAÇÃO
         ===================================================== -->

    <nav class="navigation">

        <a href="index.php">
            Dashboard
        </a>


        <a href="accounts.php">
            Contas
        </a>


        <a
            href="transactions.php"
            class="active"
        >
            Transações
        </a>


        <a
            href="logout.php"
            class="logout"
        >
            Sair
        </a>

    </nav>


    <!-- =====================================================
         TRANSAÇÕES
         ===================================================== -->

    <section class="section">


        <div class="section-header">

            <h2>
                Transações
            </h2>


            <a
                href="transaction-create.php"
                class="btn btn-primary"
            >
                + Nova transação
            </a>

        </div>


        <?php if (!empty($errors)): ?>

            <div class="errors">

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

            </div>

        <?php endif; ?>


        <!-- =================================================
             FILTROS
             ================================================= -->

        <form method="GET">


            <div class="filters">


                <div class="filter-group">

                    <label for="type">
                        Tipo
                    </label>

                    <select
                        id="type"
                        name="type"
                    >

                        <option value="">
                            Todos
                        </option>

                        <option
                            value="income"
                            <?= $filterType === 'income'
                                ? 'selected'
                                : '' ?>
                        >
                            Receitas
                        </option>

                        <option
                            value="expense"
                            <?= $filterType === 'expense'
                                ? 'selected'
                                : '' ?>
                        >
                            Despesas
                        </option>

                    </select>

                </div>


                <div class="filter-group">

                    <label for="account_id">
                        Conta
                    </label>

                    <select
                        id="account_id"
                        name="account_id"
                    >

                        <option value="">
                            Todas
                        </option>

                        <?php foreach ($accounts as $account): ?>

                            <option
                                value="<?= (int) $account['id'] ?>"
                                <?= (string) $filterAccountId ===
                                    (string) $account['id']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $account['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="filter-group">

                    <label for="category_id">
                        Categoria
                    </label>

                    <select
                        id="category_id"
                        name="category_id"
                    >

                        <option value="">
                            Todas
                        </option>

                        <?php foreach ($categories as $category): ?>

                            <option
                                value="<?= (int) $category['id'] ?>"
                                <?= (string) $filterCategoryId ===
                                    (string) $category['id']
                                    ? 'selected'
                                    : '' ?>
                            >

                                <?= htmlspecialchars(
                                    $category['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                -
                                <?= $category['type'] === 'income'
                                    ? 'Receita'
                                    : 'Despesa' ?>

                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>


                <div class="filter-group">

                    <label for="date_from">
                        De
                    </label>

                    <input
                        type="date"
                        id="date_from"
                        name="date_from"
                        value="<?= htmlspecialchars(
                            $filterDateFrom,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>


                <div class="filter-group">

                    <label for="date_to">
                        Até
                    </label>

                    <input
                        type="date"
                        id="date_to"
                        name="date_to"
                        value="<?= htmlspecialchars(
                            $filterDateTo,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>


            </div>


            <div class="filter-actions">

                <button
                    type="submit"
                    class="btn btn-primary"
                >
                    Filtrar
                </button>


                <a
                    href="transactions.php"
                    class="btn"
                >
                    Limpar filtros
                </a>

            </div>


        </form>


    </section>


    <!-- =====================================================
         RESUMO
         ===================================================== -->

    <section class="section">


        <div class="section-header">

            <h2>
                Resumo
            </h2>

        </div>


        <div class="summary">


            <div class="summary-card">

                <span class="summary-label">
                    Receitas
                </span>

                <p class="summary-value">
                    <?= format_money(
                        $totalIncomeCents
                    ) ?>
                </p>

            </div>


            <div class="summary-card">

                <span class="summary-label">
                    Despesas
                </span>

                <p class="summary-value">
                    <?= format_money(
                        $totalExpenseCents
                    ) ?>
                </p>

            </div>


            <div class="summary-card">

                <span class="summary-label">
                    Resultado
                </span>

                <p class="summary-value">
                    <?= format_money(
                        $balanceCents
                    ) ?>
                </p>

            </div>


            <div class="summary-card">

                <span class="summary-label">
                    Transações encontradas
                </span>

                <p class="summary-value">
                    <?= $totalTransactions ?>
                </p>

            </div>


        </div>


    </section>


    <!-- =====================================================
         LISTA
         ===================================================== -->

    <section class="section">


        <?php if (empty($transactions)): ?>


            <div class="empty-state">

                <p>
                    Não foram encontradas transações.
                </p>


                <a
                    href="transaction-create.php"
                    class="btn btn-primary"
                >
                    + Criar transação
                </a>

            </div>


        <?php else: ?>


            <div class="transactions-wrapper">

                <table class="transactions-table">

                    <thead>

                        <tr>

                            <th>
                                Data
                            </th>

                            <th>
                                Tipo
                            </th>

                            <th>
                                Conta
                            </th>

                            <th>
                                Categoria
                            </th>

                            <th>
                                Descrição
                            </th>

                            <th>
                                Valor
                            </th>

                            <th>
                                Ações
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $transactions
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
                                    $transaction[
                                        'transaction_date'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td>

                                <?php if (
                                    $transaction['type']
                                    === 'income'
                                ): ?>

                                    <span
                                        class="transaction-income"
                                    >
                                        Receita
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="transaction-expense"
                                    >
                                        Despesa
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $transaction[
                                        'account_name'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $transaction[
                                        'category_name'
                                    ],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $transaction[
                                        'description'
                                    ] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td>

                                <?php if (
                                    $transaction['type']
                                    === 'income'
                                ): ?>

                                    +

                                <?php else: ?>

                                    -

                                <?php endif; ?>


                                <?= format_money(
                                    $amountCents
                                ) ?>

                            </td>


                            <td>

                                <div class="transaction-actions">


                                    <a
                                        href="transaction-edit.php?id=<?= (int) $transaction['id'] ?>"
                                        class="btn"
                                    >
                                        Editar
                                    </a>


                                    <form
                                        method="POST"
                                        action="transaction-delete.php"
                                    >

                                        <?= csrf_field() ?>


                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $transaction['id'] ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="btn btn-danger"
                                            onclick="return confirm('Tens a certeza que queres apagar esta transação?');"
                                        >
                                            Apagar
                                        </button>

                                    </form>


                                </div>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


            <!-- =================================================
                 PAGINAÇÃO
                 ================================================= -->

            <?php if ($totalPages > 1): ?>


                <div class="pagination-info">

                    Página
                    <?= $page ?>
                    de
                    <?= $totalPages ?>

                </div>


                <div class="pagination">


                    <?php if ($page > 1): ?>

                        <?php

                        $previousParams = $_GET;

                        $previousParams['page'] =
                            $page - 1;

                        ?>

                        <a
                            href="?<?= htmlspecialchars(
                                http_build_query(
                                    $previousParams
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                            &laquo; Anterior
                        </a>

                    <?php endif; ?>


                    <?php for (
                        $pageNumber = 1;
                        $pageNumber <= $totalPages;
                        $pageNumber++
                    ): ?>

                        <?php

                        $pageParams = $_GET;

                        $pageParams['page'] =
                            $pageNumber;

                        ?>


                        <?php if (
                            $pageNumber === $page
                        ): ?>

                            <strong>
                                <?= $pageNumber ?>
                            </strong>

                        <?php else: ?>

                            <a
                                href="?<?= htmlspecialchars(
                                    http_build_query(
                                        $pageParams
                                    ),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >
                                <?= $pageNumber ?>
                            </a>

                        <?php endif; ?>


                    <?php endfor; ?>


                    <?php if (
                        $page < $totalPages
                    ): ?>

                        <?php

                        $nextParams = $_GET;

                        $nextParams['page'] =
                            $page + 1;

                        ?>

                        <a
                            href="?<?= htmlspecialchars(
                                http_build_query(
                                    $nextParams
                                ),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                        >
                            Seguinte &raquo;
                        </a>

                    <?php endif; ?>


                </div>


            <?php endif; ?>


        <?php endif; ?>


    </section>


</main>

</body>

</html>
