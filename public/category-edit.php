<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/auth.php';
require_once __DIR__ . '/../src/Security/csrf.php';

require_login();

$userId = current_user_id();

$categoryId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$categoryId || $categoryId < 1) {
    http_response_code(400);
    exit('Categoria inválida.');
}

$stmt = $pdo->prepare(
    'SELECT id, name, type
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
    http_response_code(404);
    exit('Categoria não encontrada.');
}

$errors = [];

$name = $category['name'];
$type = $category['type'];

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
        $errors[] = 'O nome é obrigatório.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'O nome deve ter entre 2 e 100 caracteres.';
    }

    if (!in_array($type, $allowedTypes, true)) {
        $errors[] = 'Tipo inválido.';
    }

    /*
     * Evitar duplicados.
     */
    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'SELECT id
             FROM categories
             WHERE user_id = :user_id
               AND name = :name
               AND type = :type
               AND id <> :id
             LIMIT 1'
        );

        $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'type' => $type,
            'id' => $categoryId
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Essa categoria já existe.';
        }
    }

    /*
     * Atualizar.
     */
    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'UPDATE categories
             SET name = :name,
                 type = :type
             WHERE id = :id
               AND user_id = :user_id'
        );

        $stmt->execute([
            'name' => $name,
            'type' => $type,
            'id' => $categoryId,
            'user_id' => $userId
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

    <title>Editar categoria</title>
</head>

<body>

<h1>Editar categoria</h1>

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
        Guardar alterações
    </button>

</form>

<p>
    <a href="categories.php">Voltar</a>
</p>

</body>
</html>

