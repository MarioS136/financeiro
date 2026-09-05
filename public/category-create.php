<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';

require_login();

$userId = current_user_id();

$errors = [];

$name = '';
$type = 'expense';

$allowedTypes = [
    'income',
    'expense'
];


if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * =========================================================
     * CSRF
     * =========================================================
     */

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $errors[] =
            'Pedido inválido. Atualiza a página e tenta novamente.';
    }


    /*
     * =========================================================
     * RECEBER DADOS
     * =========================================================
     */

    $name =
        trim($_POST['name'] ?? '');

    $type =
        $_POST['type'] ?? '';


    /*
     * =========================================================
     * VALIDAR NOME
     * =========================================================
     */

    if ($name === '') {

        $errors[] =
            'O nome da categoria é obrigatório.';

    } elseif (
        mb_strlen($name) < 2 ||
        mb_strlen($name) > 100
    ) {

        $errors[] =
            'O nome deve ter entre 2 e 100 caracteres.';
    }


    /*
     * =========================================================
     * VALIDAR TIPO
     * =========================================================
     */

    if (!in_array(
        $type,
        $allowedTypes,
        true
    )) {

        $errors[] =
            'Tipo de categoria inválido.';
    }


    /*
     * =========================================================
     * VERIFICAR DUPLICADO
     * =========================================================
     */

    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'SELECT id
             FROM categories
             WHERE user_id = :user_id
               AND name = :name
               AND type = :type
             LIMIT 1'
        );

        $stmt->execute([
            'user_id' =>
                $userId,

            'name' =>
                $name,

            'type' =>
                $type
        ]);


        if ($stmt->fetch()) {

            $errors[] =
                'Essa categoria já existe.';
        }
    }


    /*
     * =========================================================
     * CRIAR CATEGORIA
     * =========================================================
     */

    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'INSERT INTO categories
                (user_id, name, type)
             VALUES
                (:user_id, :name, :type)'
        );

        $stmt->execute([
            'user_id' =>
                $userId,

            'name' =>
                $name,

            'type' =>
                $type
        ]);


        header(
            'Location: categories.php'
        );

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

<title>Nova categoria</title>


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


    .form-group {
        margin-bottom: 18px;
    }


    .form-group label {
        display: block;
        margin-bottom: 6px;
        font-weight: bold;
        font-size: 14px;
    }


    .form-group input,
    .form-group select {
        width: 100%;
        padding: 10px 11px;
        border: 1px solid #ccc;
        border-radius: 6px;
        background: #ffffff;
        font-size: 15px;
    }


    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #999;
    }


    .form-actions {
        display: flex;
        gap: 8px;
        align-items: center;
        flex-wrap: wrap;
        margin-top: 25px;
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


        .form-actions {
            flex-direction: column;
            align-items: stretch;
        }


        .form-actions .btn,
        .form-actions button {
            width: 100%;
            text-align: center;
        }

    }

</style>
```

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


    <a href="transactions.php">
        Transações
    </a>


    <a
        href="categories.php"
        class="active"
    >
        Categorias
    </a>


    <a
        href="logout.php"
        class="logout"
    >
        Sair
    </a>

</nav>


<!-- =====================================================
     NOVA CATEGORIA
     ===================================================== -->

<section class="section">


    <div class="section-header">

        <h2>
            Nova categoria
        </h2>

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


    <form method="POST">


        <?= csrf_field() ?>


        <!-- =================================================
             NOME
             ================================================= -->

        <div class="form-group">

            <label for="name">
                Nome
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


        <!-- =================================================
             TIPO
             ================================================= -->

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


        <!-- =================================================
             AÇÕES
             ================================================= -->

        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
            >
                Criar categoria
            </button>


            <a
                href="categories.php"
                class="btn"
            >
                Cancelar
            </a>

        </div>


    </form>


</section>


</main>

</body>

</html>
