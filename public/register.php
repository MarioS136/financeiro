<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/csrf.php';

$errors = [];
$success = '';

$name = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    /*
     * 1. Verificar CSRF
     */
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Pedido inválido. Atualiza a página e tenta novamente.';
    }

    /*
     * 2. Receber e limpar dados
     */
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirmation = $_POST['password_confirmation'] ?? '';

    /*
     * 3. Validar nome
     */
    if ($name === '') {
        $errors[] = 'O nome é obrigatório.';
    } elseif (mb_strlen($name) < 2 || mb_strlen($name) > 100) {
        $errors[] = 'O nome deve ter entre 2 e 100 caracteres.';
    }

    /*
     * 4. Validar email
     */
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Introduz um email válido.';
    } elseif (mb_strlen($email) > 255) {
        $errors[] = 'O email é demasiado longo.';
    }

    /*
     * 5. Validar password
     */
    if (strlen($password) < 8) {
        $errors[] = 'A password deve ter pelo menos 8 caracteres.';
    }

    if (strlen($password) > 255) {
        $errors[] = 'A password é demasiado longa.';
    }

    if ($password !== $password_confirmation) {
        $errors[] = 'As passwords não coincidem.';
    }

    /*
     * 6. Se não houver erros, verificar email
     */
    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'SELECT id
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $stmt->execute([
            'email' => $email
        ]);

        if ($stmt->fetch()) {
            $errors[] = 'Não foi possível criar a conta com esses dados.';
        }
    }

    /*
     * 7. Criar utilizador
     */
    if (empty($errors)) {

        $password_hash = password_hash(
            $password,
            PASSWORD_DEFAULT
        );

        $stmt = $pdo->prepare(
            'INSERT INTO users
                (name, email, password)
             VALUES
                (:name, :email, :password)'
        );

        $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $password_hash
        ]);

        $success = 'Conta criada com sucesso.';

        $name = '';
        $email = '';
    }
}

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Criar conta</title>
</head>

<body>

    <h1>Criar conta</h1>

    <?php if (!empty($errors)): ?>
        <div>
            <ul>
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if ($success !== ''): ?>
        <p>
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </p>
    <?php endif; ?>

    <form method="POST" action="">

        <?= csrf_field() ?>

        <div>
            <label for="name">Nome</label>

            <input
                type="text"
                id="name"
                name="name"
                value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>"
                maxlength="100"
                required
            >
        </div>

        <br>

        <div>
            <label for="email">Email</label>

            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>"
                maxlength="255"
                required
            >
        </div>

        <br>

        <div>
            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                minlength="8"
                maxlength="255"
                required
            >
        </div>

        <br>

        <div>
            <label for="password_confirmation">
                Confirmar password
            </label>

            <input
                type="password"
                id="password_confirmation"
                name="password_confirmation"
                minlength="8"
                maxlength="255"
                required
            >
        </div>

        <br>

        <button type="submit">
            Criar conta
        </button>

    </form>

</body>
</html>
