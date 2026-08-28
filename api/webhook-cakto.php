<?php
// Configurações e cabeçalhos
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('America/Sao_Paulo');

// Chave Secreta configurada para validação
define('CAKTO_WEBHOOK_SECRET', 'd9611d38-f4e3-4b57-8ae3-5381df01048a');

// Importa a conexão correta com o banco de dados
require_once __DIR__ . '/../config/conexao.php';

// Função de Log para depuração e auditoria
function logWebhook($mensagem, $dados = null) {
    $dir = __DIR__ . '/../logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $arquivo = $dir . '/cakto_webhook.log';
    $dataHora = date('Y-m-d H:i:s');
    $conteudo = "[{$dataHora}] " . $mensagem . ($dados ? " | " . json_encode($dados, JSON_UNESCAPED_UNICODE) : "") . PHP_EOL;
    @file_put_contents($arquivo, $conteudo, FILE_APPEND);
}

// 1. Receber payload JSON
$rawPayload = file_get_contents('php://input');
if (empty($rawPayload)) {
    http_response_code(400);
    echo json_encode(['status' => 'erro', 'mensagem' => 'Payload vazio']);
    exit;
}

$payload = json_decode($rawPayload, true);
if (!$payload) {
    http_response_code(400);
    echo json_encode(['status' => 'erro', 'mensagem' => 'JSON inválido']);
    exit;
}

// 2. Validação da Chave Secreta
$headers = function_exists('getallheaders') ? getallheaders() : [];
$secretHeader = $headers['X-Cakto-Secret'] ?? $headers['x-cakto-secret'] ?? $_SERVER['HTTP_X_CAKTO_SECRET'] ?? '';
$secretRecebido = $payload['secret'] ?? $payload['token'] ?? $secretHeader ?? $_GET['secret'] ?? '';

if (defined('CAKTO_WEBHOOK_SECRET') && !empty(CAKTO_WEBHOOK_SECRET) && CAKTO_WEBHOOK_SECRET !== 'SUA_CHAVE_SECRETA_AQUI') {
    if (!empty($secretRecebido) && $secretRecebido !== CAKTO_WEBHOOK_SECRET) {
        logWebhook('Falha de autenticação da chave secreta', ['recebido' => $secretRecebido]);
        http_response_code(401);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Chave de segurança não autorizada']);
        exit;
    }
}

// 3. Extração dos dados da transação
$evento = strtolower(trim((string)($payload['event'] ?? $payload['evento'] ?? $payload['status'] ?? '')));
$data = $payload['data'] ?? $payload;

// Busca flexível de e-mail e produto dentro da estrutura Cakto
$email = trim((string)($data['customer']['email'] ?? $data['cliente']['email'] ?? $data['buyer']['email'] ?? $payload['customer_email'] ?? $payload['email'] ?? ''));
$nomeProduto = (string)($data['product']['name'] ?? $data['produto']['nome'] ?? $data['item_name'] ?? '');
$checkoutUrl = (string)($data['checkout_url'] ?? $payload['checkout_url'] ?? '');

logWebhook("Evento recebido: {$evento}", ['email' => $email, 'produto' => $nomeProduto, 'evento' => $evento]);

// Eventos de liberação de acesso
$eventosAprovados = [
    'compra aprovada',
    'purchase_approved',
    'paid',
    'approved',
    'subscription_renewed',
    'renovação de assinatura aprovada',
    'assinatura renovada',
    'assinatura reativada',
    'subscription_created'
];

if (in_array($evento, $eventosAprovados) || empty($evento)) {
    if (empty($email)) {
        logWebhook('Erro: E-mail do comprador não encontrado no payload');
        http_response_code(422);
        echo json_encode(['status' => 'erro', 'mensagem' => 'E-mail não identificado']);
        exit;
    }

    try {
        // Busca a empresa pelo e-mail na tabela de usuários primeiro (onde o e-mail de login fica garantido)
        $stmtUser = $pdo->prepare("SELECT empresa_id FROM usuarios WHERE email = ? LIMIT 1");
        $stmtUser->execute([$email]);
        $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

        $empresa = null;
        if ($user && !empty($user['empresa_id'])) {
            $stmt = $pdo->prepare("SELECT id, data_expiracao, status_assinatura FROM empresas WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$user['empresa_id']]);
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        // Se não achou na tabela usuarios, tenta na tabela empresas diretamente
        if (!$empresa) {
            $stmt = $pdo->prepare("SELECT id, data_expiracao, status_assinatura FROM empresas WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        if (!$empresa) {
            logWebhook("Empresa não encontrada para o e-mail: {$email}");
            // Retorna 200 para a Cakto não entrar em loop caso o comprador use um e-mail diferente do cadastro
            http_response_code(200);
            echo json_encode(['status' => 'aviso', 'mensagem' => 'Empresa não encontrada no banco']);
            exit;
        }

        // Determina a quantidade de dias conforme o plano
        $diasAdicionar = 30; // Padrão Mensal
        if (
            stripos($nomeProduto, 'trimestral') !== false || 
            stripos($nomeProduto, '3 meses') !== false || 
            stripos($rawPayload, 'mifseqt_1068083') !== false
        ) {
            $diasAdicionar = 90; // Trimestral
        }

        $hoje = date('Y-m-d');
        $dataExpiracaoAtual = $empresa['data_expiracao'];

        // Se a assinatura atual ainda estiver válida no futuro, soma os novos dias no final
        if (!empty($dataExpiracaoAtual) && $dataExpiracaoAtual > $hoje) {
            $novaDataExpiracao = date('Y-m-d', strtotime("+{$diasAdicionar} days", strtotime($dataExpiracaoAtual)));
        } else {
            $novaDataExpiracao = date('Y-m-d', strtotime("+{$diasAdicionar} days"));
        }

        $nomePlanoSalvar = ($diasAdicionar === 90) ? 'Trimestral' : 'Mensal';

        // Atualiza a empresa com a assinatura ativa
        $stmtUp = $pdo->prepare("
            UPDATE empresas 
            SET status_assinatura = 'ativo', 
                data_expiracao = ?, 
                plano = ? 
            WHERE id = ?
        ");
        $stmtUp->execute([$novaDataExpiracao, $nomePlanoSalvar, (int)$empresa['id']]);

        logWebhook("Assinatura liberada com sucesso para empresa ID {$empresa['id']}. Nova data: {$novaDataExpiracao}");

        http_response_code(200);
        echo json_encode([
            'status' => 'sucesso',
            'empresa_id' => $empresa['id'],
            'nova_data_expiracao' => $novaDataExpiracao
        ]);
        exit;

    } catch (Throwable $e) {
        logWebhook("Exceção ao processar ativação: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['status' => 'erro', 'mensagem' => 'Erro interno ao ativar assinatura']);
        exit;
    }
}

// Resposta padrão para outros eventos
http_response_code(200);
echo json_encode(['status' => 'ignorado', 'evento' => $evento]);