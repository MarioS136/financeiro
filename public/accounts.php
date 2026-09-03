<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';
require_once __DIR__ . '/../src/Finance/money.php';

require_login();

$userId = current_user_id();

/*
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

</head>

<body>

<h1>Minhas contas</h1>

<p>
    <a href="index.php">Dashboard</a>
</p>

<p>
    <a href="account-create.php">+ Nova conta</a>
</p>

<hr>


<?php if (empty($accounts)): ?>

    <p>
        Ainda não tens contas.
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
                <th>Ações</th>
            </tr>

        </thead>

        <tbody>

        <?php foreach ($accounts as $account): ?>

            <?php

            /*
             * Converter todos os valores monetários
             * para cêntimos inteiros.
             */

            $initialCents = decimal_to_cents(
                $account['initial_balance']
            );

            $incomeCents = decimal_to_cents(
                $account['total_income']
            );

            $expenseCents = decimal_to_cents(
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

                    <?php if ($account['type'] === 'checking'): ?>

                        Conta à ordem

                    <?php elseif ($account['type'] === 'savings'): ?>

                        Poupança

                    <?php else: ?>

                        <?= htmlspecialchars(
                            $account['type'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>

                    <?php endif; ?>

                </td>


                <td>
                    <?= format_money($initialCents) ?>
                </td>


                <td>
                    <?= format_money($incomeCents) ?>
                </td>


                <td>
                    <?= format_money($expenseCents) ?>
                </td>


                <td>

                    <strong>
                        <?= format_money($currentCents) ?>
                    </strong>

                </td>


                <td>

                    <?php if ((int) $account['is_active'] === 1): ?>

                        Ativa

                    <?php else: ?>

                        Inativa

                    <?php endif; ?>

                </td>


                <td>

                    <a
                        href="account-edit.php?id=<?= (int) $account['id'] ?>"
                    >
                        Editar
                    </a>


                    <?php if ((int) $account['is_active'] === 1): ?>

                        <form
                            method="POST"
                            action="account-deactivate.php"
                            style="display:inline;"
                        >

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int) $account['id'] ?>"
                            >

                            <button type="submit">
                                Desativar
                            </button>

                        </form>


                    <?php else: ?>


                        <form
                            method="POST"
                            action="account-activate.php"
                            style="display:inline;"
                        >

                            <?= csrf_field() ?>

                            <input
                                type="hidden"
                                name="id"
                                value="<?= (int) $account['id'] ?>"
                            >

                            <button type="submit">
                                Ativar
                            </button>

                        </form>


                    <?php endif; ?>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

<?php endif; ?>


</body>

</html>