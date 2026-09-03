<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';
require_once __DIR__ . '/../src/Security/audit.php';
require_once __DIR__ . '/../src/Finance/money.php';

require_login();

$userId = current_user_id();


/*
 * =========================================================
 * OBTER ID DA TRANSAÇÃO
 * =========================================================
 */

$transactionId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$transactionId || $transactionId < 1) {

    http_response_code(400);
    exit('Transação inválida.');
}


/*
 * =========================================================
 * BUSCAR TRANSAÇÃO
 * =========================================================
 *
 * Apenas a transação pertencente ao utilizador autenticado.
 */

$stmt = $pdo->prepare(
    'SELECT
        id,
        account_id,
        category_id,
        type,
        amount,
        description,
        transaction_date
     FROM transactions
     WHERE id = :id
       AND user_id = :user_id
     LIMIT 1'
);

$stmt->execute([
    'id' => $transactionId,
    'user_id' => $userId
]);

$transaction = $stmt->fetch();


if (!$transaction) {

    http_response_code(404);
    exit('Transação não encontrada.');
}


/*
 * =========================================================
 * VALORES INICIAIS
 * =========================================================
 */

$type = $transaction['type'];

$accountId =
    (int) $transaction['account_id'];

$categoryId =
    (int) $transaction['category_id'];

$amount =
    $transaction['amount'];

$description =
    $transaction['description'] ?? '';

$transactionDate =
    $transaction['transaction_date'];

$errors = [];


/*
 * =========================================================
 * BUSCAR CONTAS
 * =========================================================
 *
 * A conta atualmente associada aparece mesmo que esteja
 * inativa.
 */

