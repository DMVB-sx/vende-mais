<?php
$nomeEmpresaExibicao = $_SESSION['empresa_nome'] ?? 'Minha Empresa';
$docEmpresaExibicao = $_SESSION['empresa_doc'] ?? '';

// Função auxiliar para formatar CPF ou CNPJ
if (!function_exists('formatarCpfCnpj')) {
    function formatarCpfCnpj($doc) {
        $numeros = preg_replace('/\D/', '', $doc);
        
        // Se for CPF (11 dígitos)
        if (strlen($numeros) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $numeros);
        }
        
        // Se for CNPJ (14 dígitos)
        if (strlen($numeros) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $numeros);
        }
        
        // Se tiver outro tamanho (ex: formatado parcial), retorna o original
        return $doc;
    }
}

if (isset($_SESSION['empresa_id']) && isset($pdo)) {
    try {
        $stmtSide = $pdo->prepare("SELECT * FROM empresas WHERE id = ? LIMIT 1");
        $stmtSide->execute([$_SESSION['empresa_id']]);
        $dadosEmpresaSide = $stmtSide->fetch(PDO::FETCH_ASSOC);

        if ($dadosEmpresaSide) {
            $nomeEncontrado = $dadosEmpresaSide['nome'] 
                           ?? $dadosEmpresaSide['nome_fantasia'] 
                           ?? $dadosEmpresaSide['nome_empresa'] 
                           ?? $dadosEmpresaSide['razao_social'] 
                           ?? null;

            $docEncontrado = $dadosEmpresaSide['cnpj_cpf'] 
                          ?? $dadosEmpresaSide['cnpj'] 
                          ?? $dadosEmpresaSide['cpf'] 
                          ?? $dadosEmpresaSide['documento'] 
                          ?? '';

            if (!empty($nomeEncontrado)) {
                $nomeEmpresaExibicao = $nomeEncontrado;
                $_SESSION['empresa_nome'] = $nomeEncontrado;
            }

            if (!empty($docEncontrado)) {
                $docEmpresaExibicao = $docEncontrado;
                $_SESSION['empresa_doc'] = $docEncontrado;
            }
        }
    } catch (Exception $e) {
        // Fallback
    }
}

$docFormatado = !empty($docEmpresaExibicao) ? formatarCpfCnpj($docEmpresaExibicao) : '';
?>

<style>
.sidebar {
    background-color: #09090b;
    border-right: 1px solid #18181b;
    display: flex;
    flex-direction: column;
    padding: 24px 16px;
    box-sizing: border-box;
    z-index: 1000;
}

.sidebar-header {
    margin-bottom: 24px;
    padding: 0 8px;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
}

