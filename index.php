<?php
// 1. Silencia notices e warnings de permissão de pasta temporária do PHP
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

// 2. Define o fuso horário oficial de Brasília
date_default_timezone_set('America/Sao_Paulo');

// 3. Inicia a sessão com o operador de supressão @
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
require_once __DIR__ . '/config/conexao.php';

// Sincroniza fuso horário no MySQL
if (isset($pdo)) {
    try {
        $pdo->exec("SET time_zone = '-03:00'");
    } catch (Throwable $e) {}
}

/*
|--------------------------------------------------------------------------
| VALIDAÇÃO DE E-MAIL VIA TOKEN DIRETO (SE HOUVER NA URL)
|--------------------------------------------------------------------------
*/
if (isset($_GET['validar_token']) && !empty($_GET['validar_token'])) {
    $token = trim($_GET['validar_token']);
    try {
        $stmtVal = $pdo->prepare("SELECT id, token_expira FROM usuarios WHERE token_verificacao = ? LIMIT 1");
        $stmtVal->execute([$token]);
        $userVal = $stmtVal->fetch(PDO::FETCH_ASSOC);

        if ($userVal) {
            if (!empty($userVal['token_expira']) && strtotime($userVal['token_expira']) < time()) {
                header("Location: login.php?erro=token_expirado");
                exit;
            }
            $upVal = $pdo->prepare("UPDATE usuarios SET email_verificado = 1, token_verificacao = NULL, token_expira = NULL WHERE id = ?");
            $upVal->execute([$userVal['id']]);
            header("Location: login.php?msg=email_confirmado");
            exit;
        } else {
            header("Location: login.php?erro=token_invalido");
            exit;
        }
    } catch (Throwable $e) {
        header("Location: login.php?erro=falha_validacao");
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| 1. SE NÃO ESTIVER LOGADO -> EXIBE LANDING PAGE
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['usuario_id']) || empty($_SESSION['usuario_id'])) {
    if (isset($_GET['page']) && !empty($_GET['page'])) {
        header("Location: login.php");
        exit;
    }
    
    // Inclui a Landing Page externa atualizada
    if (file_exists(__DIR__ . '/landing.php')) {
        require_once __DIR__ . '/landing.php';
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| 2. VERIFICAÇÃO DO STATUS DA ASSINATURA / TRIAL (USUÁRIO LOGADO)
|--------------------------------------------------------------------------
*/
$statusAssinatura = 'trial';
$trialExpiraEm = null;
$dataExpiracao = null;
$agora = date('Y-m-d H:i:s');
$hoje = date('Y-m-d');

try {
    $stmtStatus = $pdo->prepare("
        SELECT status_assinatura, trial_expira_em, data_expiracao, NOW() AS agora 
        FROM empresas 
        WHERE id = ? 
        LIMIT 1
    ");
    $stmtStatus->execute([$_SESSION['empresa_id']]);
    $empresaStatus = $stmtStatus->fetch(PDO::FETCH_ASSOC);

    if ($empresaStatus) {
        $statusAssinatura = $empresaStatus['status_assinatura'] ?? 'trial';
        $trialExpiraEm = $empresaStatus['trial_expira_em'] ?? null;
        $dataExpiracao = $empresaStatus['data_expiracao'] ?? null;
        $agora = $empresaStatus['agora'] ?? date('Y-m-d H:i:s');
    }
} catch (Throwable $e) {}

$bloqueado = false;
$motivoBloqueio = 'trial'; // 'trial' ou 'renovacao'

// 1. VIP nunca é bloqueado
if ($statusAssinatura !== 'vip') {
    // 2. Se for TRIAL e passou dos 7 dias
    if ($statusAssinatura === 'trial' && !empty($trialExpiraEm) && $agora > $trialExpiraEm) {
        $bloqueado = true;
        $motivoBloqueio = 'trial';
    }
    // 3. Se era ATIVO e a data de validade venceu
    elseif ($statusAssinatura === 'ativo' && !empty($dataExpiracao) && $hoje > $dataExpiracao) {
        $bloqueado = true;
        $motivoBloqueio = 'renovacao';
    }
    // 4. Se foi marcado manualmente como BLOQUEADO ou CANCELADO
    elseif ($statusAssinatura === 'bloqueado' || $statusAssinatura === 'cancelado') {
        $bloqueado = true;
        $motivoBloqueio = 'renovacao';
    }
}

if ($bloqueado) {
    if ($motivoBloqueio === 'renovacao') {
        $iconeBloqueio = '🔒';
        $tituloBloqueio = 'Sua assinatura venceu';
        $textoBloqueio = 'Identificamos que seu plano não foi renovado. Para continuar acessando seus relatórios, controle de vendas e estoque, renove sua assinatura:';
    } else {
        $iconeBloqueio = '⏳';
        $tituloBloqueio = 'Seu período de testes encerrou';
        $textoBloqueio = 'Seus produtos, vendas e cadastros continuam salvos com total segurança. Escolha um plano abaixo para liberar seu acesso imediatamente:';
    }
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <!-- Manifesto para PWA / Instalação -->
        <link rel="manifest" href="/manifest.json">
        <meta name="theme-color" content="#09090b">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
        <meta name="apple-mobile-web-app-title" content="Vende+">
        <link rel="apple-touch-icon" href="/assets/img/icon-192.png">

        <!-- Favicon -->
        <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">

        <title><?= $tituloBloqueio ?> | Vende+</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-black text-white min-h-screen flex items-center justify-center p-4">
        <div class="max-w-md w-full bg-zinc-900 border border-zinc-800 rounded-3xl p-8 text-center shadow-2xl">
            <div class="w-16 h-16 bg-emerald-500/10 text-emerald-400 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-5 border border-emerald-500/20">
                <?= $iconeBloqueio ?>
            </div>
            <h1 class="text-2xl font-black mb-2"><?= $tituloBloqueio ?></h1>
            <p class="text-zinc-400 text-sm mb-6 leading-relaxed">
                <?= $textoBloqueio ?>
            </p>
            <div class="space-y-3">
                <a href="https://pay.cakto.com.br/mifseqt_1068083" target="_blank" class="block w-full py-3.5 bg-emerald-500 hover:bg-emerald-400 text-black font-black rounded-xl transition-all text-sm shadow-lg shadow-emerald-500/25 no-underline">
                    Renovar no Trimestral (R$ 19,90/mês)
                </a>
                <a href="https://pay.cakto.com.br/33o9a3t_1068067" target="_blank" class="block w-full py-3 bg-zinc-800 hover:bg-zinc-700 text-white font-bold rounded-xl border border-zinc-700 transition-all text-xs no-underline">
                    Renovar no Mensal (R$ 24,90/mês)
                </a>
                <a href="logout.php" class="block w-full pt-3 text-zinc-500 hover:text-zinc-300 text-xs font-medium transition-colors no-underline">
                    Sair da Conta
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

/*
|--------------------------------------------------------------------------
| 3. ROTEAMENTO DAS PÁGINAS DO PAINEL (USUÁRIO ATIVO)
|--------------------------------------------------------------------------
*/
$pagina = $_GET['page'] ?? 'dashboard';
$page = $pagina;

$paginas_permitidas = [
    'dashboard'  => 'views/dashboard.php',
    'produtos'   => 'views/produtos.php',
    'vendas'     => 'views/vendas.php',
    'a-receber'  => 'views/a-receber.php',
    'compras'    => 'views/compras.php',
    'despesas'   => 'views/despesas.php',
    'perfil'     => 'views/perfil.php',
];

$arquivo_relativo = $paginas_permitidas[$pagina] ?? 'views/dashboard.php';
$arquivo_completo = __DIR__ . '/' . $arquivo_relativo;

if (!file_exists($arquivo_completo)) {
    $arquivo_completo = __DIR__ . '/views/dashboard.php';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">

    <!-- PWA E MOBILE CONFIGURAÇÕES -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#09090b">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Vende+">
    <link rel="apple-touch-icon" href="/assets/img/icon-192.png">

    <!-- FAVICON NA ABA DO PAINEL -->
    <link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">
    <title>Painel | Vende+</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-black text-slate-100 min-h-screen flex flex-col md:flex-row antialiased selection:bg-emerald-500 selection:text-black overflow-x-hidden">

    <?php 
    if (file_exists(__DIR__ . '/includes/sidebar.php')) {
        include __DIR__ . '/includes/sidebar.php';
    }
    ?>

    <main class="flex-1 w-full max-w-full p-4 sm:p-6 md:p-8 overflow-y-auto overflow-x-hidden">
        <?php require_once $arquivo_completo; ?>
    </main>

    <!-- REGISTRO DO SERVICE WORKER PWA -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then((reg) => console.log('PWA Service Worker registrado com sucesso!', reg))
                    .catch((err) => console.log('Erro ao registrar Service Worker:', err));
            });
        }
    </script>
</body>
</html>