<?php
require_once 'config/conexao.php';

// Se já estiver logado, redireciona para o painel
if (isset($_SESSION['usuario_id'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

$erro = '';
$sucesso = '';
$etapa = 1; // 1 = Validar conta, 2 = Digitar nova senha

// ============================================================
// Estado da recuperação: SEMPRE vem da sessão, nunca do POST.
// Isso impede que alguém forje o usuario_id e troque a senha
// de qualquer conta sem passar pela verificação da Etapa 1.
// ============================================================
if (
    !empty($_SESSION['reset_usuario_id']) &&
    !empty($_SESSION['reset_expira']) &&
    $_SESSION['reset_expira'] > time()
) {
    $etapa = 2;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validar_csrf();

    // ETAPA 1: Identificação (E-mail + Nome da Empresa)
    if (isset($_POST['validar_identidade'])) {

        // Limite simples de tentativas (evita brute-force de e-mail + empresa)
        $_SESSION['reset_tentativas'] = ($_SESSION['reset_tentativas'] ?? 0) + 1;
        $_SESSION['reset_tentativas_inicio'] = $_SESSION['reset_tentativas_inicio'] ?? time();

        if (time() - $_SESSION['reset_tentativas_inicio'] > 900) {
            // Passou 15 min, reseta contador
            $_SESSION['reset_tentativas'] = 1;
            $_SESSION['reset_tentativas_inicio'] = time();
        }

        if ($_SESSION['reset_tentativas'] > 5) {

            $erro = "Muitas tentativas. Aguarde alguns minutos antes de tentar novamente.";

        } else {

            $email = trim($_POST['email'] ?? '');
            $nome_empresa = trim($_POST['nome_empresa'] ?? '');

            if (!empty($email) && !empty($nome_empresa)) {
                try {
                    // Busca o usuário e os dados da empresa vinculada usando e.* para evitar conflito de colunas
                    $stmt = $pdo->prepare("
                        SELECT u.id AS usuario_id, u.nome AS usuario_nome, e.*
                        FROM usuarios u
                        JOIN empresas e ON u.empresa_id = e.id
                        WHERE u.email = ?
                        LIMIT 1
                    ");
                    $stmt->execute([$email]);
                    $dados = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($dados) {
                        // Identifica o campo correto do nome da empresa existente no banco
                        $nomeEmpresaReal = $dados['nome'] 
                                        ?? $dados['nome_fantasia'] 
                                        ?? $dados['fantasia'] 
                                        ?? $dados['razao_social'] 
                                        ?? $dados['nome_empresa'] 
                                        ?? '';

                        // Compara sem diferenciar maiúsculas/minúsculas
                        if (!empty($nomeEmpresaReal) && strcasecmp(trim($nomeEmpresaReal), $nome_empresa) === 0) {

                            // Identidade confirmada: guarda na SESSÃO (não no HTML)
                            $_SESSION['reset_usuario_id'] = (int)$dados['usuario_id'];
                            $_SESSION['reset_expira'] = time() + 600; // válido por 10 minutos
                            unset($_SESSION['reset_tentativas']);

                            $etapa = 2;
                        } else {
                            $erro = "O nome da empresa informada não confere com o cadastro deste e-mail.";
                        }
                    } else {
                        $erro = "Nenhuma conta cadastrada encontrada com este e-mail.";
                    }
                } catch (Throwable $e) {
                    error_log($e->getMessage());
                    $erro = "Não foi possível processar a verificação. Tente novamente.";
                }
            } else {
                $erro = "Preencha o e-mail e o nome da sua empresa cadastrada.";
            }
        }
    }

    // ETAPA 2: Redefinição da Senha
    if (isset($_POST['salvar_nova_senha'])) {

        // Só aceita se a Etapa 1 foi validada nesta sessão e não expirou
        if (
            empty($_SESSION['reset_usuario_id']) ||
            empty($_SESSION['reset_expira']) ||
            $_SESSION['reset_expira'] <= time()
        ) {
            unset($_SESSION['reset_usuario_id'], $_SESSION['reset_expira']);
            $etapa = 1;
            $erro = "Sua sessão de verificação expirou. Comece novamente.";

        } else {

            $usuario_id = (int)$_SESSION['reset_usuario_id'];
            $nova_senha = $_POST['nova_senha'] ?? '';
            $confirma_senha = $_POST['confirma_senha'] ?? '';

            if (!empty($nova_senha)) {
                if ($nova_senha === $confirma_senha) {
                    if (strlen($nova_senha) >= 6) {
                        try {
                            $nova_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                            $stmtUp = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                            $stmtUp->execute([$nova_hash, $usuario_id]);

                            // Encerra o estado de recuperação
                            unset($_SESSION['reset_usuario_id'], $_SESSION['reset_expira']);

                            $sucesso = "Senha redefinida com sucesso! Você já pode entrar com a nova senha.";
                            $etapa = 3; // Concluído
                        } catch (Throwable $e) {
                            error_log($e->getMessage());
                            $erro = "Não foi possível atualizar a senha. Tente novamente.";
                            $etapa = 2;
                        }
                    } else {
                        $erro = "A senha deve ter pelo menos 6 caracteres.";
                        $etapa = 2;
                    }
                } else {
                    $erro = "As senhas digitadas não coincidem.";
                    $etapa = 2;
                }
            } else {
                $erro = "Dados inválidos para redefinição.";
                $etapa = 2;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Recuperar Senha | Vende+</title>
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
            font-size: 13px;
            color: #71717a;
            line-height: 1.4;
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

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            border: 1px solid #10b981;
            color: #34d399;
        }
    </style>
</head>
<body>

<div class="auth-container">
    <div class="auth-card">
        <div class="brand-header">
            <h1 class="logo-text">vende<span>+</span></h1>
            <p class="brand-subtitle">
                <?php if ($etapa === 1): ?>
                    Confirme seus dados para redefinir a senha
                <?php elseif ($etapa === 2): ?>
                    Crie uma nova senha de acesso
                <?php else: ?>
                    Conta atualizada!
                <?php endif; ?>
            </p>
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

        <?php if ($etapa === 1): ?>
            <!-- ETAPA 1: Identificação -->
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

                <div class="form-group">
                    <label>Seu E-mail Cadastrado</label>
                    <input type="email" name="email" placeholder="seuemail@exemplo.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                </div>

                <div class="form-group">
                    <label>Nome da sua Empresa</label>
                    <input type="text" name="nome_empresa" placeholder="Ex: Denio" value="<?= htmlspecialchars($_POST['nome_empresa'] ?? '') ?>" required>
                </div>

                <button type="submit" name="validar_identidade" class="btn-submit">Verificar Conta</button>
            </form>

        <?php elseif ($etapa === 2): ?>
            <!-- ETAPA 2: Digitar Nova Senha -->
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

                <div class="form-group">
                    <label>Nova Senha</label>
                    <input type="password" name="nova_senha" placeholder="Mínimo 6 caracteres" required autofocus>
                </div>

                <div class="form-group">
                    <label>Confirmar Nova Senha</label>
                    <input type="password" name="confirma_senha" placeholder="Repita a nova senha" required>
                </div>

                <button type="submit" name="salvar_nova_senha" class="btn-submit">Salvar Nova Senha</button>
            </form>

        <?php else: ?>
            <!-- ETAPA 3: Sucesso -->
            <a href="login.php" class="btn-submit" style="display: block; text-align: center; text-decoration: none;">Ir para o Login</a>
        <?php endif; ?>

        <div class="auth-footer">
            Lembrou a senha? <a href="login.php">Voltar ao Login</a>
        </div>
    </div>
</div>

</body>
</html>