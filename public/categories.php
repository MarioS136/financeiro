<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';

require_login();

$userId = current_user_id();

$stmt = $pdo->prepare(
    'SELECT id, name, type, created_at
     FROM categories
     WHERE user_id = :user_id
     ORDER BY type ASC, name ASC'
);

$stmt->execute([
    'user_id' => $userId
]);

$categories = $stmt->fetchAll();

?>

<!DOCTYPE html>

<html lang="pt">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Categorias</title>

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


        .categories-wrapper {
            overflow-x: auto;
        }


        .categories-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 600px;
        }


        .categories-table th,
        .categories-table td {
            padding: 11px 10px;
            border-bottom: 1px solid #ddd;
            text-align: left;
            vertical-align: middle;
        }


        .categories-table th {
            background: #f8f9fa;
            font-size: 14px;
        }


        .categories-table tbody tr:hover {
            background: #fafafa;
        }


        .category-income {
            font-weight: bold;
        }


        .category-expense {
            font-weight: bold;
        }


        .category-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }


        .category-actions form {
            margin: 0;
        }


        .empty-state {
            text-align: center;
            padding: 30px 10px;
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
         CATEGORIAS
         ===================================================== -->

    <section class="section">


        <div class="section-header">

            <h2>
                Categorias
            </h2>


            <a
                href="category-create.php"
                class="btn btn-primary"
            >
                + Nova categoria
            </a>

        </div>


        <?php if (empty($categories)): ?>


            <div class="empty-state">

                <p>
                    Ainda não tens categorias.
                </p>


                <a
                    href="category-create.php"
                    class="btn btn-primary"
                >
                    + Criar categoria
                </a>

            </div>


        <?php else: ?>


            <div class="categories-wrapper">

                <table class="categories-table">

                    <thead>

                        <tr>

                            <th>
                                Nome
                            </th>

                            <th>
                                Tipo
                            </th>

                            <th>
                                Ações
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $categories
                        as $category
                    ): ?>

                        <tr>


                            <td>

                                <?= htmlspecialchars(
                                    $category['name'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </td>


                            <td>

                                <?php if (
                                    $category['type']
                                    === 'income'
                                ): ?>

                                    <span
                                        class="category-income"
                                    >
                                        Receita
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="category-expense"
                                    >
                                        Despesa
                                    </span>

                                <?php endif; ?>

                            </td>


                            <td>

                                <div class="category-actions">


                                    <a
                                        href="category-edit.php?id=<?= (int) $category['id'] ?>"
                                        class="btn"
                                    >
                                        Editar
                                    </a>


                                    <form
                                        method="POST"
                                        action="category-delete.php"
                                    >

                                        <?= csrf_field() ?>


                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $category['id'] ?>"
                                        >


                                        <button
                                            type="submit"
                                            class="btn btn-danger"
                                            onclick="return confirm('Tens a certeza que queres apagar esta categoria?');"
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


        <?php endif; ?>


    </section>


</main>

</body>

</html>
