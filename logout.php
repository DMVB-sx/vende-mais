<?php

require_once 'config/conexao.php';

// ============================================================
// LIMPA TODAS AS VARIÁVEIS DA SESSÃO
// ============================================================

$_SESSION = [];


// ============================================================
// REMOVE O COOKIE DA SESSÃO
// ============================================================

if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => $params['secure'] ?? false,
            'httponly' => $params['httponly'] ?? true,
            'samesite' => $params['samesite'] ?? 'Lax'
        ]
    );
}


// ============================================================
// DESTRÓI A SESSÃO
// ============================================================

session_destroy();


// ============================================================
// REDIRECIONA PARA O LOGIN
// ============================================================

header("Location: login.php");
exit;