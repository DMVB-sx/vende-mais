<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/conexao.php';

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

    $nome_usuario    = trim($_POST['nome_usuario'] ?? '');
    $email           = trim($_POST['email'] ?? '');
    $senha           = $_POST['senha'] ?? '';
    $confirmar_senha = $_POST['confirmar_senha'] ?? '';

    if (!empty($nome_usuario) && !empty($email) && !empty($senha) && !empty($confirmar_senha)) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = "Por favor, informe um endereço de e-mail válido.";
        } elseif (strlen($senha) < 6) {
            $erro = "A senha deve ter pelo menos 6 caracteres.";
        } elseif ($senha !== $confirmar_senha) {
            $erro = "A senha e a confirmação de senha não coincidem.";
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
    <title>Criar Conta | vende+</title>
    
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
            margin-bottom: 26px;
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
            margin-bottom: 16px;
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
            margin-top: 10px;
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
    <p class="brand-subtitle">Crie sua conta para começar a gerenciar</p>
</div>

        <!-- MENSAGEM DE ERRO -->
        <?php if (!empty($erro)): ?>
            <div class="alert alert-error">
                <i data-lucide="alert-triangle" style="width: 18px; height: 18px; flex-shrink: 0; margin-top: 1px;"></i>
                <div><?= htmlspecialchars($erro) ?></div>
            </div>
        <?php endif; ?>

        <!-- FORMULÁRIO DE CADASTRO -->
        <form method="POST" action="">
            <?php if (function_exists('csrf_token')): ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="nome_usuario">Nome Completo</label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <i data-lucide="user" style="width: 16px; height: 16px;"></i>
                    </span>
                    <input type="text" id="nome_usuario" name="nome_usuario" placeholder="Ex: João da Silva" value="<?= htmlspecialchars($_POST['nome_usuario'] ?? '') ?>" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label for="email">E-mail</label>
                <div class="input-wrapper">
                    <span class="input-icon">
                        <i data-lucide="mail" style="width: 16px; height: 16px;"></i>
                    </span>
                    <input type="email" id="email" name="email" placeholder="seuemail@exemplo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <div class="input-wrapper password-wrapper">
                    <span class="input-icon">
                        <i data-lucide="lock" style="width: 16px; height: 16px;"></i>
                    </span>
                    <input type="password" id="senha" name="senha" placeholder="Mínimo 6 caracteres" required oninput="analisarForcaSenha(this.value)">
                    <button type="button" class="toggle-password" onclick="toggleSenha('senha', this)" title="Mostrar/Ocultar Senha" aria-label="Mostrar ou ocultar senha">
                        <i data-lucide="eye" style="width: 17px; height: 17px;"></i>
                    </button>
                </div>
                <!-- INDICADOR DE FORÇA -->
                <div style="padding-top: 6px;">
                    <div style="display: flex; justify-content: space-between; font-size: 11px; margin-bottom: 4px;">
                        <span style="color: #71717a;">Força da senha:</span>
                        <span id="texto-forca" style="font-weight: 700; color: #52525b;">Digite uma senha</span>
                    </div>
                    <div style="width: 100%; background: #18181b; border-radius: 999px; height: 4px; overflow: hidden;">
                        <div id="barra-forca" style="height: 100%; width: 0%; transition: all 0.3s ease; background: #71717a;"></div>
                    </div>
                </div>
            </div>

            <div class="form-group">
                <label for="confirmar_senha">Confirmar Senha</label>
                <div class="input-wrapper password-wrapper">
                    <span class="input-icon">
                        <i data-lucide="lock" style="width: 16px; height: 16px;"></i>
                    </span>
                    <input type="password" id="confirmar_senha" name="confirmar_senha" placeholder="Repita a senha" required>
                    <button type="button" class="toggle-password" onclick="toggleSenha('confirmar_senha', this)" title="Mostrar/Ocultar Senha" aria-label="Mostrar ou ocultar confirmação de senha">
                        <i data-lucide="eye" style="width: 17px; height: 17px;"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-submit">
                <span>Criar Conta</span>
                <i data-lucide="arrow-right" style="width: 16px; height: 16px;"></i>
            </button>
        </form>

        <div class="auth-footer">
            Já tem uma conta? <a href="login.php">Entrar</a>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    });

    function toggleSenha(inputId, botao) {
        const campo = document.getElementById(inputId);
        if (!campo || !botao) return;

        const ehPassword = campo.type === 'password';
        campo.type = ehPassword ? 'text' : 'password';

        botao.innerHTML = ehPassword 
            ? '<i data-lucide="eye-off" style="width: 17px; height: 17px;"></i>' 
            : '<i data-lucide="eye" style="width: 17px; height: 17px;"></i>';

        if (typeof lucide !== 'undefined') {
            lucide.createIcons();
        }
    }

    function analisarForcaSenha(senha) {
        const barra = document.getElementById('barra-forca');
        const texto = document.getElementById('texto-forca');
        if (!barra || !texto) return;

        let pontos = 0;

        if (senha.length >= 6) pontos += 1;
        if (senha.length >= 8) pontos += 1;
        if (/[A-Z]/.test(senha)) pontos += 1;
        if (/[0-9]/.test(senha)) pontos += 1;
        if (/[^A-Za-z0-9]/.test(senha)) pontos += 1;

        if (senha.length === 0) {
            barra.style.width = '0%';
            barra.style.background = '#71717a';
            texto.innerText = 'Digite uma senha';
            texto.style.color = '#52525b';
        } else if (pontos <= 2) {
            barra.style.width = '30%';
            barra.style.background = '#f87171';
            texto.innerText = 'Fraca';
            texto.style.color = '#f87171';
        } else if (pontos <= 3) {
            barra.style.width = '65%';
            barra.style.background = '#fbbf24';
            texto.innerText = 'Média';
            texto.style.color = '#fbbf24';
        } else {
            barra.style.width = '100%';
            barra.style.background = '#10b981';
            texto.innerText = 'Forte';
            texto.style.color = '#10b981';
        }
    }
</script>

</body>
</html>