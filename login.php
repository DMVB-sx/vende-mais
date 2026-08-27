<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/conexao.php';

// Se já estiver logado, redireciona
if (isset($_SESSION['usuario_id']) && !empty($_SESSION['usuario_id'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

$erro = '';
$sucesso = '';

// Captura mensagens via GET
if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'verifique_email') {
        $sucesso = "Cadastro realizado! Enviamos um link de confirmação para o seu e-mail. Por favor, valide sua conta antes de entrar.";
    } elseif ($_GET['msg'] === 'email_confirmado') {
        $sucesso = "E-mail confirmado com sucesso! Você já pode entrar.";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (function_exists('validar_csrf')) {
        validar_csrf();
    }

    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (!empty($email) && !empty($senha)) {
        try {
            $stmt = $pdo->prepare("
                SELECT id, empresa_id, nome, email, senha, email_verificado 
                FROM usuarios 
                WHERE email = ? 
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                // VERIFICA SE O E-MAIL FOI CONFIRMADO
                if (isset($usuario['email_verificado']) && (int)$usuario['email_verificado'] === 0) {
                    $erro = "Sua conta ainda não foi ativada. Verifique a caixa de entrada (ou spam) do seu e-mail e clique no link de confirmação.";
                } else {
                    $_SESSION['usuario_id'] = $usuario['id'];
                    $_SESSION['empresa_id'] = $usuario['empresa_id'];
                    $_SESSION['usuario_nome'] = $usuario['nome'];

                    header("Location: index.php?page=dashboard");
                    exit;
                }
            } else {
                $erro = "E-mail ou senha incorretos.";
            }
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $erro = "Ocorreu um erro no login. Tente novamente.";
        }
    } else {
        $erro = "Preencha o e-mail e a senha.";
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Entrar | vende+</title>
    
    <!-- FAVICON -->
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    
    <!-- LUCIDE ICONS -->
    <script src="https://unpkg.com/lucide@latest"></script>

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
            max-width: 400px;
        }

        .auth-card {
            background-color: #09090b;
            border: 1px solid #18181b;
            border-radius: 16px;
            padding: 32px 26px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.85);
        }

        .brand-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .logo-text {
            font-size: 26px;
            font-weight: 800;
            color: #ffffff;
            letter-spacing: -0.5px;
            margin: 0;
        }

        .logo-text span {
            color: #10b981;
        }

        .brand-subtitle {
            font-size: 13px;
            color: #71717a;
            margin-top: 6px;
        }

        .form-group {
            margin-bottom: 18px;
        }

        .form-group label {
            display: block;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #a1a1aa;
            margin-bottom: 7px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 14px;
            color: #71717a;
            pointer-events: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .input-wrapper input {
            width: 100%;
            padding: 11px 14px 11px 40px;
            background: #000000;
            border: 1px solid #27272a;
            border-radius: 10px;
            color: #ffffff;
            font-size: 14px;
            outline: none;
            transition: all 0.2s ease;
        }

        .input-wrapper.password-wrapper input {
            padding-right: 44px;
        }

        .input-wrapper input:focus {
            border-color: #10b981;
            box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.35);
        }

        .input-wrapper input::placeholder {
            color: #52525b;
            font-size: 13.5px;
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #71717a;
            transition: color 0.2s;
        }

        .toggle-password:hover {
            color: #e4e4e7;
        }

        .forgot-wrapper {
            display: flex;
            justify-content: flex-end;
            margin-top: 8px;
        }

        .forgot-link {
            font-size: 12px;
            color: #10b981;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.2s;
        }

        .forgot-link:hover {
            color: #34d399;
            text-decoration: underline;
        }

        .btn-submit {
            width: 100%;
            background-color: #10b981;
            color: #000000;
            font-weight: 700;
            font-size: 14px;
            padding: 12px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            box-shadow: 0 4px 14px rgba(16, 185, 129, 0.25);
        }

        .btn-submit:hover {
            background-color: #34d399;
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.35);
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
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 12px 14px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #f87171;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.25);
            color: #34d399;
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-card">
        <!-- LOGO BRAND -->
<div class="brand-header">
    <a href="landing.php" style="text-decoration:none; display:inline-flex; align-items:center; justify-content:center; gap:10px;">
        <svg width="30" height="30" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0;">
            <rect width="64" height="64" rx="16" fill="#09090b"/>
            <path d="M14 22 L26 44 L44 16" fill="none" stroke="#ffffff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M52 32 L52 44 M46 38 L58 38" stroke="#10b981" stroke-width="5.5" stroke-linecap="round"/>
        </svg>
        <h1 class="logo-text">vende<span>+</span></h1>
    </a>
    <p class="brand-subtitle">Entre para gerenciar seu negócio</p>
</div>

        <!-- MENSAGENS -->
        <?php if (!empty($sucesso)): ?>
            <div class="alert alert-success">
                <i data-lucide="check-circle" style="width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px;"></i>
                <div><?= htmlspecialchars($sucesso) ?></div>
            </div>
        <?php endif; ?>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-error">
                <i data-lucide="alert-triangle" style="width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px;"></i>
                <div><?= htmlspecialchars($erro) ?></div>
            </div>
        <?php endif; ?>

        <!-- FORMULÁRIO -->
        <form method="POST" action="">
            <?php if (function_exists('csrf_token')): ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="email">E-mail</label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <i data-lucide="mail" style="width: 16px; height: 16px;"></i>
                    </span>
                    <input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <div class="input-wrapper password-wrapper">
                    <span class="input-icon">
                        <i data-lucide="lock" style="width: 16px; height: 16px;"></i>
                    </span>
                    <input type="password" id="senha" name="senha" placeholder="Digite sua senha" required>
                    <button type="button" class="toggle-password" onclick="toggleSenhaVisualizacao()" title="Mostrar/Ocultar Senha" aria-label="Mostrar ou ocultar senha">
                        <i id="eye-icon" data-lucide="eye" style="width: 17px; height: 17px;"></i>
                    </button>
                </div>
                <div class="forgot-wrapper">
                    <a href="recuperar-senha.php" class="forgot-link">Esqueceu a senha?</a>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <span>Entrar</span>
                <i data-lucide="arrow-right" style="width: 16px; height: 16px;"></i>
            </button>
        </form>

        <div class="auth-footer">
            Não tem uma conta? <a href="cadastro.php">Cadastre-se grátis</a>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });

    function toggleSenhaVisualizacao() {
        const campoSenha = document.getElementById('senha');
        const eyeBtn = document.querySelector('.toggle-password');

        if (!campoSenha || !eyeBtn) return;

        const ehPassword = campoSenha.type === 'password';
        campoSenha.type = ehPassword ? 'text' : 'password';

        eyeBtn.innerHTML = ehPassword 
            ? '<i data-lucide="eye-off" style="width: 17px; height: 17px;"></i>' 
            : '<i data-lucide="eye" style="width: 17px; height: 17px;"></i>';

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }
</script>

</body>
</html>