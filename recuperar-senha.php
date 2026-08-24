<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/config/brevo.php';

$mensagem_sucesso = '';
$mensagem_erro = '';

// Identifica a etapa: se há token na URL ou na sessão/POST, exibe o formulário de redefinição
$token_param = trim($_GET['token'] ?? $_POST['token'] ?? '');
$etapa = (!empty($token_param)) ? 'redefinir' : 'solicitar';

/*
|--------------------------------------------------------------------------
| 1. PROCESSA SOLICITAÇÃO DE CÓDIGO (ETAPA 1)
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'solicitar') {
    $email = trim($_POST['email'] ?? '');

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem_erro = "Por favor, informe um endereço de e-mail válido.";
    } else {
        // Busca usuário pelo e-mail
        $stmt = $pdo->prepare("SELECT id, nome, email FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            // Gera token numérico seguro de 6 dígitos
            $token = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $expira = date('Y-m-d H:i:s', strtotime('+30 minutes'));

            $stmtUpdate = $pdo->prepare("
                UPDATE usuarios 
                SET token_recuperacao = ?, token_expira_em = ? 
                WHERE id = ?
            ");
            $stmtUpdate->execute([$token, $expira, $usuario['id']]);

            // Dispara e-mail via API Brevo
            $enviado = enviarEmailTokenBrevo($usuario['email'], $usuario['nome'], $token);

            if ($enviado) {
                $mensagem_sucesso = "Enviamos um código de recuperação para o seu e-mail. Verifique sua caixa de entrada e pasta de spam!";
                // Transfere o fluxo para a tela de inserção do código
                $etapa = 'redefinir';
            } else {
                $mensagem_erro = "Ocorreu um erro ao enviar o e-mail. Verifique suas credenciais da Brevo ou tente novamente.";
            }
        } else {
            // Mensagem genérica para prevenir enumeração de contas
            $mensagem_sucesso = "Se o e-mail estiver cadastrado em nosso sistema, as instruções foram enviadas!";
        }
    }
}

/*
|--------------------------------------------------------------------------
| 2. PROCESSA REDEFINIÇÃO DE SENHA (ETAPA 2)
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acao']) && $_POST['acao'] === 'redefinir') {
    $token_digitado = trim($_POST['token'] ?? '');
    $nova_senha = $_POST['nova_senha'] ?? '';
    $confirma_senha = $_POST['confirma_senha'] ?? '';

    if (empty($token_digitado)) {
        $mensagem_erro = "Informe o código de 6 dígitos recebido.";
    } elseif (strlen($nova_senha) < 6) {
        $mensagem_erro = "A nova senha deve ter no mínimo 6 caracteres.";
    } elseif ($nova_senha !== $confirma_senha) {
        $mensagem_erro = "As senhas não coincidem. Digite novamente.";
    } else {
        // Valida token e expiração
        $stmtValida = $pdo->prepare("
            SELECT id 
            FROM usuarios 
            WHERE token_recuperacao = ? 
              AND token_expira_em >= NOW() 
            LIMIT 1
        ");
        $stmtValida->execute([$token_digitado]);
        $usuario = $stmtValida->fetch(PDO::FETCH_ASSOC);

        if ($usuario) {
            $senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);

            // Atualiza senha e anula o token usado
            $stmtTroca = $pdo->prepare("
                UPDATE usuarios 
                SET senha = ?, token_recuperacao = NULL, token_expira_em = NULL 
                WHERE id = ?
            ");
            $stmtTroca->execute([$senha_hash, $usuario['id']]);

            $mensagem_sucesso = "Senha alterada com sucesso! Você já pode fazer login.";
            $etapa = 'concluido';
        } else {
            $mensagem_erro = "Código inválido ou expirado. Solicite um novo envio.";
            $etapa = 'redefinir';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Recuperar Senha | Vende+</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        dark: {
                            950: '#060608',
                            900: '#09090b',
                            800: '#121215',
                            700: '#18181b',
                            600: '#27272a',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glow-emerald { box-shadow: 0 0 50px -10px rgba(16, 185, 129, 0.15); }
    </style>
</head>
<body class="bg-black text-slate-100 font-sans antialiased selection:bg-emerald-500 selection:text-black min-h-screen flex items-center justify-center p-4">

    <div class="max-w-md w-full bg-dark-900 border border-zinc-800 rounded-2xl p-6 sm:p-8 shadow-2xl glow-emerald">
        
        <!-- Logo -->
        <div class="text-center mb-6">
            <a href="index.php" class="inline-block text-2xl font-extrabold tracking-tight text-white mb-2">
                vende<span class="text-emerald-500">+</span>
            </a>
            <h1 class="text-lg font-bold text-white">Recuperação de Senha</h1>
            <p class="text-xs text-zinc-400 mt-1">
                <?= $etapa === 'redefinir' ? 'Digite o código de verificação recebido e sua nova senha' : 'Informe seu e-mail cadastrado para redefinir o acesso' ?>
            </p>
        </div>

        <!-- Alertas de Feedback -->
        <?php if (!empty($mensagem_erro)): ?>
            <div class="mb-5 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs rounded-xl p-3.5 flex items-start gap-2">
                <span>⚠️</span>
                <span><?= htmlspecialchars($mensagem_erro) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($mensagem_sucesso)): ?>
            <div class="mb-5 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-xs rounded-xl p-3.5 flex items-start gap-2">
                <span>✓</span>
                <span><?= htmlspecialchars($mensagem_sucesso) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($etapa === 'concluido'): ?>
            <!-- Tela Final de Sucesso -->
            <div class="text-center pt-2">
                <a href="login.php" class="w-full inline-block bg-emerald-500 hover:bg-emerald-400 text-black text-xs sm:text-sm font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-emerald-500/20">
                    Ir para o Login
                </a>
            </div>

        <?php elseif ($etapa === 'redefinir'): ?>
            <!-- Formulário: Etapa 2 (Código + Nova Senha) -->
            <form method="POST" action="recuperar-senha.php" class="space-y-4">
                <input type="hidden" name="acao" value="redefinir">

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1.5">Código de 6 Dígitos</label>
                    <input 
                        type="text" 
                        name="token" 
                        value="<?= htmlspecialchars($token_param) ?>" 
                        placeholder="Ex: 123456" 
                        required 
                        maxlength="6"
                        class="w-full bg-dark-800 border border-zinc-700 focus:border-emerald-500 text-white text-center text-lg tracking-widest font-mono rounded-xl px-4 py-3 outline-none transition-all placeholder:text-zinc-600 placeholder:tracking-normal placeholder:font-sans placeholder:text-xs"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1.5">Nova Senha</label>
                    <input 
                        type="password" 
                        name="nova_senha" 
                        placeholder="Mínimo 6 caracteres" 
                        required 
                        minlength="6"
                        class="w-full bg-dark-800 border border-zinc-700 focus:border-emerald-500 text-white text-xs sm:text-sm rounded-xl px-4 py-3 outline-none transition-all placeholder:text-zinc-600"
                    >
                </div>

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1.5">Confirmar Nova Senha</label>
                    <input 
                        type="password" 
                        name="confirma_senha" 
                        placeholder="Repita a nova senha" 
                        required 
                        minlength="6"
                        class="w-full bg-dark-800 border border-zinc-700 focus:border-emerald-500 text-white text-xs sm:text-sm rounded-xl px-4 py-3 outline-none transition-all placeholder:text-zinc-600"
                    >
                </div>

                <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-400 text-black text-xs sm:text-sm font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-emerald-500/20 cursor-pointer">
                    Salvar Nova Senha
                </button>

                <div class="text-center pt-2">
                    <a href="recuperar-senha.php" class="text-xs text-zinc-400 hover:text-zinc-200 transition-colors">
                        Reenviar outro código
                    </a>
                </div>
            </form>

        <?php else: ?>
            <!-- Formulário: Etapa 1 (Solicitar Código via E-mail) -->
            <form method="POST" action="recuperar-senha.php" class="space-y-4">
                <input type="hidden" name="acao" value="solicitar">

                <div>
                    <label class="block text-xs font-semibold text-zinc-300 uppercase tracking-wider mb-1.5">Seu E-mail Cadastrado</label>
                    <input 
                        type="email" 
                        name="email" 
                        placeholder="exemplo@dominio.com" 
                        required 
                        class="w-full bg-dark-800 border border-zinc-700 focus:border-emerald-500 text-white text-xs sm:text-sm rounded-xl px-4 py-3 outline-none transition-all placeholder:text-zinc-600"
                    >
                </div>

                <button type="submit" class="w-full bg-emerald-500 hover:bg-emerald-400 text-black text-xs sm:text-sm font-bold py-3.5 rounded-xl transition-all shadow-lg shadow-emerald-500/20 cursor-pointer">
                    Enviar Código de Recuperação
                </button>

                <div class="text-center pt-2">
                    <a href="login.php" class="text-xs text-zinc-400 hover:text-zinc-200 transition-colors">
                        ← Voltar para o Login
                    </a>
                </div>
            </form>
        <?php endif; ?>

    </div>

</body>
</html>