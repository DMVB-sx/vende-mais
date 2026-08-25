<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/conexao.php';

$token = trim($_GET['token'] ?? '');
$status = 'erro';
$mensagem = 'Token de verificação inválido ou inexistente.';

if (!empty($token)) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, email_verificado, token_expira 
            FROM usuarios 
            WHERE token_verificacao = ? 
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            if ((int)$usuario['email_verificado'] === 1) {
                $status = 'sucesso';
                $mensagem = 'Seu e-mail já foi verificado anteriormente!';
            } elseif (!empty($usuario['token_expira']) && strtotime($usuario['token_expira']) < time()) {
                $status = 'erro';
                $mensagem = 'Este link de confirmação expirou (validade de 24 horas). Tente se cadastrar novamente para receber um novo link.';
            } else {
                // Ativa a conta e limpa o token de uso único
                $stmtUpdate = $pdo->prepare("
                    UPDATE usuarios 
                    SET email_verificado = 1, token_verificacao = NULL, token_expira = NULL 
                    WHERE id = ?
                ");
                $stmtUpdate->execute([$usuario['id']]);

                $status = 'sucesso';
                $mensagem = 'E-mail confirmado com sucesso! Sua conta está liberada.';
            }
        }
    } catch (Throwable $e) {
        error_log($e->getMessage());
        $status = 'erro';
        $mensagem = 'Ocorreu um erro ao processar a confirmação. Tente novamente mais tarde.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Validação de Conta | Vende+</title>
    
    <!-- FAVICON -->
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">

    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #000000;
            color: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 16px;
        }

        .auth-container {
            width: 100%;
            max-width: 420px;
        }

        .auth-card {
            background-color: #09090b;
            border: 1px solid #18181b;
            border-radius: 14px;
            padding: 36px 28px;
            text-align: center;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        }

        .status-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            margin: 0 auto 20px auto;
        }

        .icon-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: #10b981;
            border: 1px solid rgba(16, 185, 129, 0.25);
        }

        .icon-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.25);
        }

        .title {
            font-size: 22px;
            font-weight: 800;
            color: #ffffff;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .description {
            font-size: 13.5px;
            color: #a1a1aa;
            line-height: 1.5;
            margin-bottom: 26px;
        }

        .btn-action {
            display: block;
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .btn-success {
            background-color: #10b981;
            color: #000000;
        }

        .btn-success:hover {
            background-color: #059669;
        }

        .btn-error {
            background-color: #18181b;
            color: #ffffff;
            border: 1px solid #27272a;
        }

        .btn-error:hover {
            background-color: #27272a;
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-card">
        <?php if ($status === 'sucesso'): ?>
            <div class="status-icon icon-success">
                ✓
            </div>
            <h1 class="title">Conta Confirmada!</h1>
            <p class="description"><?= htmlspecialchars($mensagem) ?></p>
            <a href="login.php?msg=email_confirmado" class="btn-action btn-success">
                Fazer Login
            </a>
        <?php else: ?>
            <div class="status-icon icon-error">
                ✕
            </div>
            <h1 class="title">Falha na Ativação</h1>
            <p class="description"><?= htmlspecialchars($mensagem) ?></p>
            <a href="login.php" class="btn-action btn-error">
                Ir para o Login
            </a>
        <?php endif; ?>
    </div>
</div>

</body>
</html>