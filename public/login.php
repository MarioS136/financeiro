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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Login</title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f5f6f8;
            color: #222;

            min-height: 100vh;

            display: flex;
            align-items: center;
            justify-content: center;

            padding: 20px;
        }


        .login-container {
            width: min(420px, 100%);
        }


        .app-name {
            text-align: center;
            margin: 0 0 20px 0;
            font-size: 26px;
        }


        .login-section {
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 28px;
        }


        .login-section h1 {
            margin: 0 0 25px 0;
            text-align: center;
            font-size: 24px;
        }


        .form-group {
            margin-bottom: 18px;
        }


        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-weight: bold;
            font-size: 14px;
        }


        .form-group input {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: #ffffff;
            font-size: 15px;
        }


        .form-group input:focus {
            outline: none;
            border-color: #999;
        }


        .errors {
            margin-bottom: 20px;
            padding: 15px 18px;
            border: 1px solid #d8b4b4;
            border-radius: 6px;
            background: #faf3f3;
        }


        .errors ul {
            margin: 0;
            padding-left: 20px;
        }


        .errors li {
            margin-bottom: 5px;
        }


        .errors li:last-child {
            margin-bottom: 0;
        }


        .btn {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background: #ffffff;
            cursor: pointer;
            font-size: 15px;
            font-weight: bold;
        }


        .btn:hover {
            background: #eeeeee;
        }


        .register-link {
            margin: 20px 0 0 0;
            text-align: center;
            color: #555;
            font-size: 14px;
        }


        .register-link a {
            color: inherit;
            font-weight: bold;
        }


        @media (max-width: 500px) {

            body {
                padding: 15px;
            }


            .login-section {
                padding: 22px;
            }


            .app-name {
                font-size: 23px;
            }

        }

    </style>

</head>

<body>


<div class="login-container">


    <h1 class="app-name">
        Financeiro
    </h1>


    <section class="login-section">


        <h1>
            Login
        </h1>


        <?php if (!empty($errors)): ?>

            <div class="errors">

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


        <form
            method="POST"
            action=""
        >

            <?= csrf_field() ?>


            <div class="form-group">

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
                    autocomplete="email"
                    required
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>


                <input
                    type="password"
                    id="password"
                    name="password"
                    maxlength="255"
                    autocomplete="current-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn"
            >
                Entrar
            </button>

        </form>


        <p class="register-link">

            Ainda não tens conta?

            <a href="register.php">
                Criar conta
            </a>

        </p>


    </section>


</div>


</body>

</html>