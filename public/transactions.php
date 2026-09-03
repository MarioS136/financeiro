<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';
require_once __DIR__ . '/../src/Finance/money.php';

require_login();

$userId = current_user_id();

$errors = [];



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
 *
 * Usamos os mesmos filtros da consulta principal.
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
    (int) ceil($totalTransactions / $perPage)
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
 * Ordenação + paginação.
 */
$sql .= '
    ORDER BY
        t.transaction_date DESC,
        t.id DESC
    LIMIT :limit
    OFFSET :offset
';


/*
 * Executar.
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
 * TOTAIS
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

</head>

<body>

<h1>Transações</h1>


<p>

    <a href="index.php">
        Dashboard
    </a>

    |

    <a href="transaction-create.php">
        + Nova transação
    </a>

</p>


<hr>


<h2>Filtros</h2>


<form method="GET">


    <div>

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


    <br>


    <div>

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


    <br>


    <div>

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


    <br>


    <div>

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


    <br>


    <button type="submit">
        Filtrar
    </button>


    <a href="transactions.php">
        Limpar filtros
    </a>


</form>


<hr>


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


<h2>Resumo</h2>


<p>

    Receitas:

    <strong>
        <?= format_money($totalIncomeCents) ?>
    </strong>

</p>


<p>

    Despesas:

    <strong>
        <?= format_money($totalExpenseCents) ?>
    </strong>

</p>


<p>

    Resultado:

    <strong>
        <?= format_money($balanceCents) ?>
    </strong>

</p>


<p>

    Transações encontradas:

    <strong>
        <?= count($transactions) ?>
    </strong>

</p>


<hr>


<?php if (empty($transactions)): ?>

    <p>
        Não foram encontradas transações.
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

                <th>Ações</th>

            </tr>

        </thead>


        <tbody>


        <?php foreach ($transactions as $transaction): ?>

            <?php

            $amountCents = decimal_to_cents(
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

                    <?php if ($transaction['type'] === 'income'): ?>

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

                    <?php if ($transaction['type'] === 'income'): ?>

                        +

                    <?php else: ?>

                        -

                    <?php endif; ?>

                    <?= format_money($amountCents) ?>

                </td>


                <td>

                    <a
                        href="transaction-edit.php?id=<?= (int) $transaction['id'] ?>"
                    >
                        Editar
                    </a>


                    <form
                        method="POST"
                        action="transaction-delete.php"
                        style="display:inline;"
                    >

                        <?= csrf_field() ?>


                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $transaction['id'] ?>"
                        >


                        <button
                            type="submit"
                            onclick="return confirm('Tens a certeza que queres apagar esta transação?');"
                        >
                            Apagar
                        </button>

                    </form>

                </td>

            </tr>


        <?php endforeach; ?>


        </tbody>

    </table>


<?php endif; ?>

<?php if ($totalPages > 1): ?>

    <hr>

    <p>
        Página
        <?= $page ?>
        de
        <?= $totalPages ?>
    </p>

    <p>

        <?php if ($page > 1): ?>

            <?php
            $previousParams = $_GET;
            $previousParams['page'] = $page - 1;
            ?>

            <a href="?<?= htmlspecialchars(
                http_build_query($previousParams),
                ENT_QUOTES,
                'UTF-8'
            ) ?>">
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
            $pageParams['page'] = $pageNumber;
            ?>

            <?php if ($pageNumber === $page): ?>

                <strong>
                    <?= $pageNumber ?>
                </strong>

            <?php else: ?>

                <a href="?<?= htmlspecialchars(
                    http_build_query($pageParams),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>">
                    <?= $pageNumber ?>
                </a>

            <?php endif; ?>

            &nbsp;

        <?php endfor; ?>


        <?php if ($page < $totalPages): ?>

            <?php
            $nextParams = $_GET;
            $nextParams['page'] = $page + 1;
            ?>

            <a href="?<?= htmlspecialchars(
                http_build_query($nextParams),
                ENT_QUOTES,
                'UTF-8'
            ) ?>">
                Seguinte &raquo;
            </a>

        <?php endif; ?>

    </p>

<?php endif; ?>

</body>

</html>