$stmt = $pdo->prepare(
    'SELECT
        id,
        name,
        is_active
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
 * BUSCAR CATEGORIAS
 * =========================================================
 */

$stmt = $pdo->prepare(
    'SELECT
        id,
        name,
        type
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
     * 1. CSRF
     */

    if (!verify_csrf_token(
        $_POST['csrf_token'] ?? null
    )) {

        $errors[] =
            'Pedido inválido. Atualiza a página e tenta novamente.';
    }


    /*
     * 2. RECEBER DADOS
     */

    $type =
        $_POST['type'] ?? '';

    $accountId = filter_var(
        $_POST['account_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $categoryId = filter_var(
        $_POST['category_id'] ?? '',
        FILTER_VALIDATE_INT
    );

    $amount =
        trim($_POST['amount'] ?? '');

    $description =
        trim($_POST['description'] ?? '');

    $transactionDate =
        $_POST['transaction_date'] ?? '';


    /*
     * 3. VALIDAR TIPO
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
     * 4. VALIDAR CONTA
     */

    if (!$accountId || $accountId < 1) {

        $errors[] =
            'Seleciona uma conta válida.';
    }


    /*
     * 5. VALIDAR CATEGORIA
     */

    if (!$categoryId || $categoryId < 1) {

        $errors[] =
            'Seleciona uma categoria válida.';
    }


    /*
     * 6. VALIDAR VALOR
     *
     * Aceita:
     *
     * 10
     * 10.50
     * 10,50
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
     * 7. VALIDAR DESCRIÇÃO
     */

    if (mb_strlen($description) > 255) {

        $errors[] =
            'A descrição não pode ultrapassar 255 caracteres.';
    }


    /*
     * 8. VALIDAR DATA
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
     * 9. VERIFICAR CONTA
     * =========================================================
     *
     * A conta tem de pertencer ao utilizador.
     *
     * Se for a conta atual, pode continuar inativa.
     *
     * Se for uma conta diferente, tem de estar ativa.
     */

    if ($accountId) {

        $stmt = $pdo->prepare(
            'SELECT
                id,
                is_active
             FROM accounts
             WHERE id = :id
               AND user_id = :user_id
             LIMIT 1'
        );

        $stmt->execute([
            'id' => $accountId,
            'user_id' => $userId
        ]);

        $account = $stmt->fetch();


        if (!$account) {

            $errors[] =
                'Conta inválida.';

        } else {

            $isCurrentAccount =
                $accountId ===
                (int) $transaction['account_id'];


            if (
                !$isCurrentAccount &&
                (int) $account['is_active'] !== 1
            ) {

                $errors[] =
                    'Não podes mover a transação para uma conta inativa.';
            }
        }
    }


    /*
     * =========================================================
     * 10. VERIFICAR CATEGORIA
     * =========================================================
     *
     * A categoria tem de:
     *
     * - pertencer ao utilizador;
     * - corresponder ao tipo da transação.
     */

    if ($categoryId) {

        $stmt = $pdo->prepare(
            'SELECT
                id,
                type
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

        } elseif (
            $category['type'] !== $type
        ) {

            $errors[] =
                'A categoria não corresponde ao tipo da transação.';
        }
    }


    /*
     * =========================================================
     * 11. ATUALIZAR TRANSAÇÃO
     * =========================================================
     */

    if (empty($errors)) {

        try {

            $pdo->beginTransaction();


            $stmt = $pdo->prepare(
                'UPDATE transactions
                 SET
                    account_id = :account_id,
                    category_id = :category_id,
                    type = :type,
                    amount = :amount,
                    description = :description,
                    transaction_date = :transaction_date
                 WHERE id = :id
                   AND user_id = :user_id'
            );


            $stmt->execute([
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
                    $transactionDate,

                'id' =>
                    $transactionId,

                'user_id' =>
                    $userId
            ]);


            /*
             * =================================================
             * AUDITORIA
             * =================================================
             */

            audit_log(
                $pdo,
                'transaction_updated',
                'transaction',
                $transactionId,
                [
                    'old' => [
                        'account_id' =>
                            (int) $transaction['account_id'],

                        'category_id' =>
                            (int) $transaction['category_id'],

                        'type' =>
                            $transaction['type'],

                        'amount' =>
                            $transaction['amount'],

                        'description' =>
                            $transaction['description'],

                        'transaction_date' =>
                            $transaction['transaction_date']
                    ],

                    'new' => [
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
                    ]
                ]
            );


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
                'Não foi possível atualizar a transação.';
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

    <title>Editar transação</title>

</head>

<body>

<h1>Editar transação</h1>


<p>

    <a href="index.php">
        Dashboard
    </a>

    |

    <a href="transactions.php">
        Transações
    </a>

</p>


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


<form method="POST">

    <?= csrf_field() ?>


    <!-- TIPO -->

    <div>

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


    <br>


    <!-- CONTA -->

    <div>

        <label for="account_id">
            Conta
        </label>


        <select
            id="account_id"
            name="account_id"
            required
        >

            <?php foreach ($accounts as $account): ?>

                <option
                    value="<?= (int) $account['id'] ?>"
                    <?= $accountId ===
                        (int) $account['id']
                        ? 'selected'
                        : '' ?>
                >

                    <?= htmlspecialchars(
                        $account['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>


                    <?php if (
                        (int) $account['is_active'] !== 1
                    ): ?>

                        (inativa)

                    <?php endif; ?>

                </option>

            <?php endforeach; ?>

        </select>

    </div>


    <br>


    <!-- CATEGORIA -->

    <div>

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
                    <?= $categoryId ===
                        (int) $category['id']
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


    <br>


    <!-- VALOR -->

    <div>

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
            required
        >

    </div>


    <br>


    <!-- DATA -->

    <div>

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


    <br>


    <!-- DESCRIÇÃO -->

    <div>

        <label for="description">
            Descrição
        </label>


        <textarea
            id="description"
            name="description"
            maxlength="255"
        ><?= htmlspecialchars(
            $description,
            ENT_QUOTES,
            'UTF-8'
        ) ?></textarea>

    </div>


    <br>


    <button type="submit">
        Guardar alterações
    </button>

</form>


<p>

    <a href="transactions.php">
        Cancelar
    </a>

</p>


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


        /*
         * Executar imediatamente ao abrir a página.
         *
         * Assim, se a transação for uma despesa,
         * só aparecem categorias de despesa.
         */

        filterCategories();

    }
);

</script>


</body>

</html>