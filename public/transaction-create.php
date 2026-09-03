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
 *
 * Apenas contas do utilizador que estejam ativas
 * podem receber novas transações.
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
 *
 * Buscamos as categorias do utilizador.
 *
 * O JavaScript irá filtrar visualmente as categorias
 * conforme o tipo escolhido.
 *
 * O PHP continuará a validar o tipo no servidor.
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
     * 1. CSRF
     */

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {

        $errors[] =
            'Pedido inválido. Atualiza a página e tenta novamente.';
    }


    /*
     * 2. Receber dados
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
     * 3. Validar tipo
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
     * 4. Validar conta
     */

    if (!$accountId || $accountId < 1) {

        $errors[] =
            'Seleciona uma conta válida.';
    }


    /*
     * 5. Validar categoria
     */

    if (!$categoryId || $categoryId < 1) {

        $errors[] =
            'Seleciona uma categoria válida.';
    }


    /*
     * 6. Validar valor
     *
     * Aceitamos:
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
     * 7. Validar descrição
     */

    if (mb_strlen($description) > 255) {

        $errors[] =
            'A descrição não pode ultrapassar 255 caracteres.';
    }


    /*
     * 8. Validar data
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
     * A conta tem obrigatoriamente de:
     *
     * - pertencer ao utilizador;
     * - estar ativa.
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
     * 10. VERIFICAR CATEGORIA
     * =========================================================
     *
     * A categoria tem obrigatoriamente de:
     *
     * - pertencer ao utilizador;
     * - corresponder ao tipo da transação.
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
     * 11. CRIAR TRANSAÇÃO
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

</head>

<body>

<h1>Nova transação</h1>


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
            placeholder="0.00"
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
        Guardar transação
    </button>

</form>


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
