<?php
require_once 'config/conexao.php';

// Se o usuário NÃO estiver logado, redireciona para o login
if (!isset($_SESSION['usuario_id'])) {
    header("Location: login.php");
    exit;
}

// Empresa ID dinâmica baseada na sessão do usuário logado
$empresa_id = $_SESSION['empresa_id'];

// Navegação dinâmica por páginas
$page = $_GET['page'] ?? 'dashboard';
$allowed_pages = ['dashboard', 'produtos', 'perfil', 'compras', 'vendas', 'despesas'];

if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Vende+ | Painel</title>
    <link rel="stylesheet" href="assets/css/style.css">
    
    <style>
        /* RESET E ESTRUTURA BASE */
        *, *::before, *::after {
            box-sizing: border-box !important;
        }

        html, body {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            min-height: 100vh !important;
            background-color: #000000 !important;
            color: #ffffff;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            overflow-x: hidden !important;
        }

        /* Estrutura Wrapper Flexível */
        .app-layout {
            display: flex;
            width: 100%;
            min-height: 100vh;
        }

        .main-content {
            flex: 1;
            min-width: 0; /* Previne overflow em flexbox */
            padding: 30px;
            background-color: #000000;
            box-sizing: border-box;
        }

        .border-modal-dark {
            border: 1px solid #27272a !important;
            border-radius: 12px !important;
        }

        /* --- DESKTOP (> 768px) --- */
        @media screen and (min-width: 769px) {
            .mobile-header {
                display: none !important;
            }
            .sidebar {
                width: 250px !important;
                flex-shrink: 0 !important;
                position: sticky !important;
                top: 0 !important;
                height: 100vh !important;
            }
        }

        /* --- MOBILE (<= 768px) --- */
        @media screen and (max-width: 768px) {
            .app-layout {
                flex-direction: column !important;
            }

            .main-content {
                width: 100% !important;
                max-width: 100% !important;
                padding: 16px !important;
            }

            .cards-grid {
                display: grid !important;
                grid-template-columns: 1fr 1fr !important;
                gap: 10px !important;
                width: 100% !important;
            }

            .card {
                padding: 14px !important;
                min-width: 0 !important;
            }

            .table-container {
                width: 100% !important;
                max-width: 100% !important;
                overflow-x: auto !important;
                -webkit-overflow-scrolling: touch;
            }

            .header {
                display: flex !important;
                flex-direction: column !important;
                align-items: flex-start !important;
                gap: 14px !important;
                width: 100% !important;
            }

            .header > div {
                width: 100% !important;
            }
        }
    </style>
</head>
<body>

    <div class="app-layout">
        <?php include 'includes/sidebar.php'; ?>

        <main class="main-content">
            <?php 
            $view_path = "views/{$page}.php";
            if (file_exists($view_path)) {
                include $view_path;
            } else {
                echo "<h2 style='color: #a1a1aa;'>Página em construção...</h2>";
            }
            ?>
        </main>
    </div>

    <!-- Modal Customizado SweetAlert2 Dark -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    function confirmarAcao(event, url, titulo, texto) {
        event.preventDefault();

        Swal.fire({
            title: titulo || 'Tem certeza?',
            text: texto || 'Essa ação não poderá ser desfeita!',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            cancelButtonColor: '#27272a',
            confirmButtonText: 'Sim, excluir',
            cancelButtonText: 'Cancelar',
            background: '#09090b',
            color: '#f8fafc',
            customClass: {
                popup: 'border-modal-dark'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    }
    </script>
</body>
</html>