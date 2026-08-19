<?php
require_once 'config/conexao.php';

// Se o usuário já estiver logado, vai direto pro dashboard
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome_empresa = trim($_POST['nome_empresa'] ?? '');
    $nome_usuario = trim($_POST['nome_usuario'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $senha        = $_POST['senha'] ?? '';

    if (!empty($nome_empresa) && !empty($nome_usuario) && !empty($email) && !empty($senha)) {
        if (strlen($senha) < 6) {
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

                    // 2. Cria a empresa
                    // Tenta inserir considerando diferentes nomes comuns de coluna
                    try {
                        $stmtEmp = $pdo->prepare("INSERT INTO empresas (nome) VALUES (?)");
                        $stmtEmp->execute([$nome_empresa]);
                    } catch (Throwable $eEmp) {
                        $stmtEmp = $pdo->prepare("INSERT INTO empresas (nome_fantasia) VALUES (?)");
                        $stmtEmp->execute([$nome_empresa]);
                    }
                    $empresa_id = $pdo->lastInsertId();

                    // 3. Cria o usuário com a senha criptografada
                    $senha_hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmtUser = $pdo->prepare("INSERT INTO usuarios (empresa_id, nome, email, senha) VALUES (?, ?, ?, ?)");
                    $stmtUser->execute([$empresa_id, $nome_usuario, $email, $senha_hash]);

                    $pdo->commit();

                    // Redireciona com aviso ou faz login direto
                    header("Location: login.php?msg=sucesso");
                    exit;
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $erro = "Ocorreu um erro ao criar a conta: " . $e->getMessage();
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
            border-radius: 12px;
            padding: 32px 28px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 26px;
        }

        .logo-text {
            font-size: 28px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
            margin-bottom: 6px;
        }

        .logo-text span {
            color: #10b981;
        }

        .brand-subtitle {
            font-size: 13.5px;
            color: #71717a;
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
            padding: 11px 14px;
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
            <h1 class="logo-text">vende<span>+</span></h1>
            <p class="brand-subtitle">Crie sua conta para começar a gerenciar</p>
        </div>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-error">
                ⚠️ <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="nome_empresa">Nome da Empresa / Fantasia</label>
                <input type="text" id="nome_empresa" name="nome_empresa" placeholder="Ex: Minha Loja" value="<?= htmlspecialchars($_POST['nome_empresa'] ?? '') ?>" required autofocus>
            </div>

            <div class="form-group">
                <label for="nome_usuario">Seu Nome Completo</label>
                <input type="text" id="nome_usuario" name="nome_usuario" placeholder="Ex: João da Silva" value="<?= htmlspecialchars($_POST['nome_usuario'] ?? '') ?>" required>
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