.brand-wrapper {
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.logo-text {
    font-size: 22px;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.5px;
    margin: 0;
}

.logo-text span {
    color: #10b981;
}

.company-tag {
    font-size: 13px;
    color: #a1a1aa;
    margin-top: 6px;
    margin-bottom: 2px;
    font-weight: 600;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.company-doc {
    font-size: 11.5px;
    color: #71717a;
    margin: 0;
    font-weight: 400;
    letter-spacing: 0.2px;
    display: block;
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 4px;
    flex-grow: 1;
}

.nav-link {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 11px 14px;
    color: #a1a1aa;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.2s ease;
}

.nav-link:hover {
    background-color: #18181b;
    color: #ffffff;
}

.nav-link.active {
    background-color: rgba(16, 185, 129, 0.12);
    color: #10b981;
    font-weight: 600;
}

.nav-link .icon {
    font-size: 16px;
}

.nav-divider {
    height: 1px;
    background-color: #18181b;
    margin: 16px 0;
}

.logout-btn {
    color: #f87171 !important;
}

.logout-btn:hover {
    background-color: rgba(239, 68, 68, 0.1) !important;
    color: #ef4444 !important;
}

.mobile-header, .sidebar-backdrop, .btn-close-sidebar {
    display: none;
}

@media screen and (max-width: 768px) {
    .mobile-header {
        display: flex;
        flex-direction: row;
        justify-content: space-between;
        align-items: center;
        background-color: #09090b;
        border-bottom: 1px solid #18181b;
        padding: 14px 16px;
        position: sticky;
        top: 0;
        left: 0;
        width: 100%;
        z-index: 900;
        box-sizing: border-box;
    }

    .btn-menu-toggle {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #18181b;
        border: 1px solid #27272a;
        color: #e4e4e7;
        font-size: 13px;
        font-weight: 600;
        padding: 6px 12px;
        border-radius: 6px;
        cursor: pointer;
    }

    .sidebar {
        position: fixed !important;
        top: 0;
        left: 0;
        height: 100vh !important;
        width: 270px !important;
        transform: translateX(-100%);
        transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 10px 0 30px rgba(0,0,0,0.85);
    }

    .sidebar.open {
        transform: translateX(0) !important;
    }

    .btn-close-sidebar {
        display: block;
        background: transparent;
        border: none;
        color: #a1a1aa;
        font-size: 22px;
        cursor: pointer;
        padding: 4px;
        line-height: 1;
    }

    .sidebar-backdrop {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
        backdrop-filter: blur(2px);
        z-index: 999;
    }

    .sidebar-backdrop.open {
        display: block;
    }
}
</style>

<!-- Header Mobile -->
<div class="mobile-header">
    <a href="index.php?page=dashboard" class="brand-wrapper">
        <svg width="26" height="26" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0;">
            <rect width="64" height="64" rx="16" fill="#09090b"/>
            <path d="M14 22 L26 44 L44 16" fill="none" stroke="#ffffff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M52 32 L52 44 M46 38 L58 38" stroke="#10b981" stroke-width="5.5" stroke-linecap="round"/>
        </svg>
        <h1 class="logo-text">vende<span>+</span></h1>
    </a>
    <button type="button" class="btn-menu-toggle" onclick="toggleSidebar()">
        <span>☰</span> Menu
    </button>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

<!-- Menu Lateral -->
<aside class="sidebar" id="sidebarMenu">
    <div class="sidebar-header">
        <div style="width: 100%; overflow: hidden;">
            <a href="index.php?page=dashboard" class="brand-wrapper">
                <svg width="28" height="28" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg" style="flex-shrink:0;">
                    <rect width="64" height="64" rx="16" fill="#09090b"/>
                    <path d="M14 22 L26 44 L44 16" fill="none" stroke="#ffffff" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M52 32 L52 44 M46 38 L58 38" stroke="#10b981" stroke-width="5.5" stroke-linecap="round"/>
                </svg>
                <h1 class="logo-text">vende<span>+</span></h1>
            </a>
            
            <p class="company-tag"><?= htmlspecialchars($nomeEmpresaExibicao) ?></p>
            <?php if (!empty($docFormatado)): ?>
                <span class="company-doc"><?= htmlspecialchars($docFormatado) ?></span>
            <?php endif; ?>
        </div>
        <button type="button" class="btn-close-sidebar" onclick="toggleSidebar()">✕</button>
    </div>

    <nav class="sidebar-nav">
        <a href="index.php?page=dashboard" class="nav-link <?= ($page === 'dashboard') ? 'active' : '' ?>">
            <span class="icon">📊</span>
            <span>Visão geral</span>
        </a>
        
        <a href="index.php?page=produtos" class="nav-link <?= ($page === 'produtos') ? 'active' : '' ?>">
            <span class="icon">📦</span>
            <span>Produtos</span>
        </a>
        
        <a href="index.php?page=compras" class="nav-link <?= ($page === 'compras') ? 'active' : '' ?>">
            <span class="icon">🛒</span>
            <span>Compras</span>
        </a>
        
        <a href="index.php?page=vendas" class="nav-link <?= ($page === 'vendas') ? 'active' : '' ?>">
            <span class="icon">💰</span>
            <span>Vendas</span>
        </a>
        
        <a href="index.php?page=despesas" class="nav-link <?= ($page === 'despesas') ? 'active' : '' ?>">
            <span class="icon">💸</span>
            <span>Despesas</span>
        </a>

        <div class="nav-divider"></div>

        <a href="index.php?page=perfil" class="nav-link <?= ($page === 'perfil') ? 'active' : '' ?>">
            <span class="icon">⚙️</span>
            <span>Minha Conta</span>
        </a>

        <a href="logout.php" class="nav-link logout-btn">
            <span class="icon">🚪</span>
            <span>Sair da conta</span>
        </a>
    </nav>
</aside>

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebarMenu');
    const backdrop = document.getElementById('sidebarBackdrop');
    
    if (sidebar && backdrop) {
        sidebar.classList.toggle('open');
        backdrop.classList.toggle('open');
    }
}
</script>