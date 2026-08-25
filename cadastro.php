<?php
require_once 'config/conexao.php';

// Se o usuário já estiver logado, redireciona para o painel
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('validar_csrf')) {
        validar_csrf();
    }

    $nome_usuario = trim($_POST['nome_usuario'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $senha        = $_POST['senha'] ?? '';

    if (!empty($nome_usuario) && !empty($email) && !empty($senha)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = "Por favor, informe um endereço de e-mail válido.";
        } elseif (strlen($senha) < 6) {
            $erro = "A senha deve ter pelo menos 6 caracteres.";
        } else {
            try {
                // 1. Verifica se o e-mail já existe
                $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? LIMIT 1");
                $stmtCheck->execute([$email]);

                if ($stmtCheck->rowCount() > 0) {
                    $erro = "Este e-mail já está cadastrado. Tente fazer login.";
                } else {
                    $pdo->beginTransaction();

                    // 2. Cria a empresa inicial automaticamente usando o nome do usuário
                    $primeiro_nome = explode(' ', $nome_usuario)[0];
                    $nome_empresa_padrao = "Empresa de " . $primeiro_nome;
                    try {
                        $stmtEmp = $pdo->prepare("INSERT INTO empresas (nome) VALUES (?)");
                        $stmtEmp->execute([$nome_empresa_padrao]);
                    } catch (Throwable $eEmp) {
                        $stmtEmp = $pdo->prepare("INSERT INTO empresas (nome_fantasia) VALUES (?)");
                        $stmtEmp->execute([$nome_empresa_padrao]);
                    }
                    $empresa_id = $pdo->lastInsertId();

                    // 3. Gera hash da senha e token seguro de verificação (24h)
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $token_verificacao = bin2hex(random_bytes(32));
                    $token_expira = date('Y-m-d H:i:s', strtotime('+24 hours'));

                    // 4. Insere o usuário com email_verificado = 0
                    $stmtUser = $pdo->prepare("
                        INSERT INTO usuarios (
                            empresa_id,
                            nome,
                            email,
                            senha,
                            email_verificado,
                            token_verificacao,
                            token_expira
                        ) VALUES (?, ?, ?, ?, 0, ?, ?)
                    ");

                    $stmtUser->execute([
                        $empresa_id,
                        $nome_usuario,
                        $email,
                        $senha_hash,
                        $token_verificacao,
                        $token_expira
                    ]);

                    $pdo->commit();

                    // 5. Envia o e-mail de ativação via Brevo
                    if (file_exists(__DIR__ . '/config/brevo.php')) {
                        require_once __DIR__ . '/config/brevo.php';
                        if (function_exists('enviar_email_verificacao')) {
                            enviar_email_verificacao($email, $nome_usuario, $token_verificacao);
                        }
                    }

                    header("Location: login.php?msg=verifique_email");
                    exit;
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log($e->getMessage());
                $erro = "Ocorreu um erro ao criar a conta. Tente novamente.";
            }
        }
    } else {
        $erro = "Por favor, preencha todos os campos obrigatórios.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Criar Conta | Vende+</title>
    
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
            padding: 32px 28px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 26px;
        }

        .logo-text {
            font-size: 28px;
            font-weight: 900;
            color: #ffffff;
            letter-spacing: -1px;
            margin: 0;
        }

        .logo-text span {
            color: #10b981;
        }

        .brand-subtitle {
            font-size: 13.5px;
            color: #71717a;
            margin-top: 6px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            color: #a1a1aa;
            margin-bottom: 6px;
            font-weight: 500;
        }

        .form-group input {
            width: 100%;
            padding: 12px 14px;
            background: #000000;
            border: 1px solid #27272a;
            border-radius: 8px;
            color: #ffffff;
            font-size: 14px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-group input:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 1px #10b981;
        }

        .form-group input::placeholder {
            color: #52525b;
        }

        .btn-submit {
            width: 100%;
            background-color: #10b981;
            color: #000000;
            font-weight: 700;
            font-size: 14px;
            padding: 12px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
        }

        .btn-submit:hover {
            background-color: #059669;
        }

        .btn-submit:active {
            transform: scale(0.99);
        }

        .auth-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 13px;
            color: #71717a;
        }

        .auth-footer a {
            color: #10b981;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.2s;
        }

        .auth-footer a:hover {
            color: #34d399;
            text-decoration: underline;
        }

        .alert {
            padding: 11px 14px;
            border-radius: 8px;
            font-size: 13px;
            margin-bottom: 18px;
            line-height: 1.4;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #f87171;
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-card">
        <div class="brand-header">
            <a href="index.php" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:10px;">
                <svg width="32" height="32" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0;">
                    <rect width="64" height="64" rx="16" fill="#09090b"/>
                    <path d="M14 22 L26 44 L44 16" fill="none" stroke="#ffffff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M52 32 L52 44 M46 38 L58 38" stroke="#10b981" stroke-width="5.5" stroke-linecap="round"/>
                </svg>
                <h1 class="logo-text">vende<span>+</span></h1>
            </a>
            <p class="brand-subtitle">Crie sua conta para começar a gerenciar</p>
        </div>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-error">
                ⚠️ <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php if (function_exists('csrf_token')): ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="nome_usuario">Seu Nome Completo</label>
                <input type="text" id="nome_usuario" name="nome_usuario" placeholder="Ex: João da Silva" value="<?= htmlspecialchars($_POST['nome_usuario'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" placeholder="Mínimo 6 caracteres" required>
            </div>

            <button type="submit" class="btn-submit">Criar Conta</button>
        </form>

        <div class="auth-footer">
            Já tem uma conta? <a href="login.php">Entrar</a>
        </div>
    </div>
</div>

</body>
</html>