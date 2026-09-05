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
     * =====================================================
     * CSRF
     * =====================================================
     */

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Pedido inválido. Atualiza a página e tenta novamente.';
    }


    /*
     * =====================================================
     * DADOS
     * =====================================================
     */

    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';
    $initialBalance = trim(
        $_POST['initial_balance'] ?? '0.00'
    );


    /*
     * =====================================================
     * NOME
     * =====================================================
     */

    if ($name === '') {

        $errors[] =
            'O nome da conta é obrigatório.';

    } elseif (
        mb_strlen($name) < 2 ||
        mb_strlen($name) > 100
    ) {

        $errors[] =
            'O nome deve ter entre 2 e 100 caracteres.';
    }


    /*
     * =====================================================
     * TIPO
     * =====================================================
     */

    if (!in_array($type, $allowedTypes, true)) {

        $errors[] =
            'Tipo de conta inválido.';
    }


    /*
     * =====================================================
     * SALDO INICIAL
     *
     * Aceitamos:
     *
     * 1000
     * 1000.50
     * 1000,50
     * =====================================================
     */

    $normalizedBalance =
        str_replace(',', '.', $initialBalance);

    if (
        !preg_match(
            '/^-?\d{1,13}(\.\d{1,2})?$/',
            $normalizedBalance
        )
    ) {

        $errors[] =
            'Saldo inicial inválido.';
    }


    /*
     * =====================================================
     * CRIAR CONTA
     * =====================================================
     */

    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'INSERT INTO accounts
                (
                    user_id,
                    name,
                    type,
                    initial_balance
                )
             VALUES
                (
                    :user_id,
                    :name,
                    :type,
                    :initial_balance
                )'
        );

        $stmt->execute([
            'user_id' =>
                $userId,

            'name' =>
                $name,

            'type' =>
                $type,

            'initial_balance' =>
                $normalizedBalance
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

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Nova conta</title>


<style>

    /*
     * =====================================================
     * RESET
     * =====================================================
     */

    * {
        box-sizing: border-box;
    }


    /*
     * =====================================================
     * PÁGINA
     * =====================================================
     */

    body {
        margin: 0;
        padding: 0;

        font-family:
            Arial,
            sans-serif;

        background: #f5f6f8;
        color: #222;
    }


    /*
     * =====================================================
     * CONTAINER
     * =====================================================
     */

    .container {
        width: min(700px, 92%);
        margin: 40px auto;
    }


    /*
     * =====================================================
     * CABEÇALHO
     * =====================================================
     */

    .page-header {
        margin-bottom: 25px;
    }

    .page-header h1 {
        margin: 0 0 8px 0;
        font-size: 30px;
    }

    .page-header p {
        margin: 0;
        color: #666;
    }


    /*
     * =====================================================
     * NAVEGAÇÃO
     * =====================================================
     */

    .navigation {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;

        margin-bottom: 25px;
    }

    .navigation a {
        display: inline-block;

        padding: 9px 14px;

        background: #ffffff;

        border: 1px solid #ddd;
        border-radius: 6px;

        text-decoration: none;

        color: #222;
    }

    .navigation a:hover {
        background: #eeeeee;
    }


    /*
     * =====================================================
     * FORMULÁRIO
     * =====================================================
     */

    .form-card {
        background: #ffffff;

        border: 1px solid #ddd;
        border-radius: 10px;

        padding: 28px;

        box-shadow:
            0 2px 6px rgba(0, 0, 0, 0.04);
    }


    /*
     * =====================================================
     * CAMPOS
     * =====================================================
     */

    .form-group {
        margin-bottom: 22px;
    }

    .form-group label {
        display: block;

        margin-bottom: 7px;

        font-weight: bold;
    }

    .form-help {
        display: block;

        margin-top: 6px;

        font-size: 13px;

        color: #666;
    }


    /*
     * =====================================================
     * INPUTS
     * =====================================================
     */

    .form-group input,
    .form-group select {

        width: 100%;

        padding: 11px 12px;

        border: 1px solid #ccc;
        border-radius: 6px;

        background: #ffffff;

        font-size: 16px;

    }

    .form-group input:focus,
    .form-group select:focus {

        outline: none;

        border-color: #777;

    }


    /*
     * =====================================================
     * ERROS
     * =====================================================
     */

    .errors {

        margin-bottom: 25px;

        padding: 15px 18px;

        border: 1px solid #d8b4b4;

        border-radius: 6px;

        background: #faf3f3;

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


    /*
     * =====================================================
     * AÇÕES
     * =====================================================
     */

    .form-actions {

        display: flex;

        gap: 10px;

        align-items: center;

        flex-wrap: wrap;

        margin-top: 10px;

    }

    .btn {

        display: inline-block;

        padding: 10px 16px;

        border-radius: 6px;

        border: 1px solid #ccc;

        background: #ffffff;

        color: #222;

        text-decoration: none;

        font-size: 15px;

        cursor: pointer;

    }

    .btn:hover {

        background: #eeeeee;

    }

    .btn-primary {

        font-weight: bold;

    }


    /*
     * =====================================================
     * RESPONSIVO
     * =====================================================
     */

    @media (max-width: 600px) {

        .container {

            width: 94%;

            margin: 20px auto;

        }

        .page-header h1 {

            font-size: 26px;

        }

        .form-card {

            padding: 20px;

        }

        .form-actions {

            flex-direction: column;

            align-items: stretch;

        }

        .form-actions .btn {

            width: 100%;

            text-align: center;

        }

    }

</style>


</head>

<body>

<div class="container">


<!-- =====================================================
     CABEÇALHO
     ===================================================== -->

<header class="page-header">

    <h1>
        Nova conta
    </h1>

    <p>
        Adiciona uma nova conta financeira ao sistema.
    </p>

</header>


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

    <a href="transactions.php">
        Transações
    </a>

</nav>


<!-- =====================================================
     FORMULÁRIO
     ===================================================== -->

<div class="form-card">


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


        <!-- =================================================
             NOME
             ================================================= -->

        <div class="form-group">

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
                autocomplete="off"
                required
            >

            <span class="form-help">
                Exemplo: Conta principal, Poupança ou Carteira.
            </span>

        </div>


        <!-- =================================================
             TIPO
             ================================================= -->

        <div class="form-group">

            <label for="type">
                Tipo de conta
            </label>

            <select
                id="type"
                name="type"
                required
            >

                <option
                    value="checking"
                    <?= $type === 'checking'
                        ? 'selected'
                        : '' ?>
                >
                    Conta corrente
                </option>

                <option
                    value="savings"
                    <?= $type === 'savings'
                        ? 'selected'
                        : '' ?>
                >
                    Poupança
                </option>

                <option
                    value="cash"
                    <?= $type === 'cash'
                        ? 'selected'
                        : '' ?>
                >
                    Dinheiro
                </option>

                <option
                    value="credit_card"
                    <?= $type === 'credit_card'
                        ? 'selected'
                        : '' ?>
                >
                    Cartão de crédito
                </option>

                <option
                    value="investment"
                    <?= $type === 'investment'
                        ? 'selected'
                        : '' ?>
                >
                    Investimento
                </option>

                <option
                    value="other"
                    <?= $type === 'other'
                        ? 'selected'
                        : '' ?>
                >
                    Outro
                </option>

            </select>

        </div>


        <!-- =================================================
             SALDO INICIAL
             ================================================= -->

        <div class="form-group">

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
                autocomplete="off"
                required
            >

            <span class="form-help">
                Podes utilizar ponto ou vírgula para os
                centavos. Exemplo: 1250,50.
            </span>

        </div>


        <!-- =================================================
             BOTÕES
             ================================================= -->

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                Criar conta
            </button>

            <a
                href="accounts.php"
                class="btn"
            >
                Cancelar
            </a>

        </div>


    </form>

</div>


</div>

</body>

</html>
