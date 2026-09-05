<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';
require_once __DIR__ . '/../src/Finance/money.php';

require_login();

$userId = current_user_id();

$errors = [];

$type = 'expense';
$accountId = '';
$categoryId = '';
$amount = '';
$description = '';
$transactionDate = date('Y-m-d');


/*
 * =========================================================
 * CONTAS
 * =========================================================
 */

$stmt = $pdo->prepare(
    'SELECT id, name
     FROM accounts
     WHERE user_id = :user_id
       AND is_active = 1
     ORDER BY name ASC'
);

$stmt->execute([
    'user_id' => $userId
]);

$accounts = $stmt->fetchAll();


/*
 * =========================================================
 * CATEGORIAS
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
 * PROCESSAR FORMULÁRIO
 * =========================================================
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {


    /*
     * CSRF
     */

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $errors[] =
            'Pedido inválido. Atualiza a página e tenta novamente.';
    }


    /*
     * Receber dados
     */

    $type = $_POST['type'] ?? '';

    $accountId = filter_var(
        $_POST['account_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $categoryId = filter_var(
        $_POST['category_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $amount = trim(
        $_POST['amount'] ?? ''
    );

    $description = trim(
        $_POST['description'] ?? ''
    );

    $transactionDate =
        $_POST['transaction_date'] ?? '';


    /*
     * Validar tipo
     */

    if (!in_array(
        $type,
        ['income', 'expense'],
        true
    )) {

        $errors[] =
            'Tipo de transação inválido.';
    }


    /*
     * Validar conta
     */

    if (!$accountId || $accountId < 1) {

        $errors[] =
            'Seleciona uma conta válida.';
    }


    /*
     * Validar categoria
     */

    if (!$categoryId || $categoryId < 1) {

        $errors[] =
            'Seleciona uma categoria válida.';
    }


    /*
     * Validar valor
     */

    $normalizedAmount =
        str_replace(
            ',',
            '.',
            $amount
        );

    if (
        !preg_match(
            '/^\d{1,13}(\.\d{1,2})?$/',
            $normalizedAmount
        )
    ) {

        $errors[] =
            'O valor deve ser positivo e ter no máximo 2 casas decimais.';
    }


    /*
     * Validar descrição
     */

    if (mb_strlen($description) > 255) {

        $errors[] =
            'A descrição não pode ultrapassar 255 caracteres.';
    }


    /*
     * Validar data
     */

    $dateObject =
        DateTime::createFromFormat(
            'Y-m-d',
            $transactionDate
        );

    if (
        !$dateObject ||
        $dateObject->format('Y-m-d') !== $transactionDate
    ) {

        $errors[] =
            'Data inválida.';
    }


    /*
     * =========================================================
     * VERIFICAR CONTA
     * =========================================================
     */

    if ($accountId) {

        $stmt = $pdo->prepare(
            'SELECT id
             FROM accounts
             WHERE id = :id
               AND user_id = :user_id
               AND is_active = 1
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $accountId,
            'user_id' => $userId
        ]);

        if (!$stmt->fetch()) {

            $errors[] =
                'Conta inválida ou inativa.';
        }
    }


    /*
     * =========================================================
     * VERIFICAR CATEGORIA
     * =========================================================
     */

    if ($categoryId) {

        $stmt = $pdo->prepare(
            'SELECT id, type
             FROM categories
             WHERE id = :id
               AND user_id = :user_id
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $categoryId,
            'user_id' => $userId
        ]);

        $category = $stmt->fetch();


        if (!$category) {

            $errors[] =
                'Categoria inválida.';

        } elseif ($category['type'] !== $type) {

            $errors[] =
                'A categoria não corresponde ao tipo da transação.';
        }
    }


    /*
     * =========================================================
     * CRIAR TRANSAÇÃO
     * =========================================================
     */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();


            $stmt = $pdo->prepare(
                'INSERT INTO transactions
                    (
                        user_id,
                        account_id,
                        category_id,
                        type,
                        amount,
                        description,
                        transaction_date
                    )
                 VALUES
                    (
                        :user_id,
                        :account_id,
                        :category_id,
                        :type,
                        :amount,
                        :description,
                        :transaction_date
                    )'
            );


            $stmt->execute([
                'user_id' =>
                    $userId,

                'account_id' =>
                    $accountId,

                'category_id' =>
                    $categoryId,

                'type' =>
                    $type,

                'amount' =>
                    $normalizedAmount,

                'description' =>
                    $description !== ''
                        ? $description
                        : null,

                'transaction_date' =>
                    $transactionDate
            ]);


            $pdo->commit();


            header(
                'Location: transactions.php'
            );

            exit;


        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {

                $pdo->rollBack();
            }


            $errors[] =
                'Não foi possível criar a transação.';
        }
    }
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

