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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Categorias</title>
</head>

<body>

<h1>Minhas categorias</h1>

<p>
    <a href="index.php">Dashboard</a>
</p>

<p>
    <a href="category-create.php">+ Nova categoria</a>
</p>

<?php if (empty($categories)): ?>

    <p>
        Ainda não tens categorias.
    </p>

<?php else: ?>

    <table border="1" cellpadding="8">

        <thead>
            <tr>
                <th>Nome</th>
                <th>Tipo</th>
                <th>Ações</th>
            </tr>
        </thead>

        <tbody>

        <?php foreach ($categories as $category): ?>

            <tr>

                <td>
                    <?= htmlspecialchars(
                        $category['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>
                </td>

                <td>
                    <?= $category['type'] === 'income'
                        ? 'Receita'
                        : 'Despesa' ?>
                </td>

                <td>

                    <a
                        href="category-edit.php?id=<?= (int) $category['id'] ?>"
                    >
                        Editar
                    </a>

                    <form
                        method="POST"
                        action="category-delete.php"
                        style="display:inline;"
                    >

                        <?= csrf_field() ?>

                        <input
                            type="hidden"
                            name="id"
                            value="<?= (int) $category['id'] ?>"
                        >

                        <button type="submit">
                            Apagar
                        </button>

                    </form>

                </td>

            </tr>

        <?php endforeach; ?>

        </tbody>

    </table>

<?php endif; ?>

</body>
</html>
