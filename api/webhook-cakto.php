<?php
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/../config/conexao.php';

function logWebhook($msg, $dados = null) {
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $arquivo = $dir . '/cakto_webhook.log';
    $conteudo = "[" . date('Y-m-d H:i:s') . "] " . $msg . ($dados ? " | " . json_encode($dados, JSON_UNESCAPED_UNICODE) : "") . PHP_EOL;
    @file_put_contents($arquivo, $conteudo, FILE_APPEND);
}

$raw = file_get_contents('php://input');
if (empty($raw)) {
    http_response_code(200);
    echo json_encode(['status' => 'vazio']);
    exit;
}

$payload = json_decode($raw, true);
if (!$payload) {
    http_response_code(200);
    echo json_encode(['status' => 'json_invalido']);
    exit;
}

// 1. Responde com 200 IMEDIATAMENTE se for evento de teste ou ping da Cakto
$evento = strtolower(trim((string)($payload['event'] ?? '')));
if (in_array($evento, ['test', 'ping', 'teste', 'webhook_test', ''])) {
    if (!isset($payload['data'])) {
        logWebhook("Ping/Teste de conexao recebido com sucesso da Cakto");
        http_response_code(200);
        echo json_encode(['status' => 'sucesso', 'mensagem' => 'Endpoint online e pronto']);
        exit;
    }
}

// 2. Extrai os dados da transação (lida com array data[0])
$transacao = [];
if (isset($payload['data']) && is_array($payload['data'])) {
    $transacao = isset($payload['data'][0]) ? $payload['data'][0] : $payload['data'];
} else {
    $transacao = $payload;
}

// Atualiza o evento caso venha dentro da transação
if (empty($evento)) {
    $evento = strtolower(trim((string)($transacao['status'] ?? '')));
}

// 3. Extrai o e-mail do comprador
$email = trim((string)(
    $transacao['customer']['email'] 
    ?? $transacao['subscription']['customer']['email']
    ?? $transacao['buyer']['email']
    ?? $transacao['cliente']['email']
    ?? $payload['customer']['email']
    ?? $payload['email'] 
    ?? ''
));

$nomeProduto = (string)($transacao['product']['name'] ?? $transacao['offer']['name'] ?? '');

logWebhook("Evento recebido", ['email' => $email, 'evento' => $evento, 'produto' => $nomeProduto]);

// Se for disparo de teste que enviou e-mail fictício ou vazio
if (empty($email) || stripos($email, 'cakto.com') !== false || stripos($email, 'teste') !== false) {
    logWebhook("Disparo de teste da Cakto validado");
    http_response_code(200);
    echo json_encode(['status' => 'sucesso', 'mensagem' => 'Teste validado']);
    exit;
}

try {
    // 4. Localiza a empresa pelo e-mail
    $stmtU = $pdo->prepare("SELECT empresa_id FROM usuarios WHERE email = ? LIMIT 1");
    $stmtU->execute([$email]);
    $user = $stmtU->fetch(PDO::FETCH_ASSOC);

    $empresaId = $user['empresa_id'] ?? null;

    if (!$empresaId) {
        $stmtE = $pdo->prepare("SELECT id FROM empresas WHERE email = ? LIMIT 1");
        $stmtE->execute([$email]);
        $empresa = $stmtE->fetch(PDO::FETCH_ASSOC);
        $empresaId = $empresa['id'] ?? null;
    }

    if (!$empresaId) {
        logWebhook("Empresa nao encontrada para email: {$email}");
        http_response_code(200);
        echo json_encode(['status' => 'aviso', 'mensagem' => 'Empresa nao encontrada']);
        exit;
    }

    // 5. Trata os diferentes eventos (Aprovação vs Cancelamento/Atraso)
    $eventosAprovados = ['purchase_approved', 'paid', 'approved', 'subscription_renewed', 'subscription_reactivated'];
    $eventosCancelados = ['subscription_canceled', 'canceled', 'refunded', 'chargedback', 'subscription_paused'];

    if (in_array($evento, $eventosCancelados)) {
        // Bloqueia ou suspende caso cancele/reembolse
        $up = $pdo->prepare("UPDATE empresas SET status_assinatura = 'cancelado' WHERE id = ?");
        $up->execute([(int)$empresaId]);
        logWebhook("Assinatura suspensa/cancelada para empresa {$empresaId}");

        http_response_code(200);
        echo json_encode(['status' => 'sucesso', 'acao' => 'cancelado']);
        exit;
    }

    // Fluxo padrão: Pagamento Aprovado / Renovação
    $dias = (stripos($nomeProduto, 'trimestral') !== false || stripos($raw, '1068083') !== false) ? 90 : 30;
    $planoNome = ($dias === 90) ? 'Trimestral' : 'Mensal';

    $stmtEmp = $pdo->prepare("SELECT data_expiracao FROM empresas WHERE id = ? LIMIT 1");
    $stmtEmp->execute([(int)$empresaId]);
    $dadosEmp = $stmtEmp->fetch(PDO::FETCH_ASSOC);

    $hoje = date('Y-m-d');
    $expAtual = $dadosEmp['data_expiracao'] ?? null;

    if (!empty($expAtual) && $expAtual > $hoje) {
        $novaData = date('Y-m-d', strtotime("+{$dias} days", strtotime($expAtual)));
    } else {
        $novaData = date('Y-m-d', strtotime("+{$dias} days"));
    }

    $up = $pdo->prepare("UPDATE empresas SET status_assinatura = 'ativo', data_expiracao = ?, plano = ? WHERE id = ?");
    $up->execute([$novaData, $planoNome, (int)$empresaId]);

    logWebhook("SUCESSO: Empresa {$empresaId} ativada ate {$novaData} ({$planoNome})");

    http_response_code(200);
    echo json_encode([
        'status' => 'sucesso',
        'empresa_id' => $empresaId,
        'validade' => $novaData
    ]);
    exit;

} catch (Throwable $e) {
    logWebhook("Erro banco: " . $e->getMessage());
    http_response_code(200);
    echo json_encode(['status' => 'erro_banco', 'mensagem' => $e->getMessage()]);
    exit;
}