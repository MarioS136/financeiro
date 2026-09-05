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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Criar conta</title>


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


        .register-container {
            width: min(420px, 100%);
        }


        .app-name {
            text-align: center;
            margin: 0 0 20px 0;
            font-size: 26px;
        }


        .register-section {
            background: #ffffff;
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 28px;
        }


        .register-section h1 {
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


        .success {
            margin-bottom: 20px;
            padding: 15px 18px;
            border: 1px solid #b8d8c0;
            border-radius: 6px;
            background: #f3faf5;
        }


        .success p {
            margin: 0;
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


        .login-link {
            margin: 20px 0 0 0;
            text-align: center;
            color: #555;
            font-size: 14px;
        }


        .login-link a {
            color: inherit;
            font-weight: bold;
        }


        @media (max-width: 500px) {

            body {
                padding: 15px;
            }


            .register-section {
                padding: 22px;
            }


            .app-name {
                font-size: 23px;
            }

        }

    </style>

</head>

<body>


<div class="register-container">


    <h1 class="app-name">
        Financeiro
    </h1>


    <section class="register-section">


        <h1>
            Criar conta
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


        <?php if ($success !== ''): ?>

            <div class="success">

                <p>

                    <?= htmlspecialchars(
                        $success,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </p>

            </div>

        <?php endif; ?>


        <form
            method="POST"
            action=""
        >

            <?= csrf_field() ?>


            <div class="form-group">

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
                    autocomplete="name"
                    required
                >

            </div>


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
                    minlength="8"
                    maxlength="255"
                    autocomplete="new-password"
                    required
                >

            </div>


            <div class="form-group">

                <label for="password_confirmation">
                    Confirmar password
                </label>


                <input
                    type="password"
                    id="password_confirmation"
                    name="password_confirmation"
                    minlength="8"
                    maxlength="255"
                    autocomplete="new-password"
                    required
                >

            </div>


            <button
                type="submit"
                class="btn"
            >
                Criar conta
            </button>

        </form>


        <p class="login-link">

            Já tens uma conta?

            <a href="login.php">
                Entrar
            </a>

        </p>


    </section>


</div>


</body>

</html>