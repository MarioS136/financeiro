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

    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Pedido inválido. Atualiza a página e tenta novamente.';
    }

    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? '';

    if ($name === '') {
        $errors[] = 'O nome da categoria é obrigatório.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'O nome deve ter entre 2 e 100 caracteres.';
    }

    if (!in_array($type, $allowedTypes, true)) {
        $errors[] = 'Tipo de categoria inválido.';
    }

    /*
     * Verificar se já existe.
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
            'user_id' => $userId,
            'name' => $name,
            'type' => $type
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Essa categoria já existe.';
        }
    }

    /*
     * Criar.
     */
    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'INSERT INTO categories
                (user_id, name, type)
             VALUES
                (:user_id, :name, :type)'
        );

        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'type' => $type
        ]);

        header('Location: categories.php');
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Nova categoria</title>
</head>

<body>

<h1>Nova categoria</h1>

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

    <div>

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

    <br>

    <div>

        <label for="type">
            Tipo
        </label>

        <select id="type" name="type" required>

            <option
                value="expense"
                <?= $type === 'expense' ? 'selected' : '' ?>
            >
                Despesa
            </option>

            <option
                value="income"
                <?= $type === 'income' ? 'selected' : '' ?>
            >
                Receita
            </option>

        </select>

    </div>

    <br>

    <button type="submit">
        Criar categoria
    </button>

</form>

<p>
    <a href="categories.php">Voltar</a>
</p>

</body>
</html>
