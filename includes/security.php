<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function validar_csrf(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $token = $_POST['csrf_token'] ?? '';
    $sessao_token = $_SESSION['csrf_token'] ?? '';

    if (
        empty($token) ||
        empty($sessao_token) ||
        !hash_equals($sessao_token, $token)
    ) {
        // Gera um novo token para a próxima tentativa
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        http_response_code(403);
        exit('Requisição inválida. Atualize a página e tente novamente.');
    }
}