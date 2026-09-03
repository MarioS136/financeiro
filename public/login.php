<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../src/Security/csrf.php';

$email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // 1. Verificar CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Pedido inválido. Atualiza a página e tenta novamente.';
    }

    // 2. Receber dados
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // 3. Validar
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email ou password inválidos.';
    }

    if ($password === '') {
        $errors[] = 'Email ou password inválidos.';
    }

    // 4. Procurar utilizador
    if (empty($errors)) {

        $stmt = $pdo->prepare(
            'SELECT id, name, email, password, role, is_active
             FROM users
             WHERE email = :email
             LIMIT 1'
        );

        $stmt->execute([
            'email' => $email
        ]);

        $user = $stmt->fetch();

        // 5. Verificar utilizador + password
        if (
            !$user ||
            !$user['is_active'] ||
            !password_verify($password, $user['password'])
        ) {
            $errors[] = 'Email ou password inválidos.';
        }
    }

    // 6. Login bem-sucedido
    if (empty($errors)) {

        session_regenerate_id(true);

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['authenticated'] = true;

        header('Location: index.php');
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Login</title>
</head>

<body>

    <h1>Login</h1>

    <?php if (!empty($errors)): ?>

        <div>
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
        </div>

    <?php endif; ?>

    <form method="POST" action="">

        <?= csrf_field() ?>

        <div>
            <label for="email">
                Email
            </label>

            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars(
                    $email,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
                maxlength="255"
                required
            >
        </div>

        <br>

        <div>
            <label for="password">
                Password
            </label>

            <input
                type="password"
                id="password"
                name="password"
                maxlength="255"
                required
            >
        </div>

        <br>

        <button type="submit">
            Entrar
        </button>

    </form>

    <p>
        Ainda não tens conta?
        <a href="register.php">Criar conta</a>
    </p>

</body>
</html>
