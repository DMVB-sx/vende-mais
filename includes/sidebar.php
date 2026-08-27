<?php
$nomeEmpresaExibicao = $_SESSION['empresa_nome'] ?? 'Minha Empresa';
$docEmpresaExibicao = $_SESSION['empresa_doc'] ?? '';
$page = $page ?? ($_GET['page'] ?? 'dashboard');

// Função auxiliar para formatar CPF ou CNPJ
if (!function_exists('formatarCpfCnpj')) {
    function formatarCpfCnpj($doc) {
        $numeros = preg_replace('/\D/', '', $doc);
        if (strlen($numeros) === 11) return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $numeros);
        if (strlen($numeros) === 14) return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $numeros);
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
        // Fallback silencioso
    }
}

$docFormatado = !empty($docEmpresaExibicao) ? formatarCpfCnpj($docEmpresaExibicao) : '';
?>

<!-- Biblioteca Lucide Icons -->
<script src="https://unpkg.com/lucide@latest"></script>

<style>
.sidebar {
    background-color: #09090b;
    border-right: 1px solid #18181b;
    display: flex;
    flex-direction: column;
    padding: 24px 16px;
    box-sizing: border-box;
    z-index: 1000;
    width: 260px;
    flex-shrink: 0;
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
    margin-top: 8px;
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
        display: flex !important;
        flex-direction: column !important;
        align-items: flex-start !important;
        gap: 10px !important;
        background-color: #09090b !important;
        border-bottom: 1px solid #18181b !important;
        padding: 14px 16px !important;
        position: sticky !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        z-index: 900 !important;
        box-sizing: border-box !important;
    }

    .btn-menu-toggle {
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        background: #18181b !important;
        border: 1px solid #27272a !important;
        color: #e4e4e7 !important;
        font-size: 13px !important;
        font-weight: 600 !important;
        padding: 6px 12px !important;
        border-radius: 6px !important;
        cursor: pointer !important;
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
        <i data-lucide="menu" style="width: 16px; height: 16px;"></i> Menu
    </button>
</div>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleSidebar()"></div>

<!-- Menu Lateral Desktop / Gaveta Mobile -->
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
            <i data-lucide="layout-dashboard" style="width: 18px; height: 18px;"></i>
            <span>Visão geral</span>
        </a>
        
        <a href="index.php?page=vendas" class="nav-link <?= ($page === 'vendas') ? 'active' : '' ?>">
            <i data-lucide="shopping-bag" style="width: 18px; height: 18px;"></i>
            <span>Vendas</span>
        </a>

        <a href="index.php?page=produtos" class="nav-link <?= ($page === 'produtos') ? 'active' : '' ?>">
            <i data-lucide="package" style="width: 18px; height: 18px;"></i>
            <span>Produtos</span>
        </a>
        
        <a href="index.php?page=compras" class="nav-link <?= ($page === 'compras') ? 'active' : '' ?>">
            <i data-lucide="shopping-cart" style="width: 18px; height: 18px;"></i>
            <span>Compras</span>
        </a>

        <a href="index.php?page=a-receber" class="nav-link <?= ($page === 'a-receber') ? 'active' : '' ?>">
            <i data-lucide="wallet" style="width: 18px; height: 18px;"></i>
            <span>A Receber</span>
        </a>
        
        <a href="index.php?page=despesas" class="nav-link <?= ($page === 'despesas') ? 'active' : '' ?>">
            <i data-lucide="trending-down" style="width: 18px; height: 18px;"></i>
            <span>Despesas</span>
        </a>
        
        <div class="nav-divider"></div>

        <a href="index.php?page=perfil" class="nav-link <?= ($page === 'perfil') ? 'active' : '' ?>">
            <i data-lucide="settings" style="width: 18px; height: 18px;"></i>
            <span>Minha Conta</span>
        </a>

        <a href="logout.php" class="nav-link logout-btn">
            <i data-lucide="log-out" style="width: 18px; height: 18px;"></i>
            <span>Sair da conta</span>
        </a>
    </nav>
</aside>

<script>
if (typeof lucide !== 'undefined') {
    lucide.createIcons();
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebarMenu');
    const backdrop = document.getElementById('sidebarBackdrop');
    
    if (sidebar && backdrop) {
        sidebar.classList.toggle('open');
        backdrop.classList.toggle('open');
    }
}
</script>