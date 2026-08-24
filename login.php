<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/conexao.php';

// Se já estiver logado, redireciona
if (isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] > 0) {
    header("Location: index.php?page=dashboard");
    exit;
}

$erro = '';
$sucesso = '';

if (isset($_GET['msg']) && $_GET['msg'] === 'sucesso') {
    $sucesso = "Conta criada com sucesso! Faça login para continuar.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($email) || empty($senha)) {
        $erro = "Preencha todos os campos.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = "Informe um e-mail válido.";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT id, nome, email, senha, empresa_id FROM usuarios WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario && password_verify($senha, $usuario['senha'])) {
                $empresa_id = (int)($usuario['empresa_id'] ?? 0);

                // Grava a sessão
                $_SESSION['usuario_id']   = (int)$usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['empresa_id']   = $empresa_id;

                // Busca dados da empresa
                if ($empresa_id > 0) {
                    $stmtEmp = $pdo->prepare("SELECT * FROM empresas WHERE id = ? LIMIT 1");
                    $stmtEmp->execute([$empresa_id]);
                    $emp = $stmtEmp->fetch(PDO::FETCH_ASSOC);
                    if ($emp) {
                        $_SESSION['empresa_nome'] = $emp['nome'] ?? $emp['nome_fantasia'] ?? 'Minha Empresa';
                        $_SESSION['empresa_doc']  = $emp['cnpj_cpf'] ?? $emp['cnpj'] ?? $emp['cpf'] ?? '';
                    }
                }

                // Salva a sessão no servidor antes de redirecionar
                session_write_close();

                // Redirecionamento duplo (PHP Header + fallback JS)
                header("Location: index.php?page=dashboard");
                echo "<script>window.location.href='index.php?page=dashboard';</script>";
                exit;
            } else {
                $erro = "E-mail ou senha incorretos.";
            }
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $erro = "Erro ao autenticar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Entrar | Vende+</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
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
        .auth-container { width: 100%; max-width: 400px; }
        .auth-card {
            background-color: #09090b;
            border: 1px solid #18181b;
            border-radius: 12px;
            padding: 32px 28px;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.7);
        }
        .brand-header { text-align: center; margin-bottom: 26px; }
        .logo-text { font-size: 28px; font-weight: 800; color: #ffffff; letter-spacing: -0.5px; margin-bottom: 6px; }
        .logo-text span { color: #10b981; }
        .brand-subtitle { font-size: 13.5px; color: #71717a; }
        .form-group { margin-bottom: 18px; }
        .form-group label { display: block; font-size: 13px; color: #a1a1aa; margin-bottom: 6px; font-weight: 500; }
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
        .form-group input:focus { border-color: #10b981; box-shadow: 0 0 0 1px #10b981; }
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
        }
        .btn-submit:hover { background-color: #059669; }
        .auth-footer { margin-top: 24px; text-align: center; font-size: 13px; color: #71717a; }
        .auth-footer a { color: #10b981; text-decoration: none; font-weight: 600; }
        .alert { padding: 11px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 18px; }
        .alert-error { background-color: rgba(239, 68, 68, 0.1); border: 1px solid #ef4444; color: #f87171; }
        .alert-success { background-color: rgba(16, 185, 129, 0.1); border: 1px solid #10b981; color: #34d399; }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-card">
        <div class="brand-header">
            <h1 class="logo-text">vende<span>+</span></h1>
            <p class="brand-subtitle">Acesse seu painel financeiro</p>
        </div>

        <?php if (!empty($erro)): ?>
            <div class="alert alert-error">
                ⚠️ <?= htmlspecialchars($erro) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($sucesso)): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($sucesso) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label for="email">E-mail</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="seuemail@exemplo.com"
                    value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                    required
                    autofocus
                >
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input
                    type="password"
                    id="senha"
                    name="senha"
                    placeholder="Sua senha de acesso"
                    required
                >
            </div>

            <button type="submit" class="btn-submit">Entrar no Sistema</button>
        </form>

        <div class="auth-footer">
            Não tem uma conta? <a href="cadastro.php">Cadastre-se</a>
        </div>
    </div>
</div>

</body>
</html>