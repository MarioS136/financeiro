<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';
require_once __DIR__ . '/../src/Finance/money.php';

require_login();

$userId = current_user_id();

/*
 * =========================================================
 * CONTAS
 * =========================================================
 *
 * Buscar as contas do utilizador e calcular:
 *
 * saldo inicial
 * + receitas
 * - despesas
 *
 * Os totais são calculados pelo MariaDB usando DECIMAL.
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
 * FUNÇÃO PARA MOSTRAR O TIPO DA CONTA
 * =========================================================
 */

function account_type_label(string $type): string
{
    return match ($type) {

        'checking' =>
            'Conta à ordem',

        'savings' =>
            'Poupança',

        'cash' =>
            'Dinheiro',

        'credit_card' =>
            'Cartão de crédito',

        'investment' =>
            'Investimento',

        default =>
            'Outro'
    };
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

<title>Contas</title>


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
       LAYOUT
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
        margin-bottom: 25px;
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


    .page-title {
        margin: 0 0 5px 0;
        font-size: 28px;
    }


    /* =====================================================
       NAVEGAÇÃO
       ===================================================== */

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
        transition: background 0.15s;
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


    /* =====================================================
       SECÇÃO
       ===================================================== */

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


    .section-header h2 {
        margin: 0;
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


    /* =====================================================
       CONTAS
       ===================================================== */

    .account-cards {
        display: grid;
        grid-template-columns: repeat(
            auto-fit,
            minmax(280px, 1fr)
        );
        gap: 20px;
    }


    .account-card {
        border: 1px solid #ddd;
        padding: 20px;
        border-radius: 10px;
        background: #fafafa;
    }


    .account-card h3 {
        margin-top: 0;
        margin-bottom: 6px;
        font-size: 20px;
    }


    .account-type {
        margin-top: 0;
        color: #666;
        font-size: 14px;
    }


    .account-balance-label {
        margin-bottom: 5px;
        color: #666;
        font-size: 14px;
    }


    .account-balance {
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 28px;
        font-weight: bold;
    }


    .account-details {
        border-top: 1px solid #ddd;
        padding-top: 15px;
    }


    .account-detail {
        display: flex;
        justify-content: space-between;
        gap: 15px;
        margin-bottom: 10px;
    }


    .account-detail:last-child {
        margin-bottom: 0;
    }


    .account-detail-label {
        color: #666;
    }


    .account-actions {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #ddd;
    }


    .account-actions form {
        margin: 0;
    }


    /* =====================================================
       ESTADO
       ===================================================== */

    .status {
        display: inline-block;
        padding: 4px 9px;
        border-radius: 12px;
        font-size: 13px;
        border: 1px solid #aaa;
    }


    .status-active {
        font-weight: bold;
    }


    .status-inactive {
        color: #666;
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
       VAZIO
       ===================================================== */

    .empty-state {
        text-align: center;
        padding: 30px 10px;
    }


    .empty-state p {
        margin-bottom: 20px;
    }


    /* =====================================================
       RESPONSIVO
       ===================================================== */

    @media (max-width: 700px) {

        body {
            padding-bottom: 20px;
        }


        .container {
            width: 94%;
        }


        .header {
            padding: 15px 0;
        }


        .app-name {
            font-size: 21px;
        }


        .page-title {
            font-size: 24px;
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


        .account-cards {
            grid-template-columns: 1fr;
            gap: 15px;
        }


        .account-card {
            width: 100%;
        }


        .account-detail {
            flex-direction: column;
            gap: 3px;
        }


        .account-actions {
            flex-direction: column;
            align-items: stretch;
        }


        .account-actions .btn,
        .account-actions form,
        .account-actions button {
            width: 100%;
        }

    }


    @media (min-width: 701px) and (max-width: 1000px) {

        .account-cards {
            grid-template-columns: repeat(2, 1fr);
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


    <a href="accounts.php" class="active">
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


<!-- =====================================================
     CONTAS
     ===================================================== -->

<section class="section">


    <div class="section-header">

        <h2 class="page-title">
            Minhas contas
        </h2>


        <a
            href="account-create.php"
            class="btn btn-primary"
        >
            + Nova conta
        </a>

    </div>


    <?php if (empty($accounts)): ?>


        <div class="empty-state">

            <p>
                Ainda não tens contas cadastradas.
            </p>


            <a
                href="account-create.php"
                class="btn btn-primary"
            >
                + Criar primeira conta
            </a>

        </div>


    <?php else: ?>


        <div class="account-cards">


            <?php foreach ($accounts as $account): ?>


                <?php

                /*
                 * Converter valores monetários
                 * para cêntimos inteiros.
                 */

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


                /*
                 * Saldo atual:
                 *
                 * saldo inicial
                 * + receitas
                 * - despesas
                 */

                $currentCents =
                    $initialCents
                    + $incomeCents
                    - $expenseCents;


                /*
                 * Classe visual do saldo.
                 */

                if ($currentCents > 0) {

                    $balanceClass = 'positive';

                } elseif ($currentCents < 0) {

                    $balanceClass = 'negative';

                } else {

                    $balanceClass = 'neutral';
                }

                ?>


                <article class="account-card">


                    <h3>

                        <?= htmlspecialchars(
                            $account['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </h3>


                    <p class="account-type">

                        <?= htmlspecialchars(
                            account_type_label(
                                $account['type']
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    </p>


                    <p class="account-balance-label">
                        Saldo atual
                    </p>


                    <p
                        class="account-balance <?= $balanceClass ?>"
                    >

                        <?= format_money(
                            $currentCents
                        ) ?>

                    </p>


                    <div class="account-details">


                        <div class="account-detail">

                            <span class="account-detail-label">
                                Saldo inicial
                            </span>

                            <strong>
                                <?= format_money(
                                    $initialCents
                                ) ?>
                            </strong>

                        </div>


                        <div class="account-detail">

                            <span class="account-detail-label">
                                Receitas
                            </span>

                            <strong>
                                <?= format_money(
                                    $incomeCents
                                ) ?>
                            </strong>

                        </div>


                        <div class="account-detail">

                            <span class="account-detail-label">
                                Despesas
                            </span>

                            <strong>
                                <?= format_money(
                                    $expenseCents
                                ) ?>
                            </strong>

                        </div>


                        <div class="account-detail">

                            <span class="account-detail-label">
                                Estado
                            </span>


                            <?php if (
                                (int) $account['is_active'] === 1
                            ): ?>


                                <span class="status status-active">
                                    Ativa
                                </span>


                            <?php else: ?>


                                <span class="status status-inactive">
                                    Inativa
                                </span>


                            <?php endif; ?>


                        </div>


                    </div>


                    <div class="account-actions">


                        <a
                            href="account-edit.php?id=<?= (int) $account['id'] ?>"
                            class="btn"
                        >
                            Editar
                        </a>


                        <?php if (
                            (int) $account['is_active'] === 1
                        ): ?>


                            <form
                                method="POST"
                                action="account-deactivate.php"
                            >

                                <?= csrf_field() ?>


                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $account['id'] ?>"
                                >


                                <button
                                    type="submit"
                                    class="btn btn-danger"
                                    onclick="return confirm('Tens a certeza que queres desativar esta conta?');"
                                >
                                    Desativar
                                </button>

                            </form>


                        <?php else: ?>


                            <form
                                method="POST"
                                action="account-activate.php"
                            >

                                <?= csrf_field() ?>


                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $account['id'] ?>"
                                >


                                <button
                                    type="submit"
                                    class="btn"
                                >
                                    Ativar
                                </button>

                            </form>


                        <?php endif; ?>


                    </div>


                </article>


            <?php endforeach; ?>


        </div>


    <?php endif; ?>


</section>

</main>

</body>

</html>
