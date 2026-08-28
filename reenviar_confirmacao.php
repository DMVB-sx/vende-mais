<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);
date_default_timezone_set('America/Sao_Paulo');

require_once __DIR__ . '/config/conexao.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);

    if (!$email) {
        header("Location: login.php?erro=email_invalido");
        exit;
    }

    try {
        // 1. Verifica se o usuário existe e se o e-mail AINDA NÃO foi verificado
        $stmt = $pdo->prepare("SELECT id, nome, email_verificado FROM usuarios WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            header("Location: login.php?erro=email_nao_encontrado");
            exit;
        }

        if ((int)$usuario['email_verificado'] === 1) {
            header("Location: login.php?msg=email_ja_verificado");
            exit;
        }

        // 2. Gera um novo token e validade de 24 horas
        $novoToken = bin2hex(random_bytes(32));
        $expiraEm = date('Y-m-d H:i:s', strtotime('+24 hours'));

        $update = $pdo->prepare("UPDATE usuarios SET token_verificacao = ?, token_expira = ? WHERE id = ?");
        $update->execute([$novoToken, $expiraEm, $usuario['id']]);

        // 3. Monta o link de validação (mesmo formato que você já usa no index.php)
        $linkValidacao = "https://" . $_SERVER['HTTP_HOST'] . "/index.php?validar_token=" . $novoToken;

        // 4. Disparo do e-mail (usando mail() padrão ou PHPMailer)
        $assunto = "Confirmação de Conta - Vende+";
        $mensagem = "Olá, " . htmlspecialchars($usuario['nome']) . "!\n\n";
        $mensagem .= "Recebemos sua solicitação de reenvio de confirmação.\n";
        $mensagem .= "Clique no link abaixo para ativar sua conta:\n" . $linkValidacao . "\n\n";
        $mensagem .= "Este link expira em 24 horas.";

        $headers = "From: nao-responda@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "Reply-To: suporte@" . $_SERVER['HTTP_HOST'] . "\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        mail($email, $assunto, $mensagem, $headers);

        header("Location: login.php?msg=reenvio_sucesso");
        exit;

    } catch (Throwable $e) {
        header("Location: login.php?erro=falha_reenvio");
        exit;
    }
}