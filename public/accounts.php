<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';
require_once __DIR__ . '/../src/Finance/money.php';

require_login();

$userId = current_user_id();

$userName = $_SESSION['user_name'] ?? 'Utilizador';
$userRole = $_SESSION['user_role'] ?? 'user';


/*
 * =========================================================
 * BUSCAR CONTAS
 * =========================================================
 *
 * Saldo atual:
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

     ORDER BY
        a.is_active DESC,
        a.name ASC'
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


    .user-info {
        text-align: right;
        font-size: 14px;
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
        font-size: 14px;
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
        margin-bottom: 25px;
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
        background: #ffffff;
        cursor: pointer;
        font-size: 14px;
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
        grid-template-columns:
            repeat(
                auto-fit,
                minmax(280px, 1fr)
            );
        gap: 20px;
    }


    .account-card {
        border: 1px solid #ddd;
        border-radius: 10px;
        padding: 20px;
        background: #fafafa;
        min-width: 0;
    }


    .account-card.inactive {
        opacity: 0.75;
    }


    .account-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 15px;
    }


    .account-card h3 {
        margin: 0 0 6px 0;
        font-size: 20px;
        overflow-wrap: anywhere;
    }


    .account-type {
        margin: 0;
        font-size: 14px;
        color: #666;
    }


    /* =====================================================
       ESTADO
       ===================================================== */

    .status {
        display: inline-block;
        padding: 4px 9px;
        border-radius: 12px;
        font-size: 12px;
        white-space: nowrap;
    }


    .status-active {
        border: 1px solid #999;
    }


    .status-inactive {
        border: 1px solid #999;
    }


    /* =====================================================
       SALDO
       ===================================================== */

    .account-balance-area {
        margin: 25px 0;
        padding: 18px;
        border-radius: 8px;
        background: #ffffff;
        border: 1px solid #ddd;
    }


    .account-balance-label {
        margin: 0 0 6px 0;
        font-size: 13px;
        color: #666;
    }


    .account-balance {
        margin: 0;
        font-size: 30px;
        font-weight: bold;
        overflow-wrap: anywhere;
    }


    /* =====================================================
       DETALHES FINANCEIROS
       ===================================================== */

    .account-details {
        display: grid;
        grid-template-columns:
            repeat(2, minmax(0, 1fr));
        gap: 12px;
    }


    .detail {
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 7px;
        background: #ffffff;
    }


    .detail-label {
        display: block;
        margin-bottom: 5px;
        font-size: 12px;
        color: #666;
    }


    .detail-value {
        font-weight: bold;
        overflow-wrap: anywhere;
    }


    /* =====================================================
       AÇÕES
       ===================================================== */

    .account-actions {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }


    .account-actions form {
        margin: 0;
    }


    /* =====================================================
       ESTADO VAZIO
       ===================================================== */

    .empty-state {
        text-align: center;
        padding: 35px 15px;
    }


    .empty-state p {
        margin-bottom: 20px;
    }


    /* =====================================================
       RESPONSIVO
       ===================================================== */

    @media (max-width: 700px) {

        body {
            padding: 0;
        }


        .container {
            width: 94%;
        }


        .header-content {
            flex-direction: column;
            align-items: flex-start;
        }


        .user-info {
            text-align: left;
        }


        .navigation {
            display: grid;
            grid-template-columns:
                repeat(2, minmax(0, 1fr));
        }


        .navigation a {
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


        .account-cards {
            grid-template-columns: 1fr;
            gap: 15px;
        }


        .account-card {
            width: 100%;
        }


        .account-card-header {
            flex-direction: column;
        }


        .account-details {
            grid-template-columns: 1fr;
        }


        .account-actions {
            flex-direction: column;
        }


        .account-actions .btn,
        .account-actions button {
            width: 100%;
            text-align: center;
        }

    }

</style>

</head>

<body>

<header class="header">

<div class="container header-content">

    <h1 class="app-name">
        Gestão Financeira
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


<!-- =====================================================
     NAVEGAÇÃO
     ===================================================== -->

<nav class="navigation">

    <a href="index.php">
        Dashboard
    </a>


    <a
        href="transactions.php"
    >
        Transações
    </a>


    <a
        href="accounts.php"
        class="active"
    >
        Contas
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

        <h2>
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
                Ainda não tens nenhuma conta.
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
                 * Converter valores da BD
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
                 * Saldo atual.
                 */

                $currentCents =
                    $initialCents
                    + $incomeCents
                    - $expenseCents;


                $isActive =
                    (int) $account['is_active'] === 1;

                ?>


                <article
                    class="account-card
                    <?= !$isActive
                        ? 'inactive'
                        : '' ?>"
                >


                    <div class="account-card-header">


                        <div>

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

                        </div>


                        <?php if ($isActive): ?>

                            <span
                                class="status status-active"
                            >
                                Ativa
                            </span>

                        <?php else: ?>

                            <span
                                class="status status-inactive"
                            >
                                Inativa
                            </span>

                        <?php endif; ?>


                    </div>


                    <!-- SALDO -->

                    <div class="account-balance-area">

                        <p class="account-balance-label">
                            Saldo atual
                        </p>


                        <p class="account-balance">

                            <?= format_money(
                                $currentCents
                            ) ?>

                        </p>

                    </div>


                    <!-- DETALHES -->

                    <div class="account-details">


                        <div class="detail">

                            <span class="detail-label">
                                Saldo inicial
                            </span>

                            <span class="detail-value">

                                <?= format_money(
                                    $initialCents
                                ) ?>

                            </span>

                        </div>


                        <div class="detail">

                            <span class="detail-label">
                                Receitas
                            </span>

                            <span class="detail-value">

                                + <?= format_money(
                                    $incomeCents
                                ) ?>

                            </span>

                        </div>


                        <div class="detail">

                            <span class="detail-label">
                                Despesas
                            </span>

                            <span class="detail-value">

                                - <?= format_money(
                                    $expenseCents
                                ) ?>

                            </span>

                        </div>


                    </div>


                    <!-- AÇÕES -->

                    <div class="account-actions">


                        <a
                            href="account-edit.php?id=<?= (int) $account['id'] ?>"
                            class="btn"
                        >
                            Editar
                        </a>


                        <?php if ($isActive): ?>


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
