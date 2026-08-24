<?php

require_once 'config/conexao.php';

$token = $_GET['token'] ?? '';

$mensagem = '';
$sucesso = false;

if (empty($token)) {
    $mensagem = 'Link de verificação inválido.';
} else {
    try {
        $stmt = $pdo->prepare("
            SELECT id, email, email_verificado, token_expira
            FROM usuarios
            WHERE token_verificacao = ?
            LIMIT 1
        ");

        $stmt->execute([$token]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            $mensagem = 'Este link de verificação é inválido ou já foi utilizado.';
        } elseif ((int)$usuario['email_verificado'] === 1) {
            $mensagem = 'Este e-mail já foi verificado.';
            $sucesso = true;
        } elseif (
            empty($usuario['token_expira']) ||
            strtotime($usuario['token_expira']) < time()
        ) {
            $mensagem = 'Este link de verificação expirou. Solicite um novo link.';
        } else {

            $stmtUpdate = $pdo->prepare("
                UPDATE usuarios
                SET
                    email_verificado = 1,
                    token_verificacao = NULL,
                    token_expira = NULL
                WHERE id = ?
            ");

            $stmtUpdate->execute([$usuario['id']]);

            $mensagem = 'E-mail verificado com sucesso! Agora você já pode entrar na sua conta.';
            $sucesso = true;
        }

    } catch (Throwable $e) {
        error_log($e->getMessage());
        $mensagem = 'Ocorreu um erro ao verificar seu e-mail. Tente novamente.';
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Verificação de E-mail | Vende+</title>

    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background: #000;
            color: #fff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .card {
            width: 100%;
            max-width: 420px;
            background: #09090b;
            border: 1px solid #18181b;
            border-radius: 12px;
            padding: 35px 28px;
            text-align: center;
        }

        .logo {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 25px;
        }

        .logo span {
            color: #10b981;
        }

        .icon {
            font-size: 45px;
            margin-bottom: 18px;
        }

        h1 {
            font-size: 21px;
            margin-bottom: 12px;
        }

        p {
            color: #a1a1aa;
            font-size: 14px;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 20px;
            background: #10b981;
            color: #000;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
        }

        .erro {
            color: #f87171;
        }

        .ok {
            color: #34d399;
        }
    </style>
</head>

<body>

<div class="card">

    <div class="logo">
        vende<span>+</span>
    </div>

    <?php if ($sucesso): ?>

        <div class="icon">✓</div>

        <h1 class="ok">E-mail verificado!</h1>

        <p>
            Sua conta foi confirmada com sucesso.
            Agora você já pode acessar o Vende+.
        </p>

        <a href="login.php" class="btn">
            Entrar na minha conta
        </a>

    <?php else: ?>

        <div class="icon">⚠️</div>

        <h1 class="erro">Não foi possível verificar</h1>

        <p>
            <?= htmlspecialchars($mensagem) ?>
        </p>

        <a href="login.php" class="btn">
            Voltar para o login
        </a>

    <?php endif; ?>

</div>

</body>
</html>