<title>Nova transação</title>


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
        width: min(900px, 92%);
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


    .section {
        background: #ffffff;
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 24px;
        margin-bottom: 25px;
    }


    .section h1 {
        margin-top: 0;
        margin-bottom: 5px;
        font-size: 28px;
    }


    .section-description {
        color: #666;
        margin-top: 0;
        margin-bottom: 25px;
    }


    .form-group {
        margin-bottom: 20px;
    }


    label {
        display: block;
        margin-bottom: 7px;
        font-weight: bold;
    }


    input,
    select,
    textarea {
        width: 100%;
        padding: 11px 12px;
        border: 1px solid #ccc;
        border-radius: 6px;
        font-size: 15px;
        background: #ffffff;
    }


    textarea {
        min-height: 110px;
        resize: vertical;
    }


    input:focus,
    select:focus,
    textarea:focus {
        outline: none;
        border-color: #888;
    }


    .form-actions {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }


    .btn {
        display: inline-block;
        padding: 10px 16px;
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


    .errors {
        border: 1px solid #aaa;
        background: #fafafa;
        border-radius: 7px;
        padding: 15px 20px;
        margin-bottom: 25px;
    }


    .errors ul {
        margin: 0;
        padding-left: 20px;
    }


    .errors li {
        margin-bottom: 5px;
    }


    .errors li:last-child {
        margin-bottom: 0;
    }


    .help-text {
        display: block;
        margin-top: 6px;
        color: #666;
        font-size: 13px;
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
            gap: 8px;
        }


        .navigation a {
            text-align: center;
            width: 100%;
        }


        .section {
            padding: 18px;
        }


        .section h1 {
            font-size: 24px;
        }


        .form-actions {
            flex-direction: column;
        }


        .form-actions .btn {
            width: 100%;
            text-align: center;
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

</nav>


<!-- =====================================================
     FORMULÁRIO
     ===================================================== -->

<section class="section">

    <h1>
        Nova transação
    </h1>


    <p class="section-description">
        Regista uma nova receita ou despesa.
    </p>


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


    <form method="POST">

        <?= csrf_field() ?>


        <!-- TIPO -->

        <div class="form-group">

            <label for="type">
                Tipo
            </label>


            <select
                id="type"
                name="type"
                required
            >

                <option
                    value="expense"
                    <?= $type === 'expense'
                        ? 'selected'
                        : '' ?>
                >
                    Despesa
                </option>


                <option
                    value="income"
                    <?= $type === 'income'
                        ? 'selected'
                        : '' ?>
                >
                    Receita
                </option>

            </select>

        </div>


        <!-- CONTA -->

        <div class="form-group">

            <label for="account_id">
                Conta
            </label>


            <select
                id="account_id"
                name="account_id"
                required
            >

                <option value="">
                    -- Seleciona uma conta --
                </option>


                <?php foreach ($accounts as $account): ?>

                    <option
                        value="<?= (int) $account['id'] ?>"
                        <?= (string) $accountId ===
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

            <span class="help-text">
                Apenas contas ativas aparecem nesta lista.
            </span>

        </div>


        <!-- CATEGORIA -->

        <div class="form-group">

            <label for="category_id">
                Categoria
            </label>


            <select
                id="category_id"
                name="category_id"
                required
            >

                <option value="">
                    -- Seleciona uma categoria --
                </option>


                <?php foreach ($categories as $category): ?>

                    <option
                        value="<?= (int) $category['id'] ?>"
                        data-type="<?= htmlspecialchars(
                            $category['type'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        <?= (string) $categoryId ===
                            (string) $category['id']
                            ? 'selected'
                            : '' ?>
                    >

                        <?= htmlspecialchars(
                            $category['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- VALOR -->

        <div class="form-group">

            <label for="amount">
                Valor
            </label>


            <input
                type="text"
                id="amount"
                name="amount"
                value="<?= htmlspecialchars(
                    $amount,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                inputmode="decimal"
                placeholder="0,00"
                maxlength="16"
                required
            >


            <span class="help-text">
                Podes utilizar vírgula ou ponto como separador decimal.
            </span>

        </div>


        <!-- DATA -->

        <div class="form-group">

            <label for="transaction_date">
                Data
            </label>


            <input
                type="date"
                id="transaction_date"
                name="transaction_date"
                value="<?= htmlspecialchars(
                    $transactionDate,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                required
            >

        </div>


        <!-- DESCRIÇÃO -->

        <div class="form-group">

            <label for="description">
                Descrição
            </label>


            <textarea
                id="description"
                name="description"
                maxlength="255"
                placeholder="Ex.: Supermercado, salário, renda..."
            ><?= htmlspecialchars(
                $description,
                ENT_QUOTES,
                'UTF-8'
            ) ?></textarea>


            <span class="help-text">
                Opcional. Máximo de 255 caracteres.
            </span>

        </div>


        <!-- AÇÕES -->

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                Guardar transação
            </button>


            <a
                href="transactions.php"
                class="btn"
            >
                Cancelar
            </a>

        </div>

    </form>

</section>


</main>

<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        const typeSelect =
            document.getElementById('type');

        const categorySelect =
            document.getElementById('category_id');


        function filterCategories() {

            const selectedType =
                typeSelect.value;

            const options =
                categorySelect.querySelectorAll(
                    'option[data-type]'
                );


            options.forEach(
                function (option) {

                    const optionType =
                        option.dataset.type;


                    if (
                        optionType === selectedType
                    ) {

                        option.hidden = false;

                    } else {

                        option.hidden = true;


                        if (option.selected) {

                            categorySelect.value = '';

                        }
                    }
                }
            );
        }


        typeSelect.addEventListener(
            'change',
            filterCategories
        );


        filterCategories();

    }
);

</script>

</body>

</html>
