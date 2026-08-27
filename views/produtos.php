<?php
$mensagem = '';
$empresa_id = $_SESSION['empresa_id'] ?? 0;

/*
|--------------------------------------------------------------------------
| FUNÇÃO AUXILIAR: TRATAMENTO DE VALORES MONETÁRIOS
|--------------------------------------------------------------------------
*/
function converterMoedaParaFloat($valor) {
    if (empty($valor)) return 0.0;
    $v = trim((string)$valor);
    if (strpos($v, ',') !== false) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return (float)$v;
}

/*
|--------------------------------------------------------------------------
| 1. EXCLUIR / INATIVAR PRODUTO
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_produto'])) {
    try {
        if (function_exists('validar_csrf')) validar_csrf();
        $id_del = (int)($_POST['id_produto'] ?? 0);

        if ($id_del > 0) {
            $stmtDel = $pdo->prepare("UPDATE produtos SET ativo = FALSE WHERE id = ? AND empresa_id = ?");
            $stmtDel->execute([$id_del, $empresa_id]);

            $mensagem = '
                <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                    <div><strong class="font-semibold block text-emerald-300">Produto removido com sucesso!</strong></div>
                </div>
            ';
        }
    } catch (Throwable $e) {
        $mensagem = '
            <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                <div><strong class="font-semibold block text-rose-300">Erro ao remover produto.</strong></div>
            </div>
        ';
    }
}

/*
|--------------------------------------------------------------------------
| 2. CADASTRAR OU EDITAR PRODUTO
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_produto'])) {
    try {
        if (function_exists('validar_csrf')) validar_csrf();

        $id_produto = (int)($_POST['id_produto'] ?? 0);
        $nome = trim($_POST['nome'] ?? '');
        $fornecedor = trim($_POST['fornecedor'] ?? '');
        $preco_custo = converterMoedaParaFloat($_POST['preco_custo'] ?? '0');
        $preco_venda = converterMoedaParaFloat($_POST['preco_venda'] ?? '0');
        $estoque = (int)($_POST['estoque'] ?? 0);

        if (empty($nome)) {
            throw new Exception('Informe o nome do produto.');
        }

        if ($preco_custo < 0 || $preco_venda < 0 || $estoque < 0) {
            throw new Exception('Valores e estoque não podem ser negativos.');
        }

        if ($id_produto > 0) {
            $stmtUp = $pdo->prepare("
                UPDATE produtos 
                SET nome = ?, fornecedor = ?, preco_custo = ?, preco_venda = ?, estoque = ? 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmtUp->execute([$nome, $fornecedor, $preco_custo, $preco_venda, $estoque, $id_produto, $empresa_id]);

            $mensagem = '
                <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                    <div><strong class="font-semibold block text-emerald-300">Produto atualizado com sucesso!</strong></div>
                </div>
            ';
        } else {
            $stmtIns = $pdo->prepare("
                INSERT INTO produtos (empresa_id, nome, fornecedor, preco_custo, preco_venda, estoque, ativo) 
                VALUES (?, ?, ?, ?, ?, ?, TRUE)
            ");
            $stmtIns->execute([$empresa_id, $nome, $fornecedor, $preco_custo, $preco_venda, $estoque]);

            $mensagem = '
                <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                    <div><strong class="font-semibold block text-emerald-300">Produto cadastrado com sucesso!</strong></div>
                </div>
            ';
        }
    } catch (Throwable $e) {
        error_log('Erro ao salvar produto: ' . $e->getMessage());
        $mensagem = '
            <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                <div><strong class="font-semibold block text-rose-300">Não foi possível salvar o produto. Tente novamente.</strong></div>
            </div>
        ';
    }
}

/*
|--------------------------------------------------------------------------
| 3. BUSCAR PRODUTO PARA EDIÇÃO
|--------------------------------------------------------------------------
*/
$produto_editar = null;
if (isset($_GET['acao']) && $_GET['acao'] === 'editar' && isset($_GET['id'])) {
    $id_ed = (int)$_GET['id'];
    $stmtEd = $pdo->prepare("SELECT * FROM produtos WHERE id = ? AND empresa_id = ? LIMIT 1");
    $stmtEd->execute([$id_ed, $empresa_id]);
    $produto_editar = $stmtEd->fetch(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| 4. CONSULTA E CONTAGEM DE PRODUTOS
|--------------------------------------------------------------------------
*/
$busca = trim($_GET['busca'] ?? '');
$aba_estoque = trim($_GET['aba_estoque'] ?? 'todos'); // todos | disponivel | esgotado

$paramsGeral = [$empresa_id];
$sql_busca = '';

if (!empty($busca)) {
    $sql_busca .= " AND (nome LIKE ? OR fornecedor LIKE ?) ";
    $paramsGeral[] = "%{$busca}%";
    $paramsGeral[] = "%{$busca}%";
}

// Contagens de status
$stmtCount = $pdo->prepare("
    SELECT 
        COUNT(*) as total_todos,
        COALESCE(SUM(CASE WHEN estoque > 0 THEN 1 ELSE 0 END), 0) as total_disp,
        COALESCE(SUM(CASE WHEN estoque <= 0 THEN 1 ELSE 0 END), 0) as total_esgotado
    FROM produtos
    WHERE empresa_id = ? AND ativo = TRUE {$sql_busca}
");
$stmtCount->execute($paramsGeral);
$contagens = $stmtCount->fetch(PDO::FETCH_ASSOC);

$totalTodos = (int)($contagens['total_todos'] ?? 0);
$totalDisp = (int)($contagens['total_disp'] ?? 0);
$totalEsgotado = (int)($contagens['total_esgotado'] ?? 0);

// Filtro da query por aba
$sql_aba = '';
if ($aba_estoque === 'disponivel') {
    $sql_aba = " AND estoque > 0 ";
} elseif ($aba_estoque === 'esgotado') {
    $sql_aba = " AND estoque <= 0 ";
}

$stmtProd = $pdo->prepare("
    SELECT * FROM produtos 
    WHERE empresa_id = ? AND ativo = TRUE {$sql_busca} {$sql_aba}
    ORDER BY estoque DESC, nome ASC
");
$stmtProd->execute($paramsGeral);
$produtos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);
?>

<script src="https://unpkg.com/lucide@latest"></script>

<style>
.produto-card.aberto .produto-seta-icon {
    transform: rotate(180deg);
}
.produto-detalhes {
    display: none;
}
.produto-card.aberto .produto-detalhes {
    display: block;
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>

<!-- CABEÇALHO -->
<header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-black text-white flex items-center gap-2.5 tracking-tight m-0">
            <div class="p-2 bg-emerald-500/10 rounded-xl border border-emerald-500/20">
                <i data-lucide="package" class="w-5 h-5 text-emerald-400"></i>
            </div>
            <?= $produto_editar ? 'Editar Produto' : 'Produtos' ?>
        </h2>
        <p class="text-sm text-zinc-400 mt-2 m-0">
            Catálogo, fornecedores, custos e controle de estoque
        </p>
    </div>
</header>

<?= $mensagem ?>

<!-- FORMULÁRIO DE NOVO / EDITAR PRODUTO -->
<div id="form-produto-card" class="bg-[#09090b] border <?= $produto_editar ? 'border-emerald-500/40 shadow-[0_0_30px_rgba(16,185,129,0.08)]' : 'border-zinc-800/80' ?> rounded-2xl p-4 sm:p-6 mb-8 max-w-3xl overflow-hidden box-border">
    <div class="flex items-center gap-2.5 mb-5">
        <div class="p-1.5 bg-zinc-800/60 rounded-lg text-zinc-400">
            <i data-lucide="<?= $produto_editar ? 'pencil' : 'plus' ?>" class="w-4 h-4 <?= $produto_editar ? 'text-emerald-400' : '' ?>"></i>
        </div>
        <h3 class="text-base font-bold text-white m-0">
            <?= $produto_editar ? 'Alterar Dados do Produto' : 'Novo Produto' ?>
        </h3>
    </div>

    <form method="POST" action="index.php?page=produtos" class="space-y-4">
        <?php if (function_exists('csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <?php endif; ?>
        <?php if ($produto_editar): ?>
            <input type="hidden" name="id_produto" value="<?= (int)$produto_editar['id'] ?>">
        <?php endif; ?>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="min-w-0">
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Nome do Produto *</label>
                <input type="text" name="nome" required placeholder="Ex: Camiseta Básica Preta"
                       value="<?= htmlspecialchars($produto_editar['nome'] ?? '') ?>"
                       class="w-full box-border bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors">
            </div>

            <div class="min-w-0">
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Fornecedor (opcional)</label>
                <input type="text" name="fornecedor" placeholder="Ex: Mercado Livre, Shopee"
                       value="<?= htmlspecialchars($produto_editar['fornecedor'] ?? '') ?>"
                       class="w-full box-border bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors">
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="min-w-0">
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Custo Unitário (R$)</label>
                <input type="text" name="preco_custo" id="prod_custo" placeholder="0,00"
                       value="<?= $produto_editar ? number_format((float)$produto_editar['preco_custo'], 2, ',', '.') : '0,00' ?>"
                       class="w-full box-border bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors">
            </div>

            <div class="min-w-0">
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Preço de Venda (R$)</label>
                <input type="text" name="preco_venda" id="prod_venda" placeholder="0,00"
                       value="<?= $produto_editar ? number_format((float)$produto_editar['preco_venda'], 2, ',', '.') : '0,00' ?>"
                       class="w-full box-border bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors">
            </div>

            <div class="min-w-0">
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Estoque Inicial (un.)</label>
                <input type="number" name="estoque" min="0" placeholder="0"
                       value="<?= $produto_editar ? (int)$produto_editar['estoque'] : '0' ?>"
                       class="w-full box-border bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors">
            </div>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" name="salvar_produto"
                    class="flex-1 inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-black text-sm font-bold rounded-xl px-6 py-3 transition-all shadow-[0_0_20px_rgba(16,185,129,0.15)] cursor-pointer">
                <i data-lucide="check" class="w-4 h-4"></i>
                <?= $produto_editar ? 'Atualizar Produto' : 'Salvar Produto' ?>
            </button>

            <?php if ($produto_editar): ?>
                <a href="index.php?page=produtos" class="inline-flex items-center justify-center bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 text-sm font-semibold rounded-xl px-5 py-3 transition-colors no-underline">
                    Cancelar
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- LISTAGEM DE PRODUTOS CADASTRADOS -->
<div class="bg-[#09090b] border border-zinc-800/80 rounded-2xl p-4 sm:p-6 overflow-hidden box-border">
    
    <!-- HEADER: TÍTULO, CONTADOR, ABAS DE FILTRO E BUSCA -->
    <div class="flex flex-col gap-4 mb-6 pb-5 border-b border-zinc-900">
        
        <div class="flex items-center justify-between gap-2.5">
            <div class="flex items-center gap-2.5">
                <div class="p-1.5 bg-emerald-500/10 rounded-xl border border-emerald-500/20">
                    <i data-lucide="boxes" class="w-4.5 h-4.5 text-emerald-400"></i>
                </div>
                <h3 class="text-base font-bold text-white m-0">Produtos Cadastrados</h3>
            </div>
            
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-zinc-800 text-zinc-400 border border-zinc-700/50">
                <?= count($produtos) ?> <?= count($produtos) === 1 ? 'item' : 'itens' ?>
            </span>
        </div>

        <!-- ABAS: TODOS | EM ESTOQUE | ESGOTADOS -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
                <div class="inline-flex bg-[#000000] p-1 rounded-xl border border-zinc-800 text-xs shrink-0">
                    <a href="index.php?page=produtos&aba_estoque=todos<?= !empty($busca) ? '&busca='.urlencode($busca) : '' ?>" 
                       class="px-3 py-1.5 rounded-lg no-underline transition flex items-center gap-1.5 whitespace-nowrap <?= $aba_estoque === 'todos' ? 'bg-zinc-800 text-white font-bold' : 'text-zinc-400 hover:text-white' ?>">
                        <span>Todos</span>
                        <span class="text-[10px] opacity-80">(<?= $totalTodos ?>)</span>
                    </a>

                    <a href="index.php?page=produtos&aba_estoque=disponivel<?= !empty($busca) ? '&busca='.urlencode($busca) : '' ?>" 
                       class="px-3 py-1.5 rounded-lg no-underline transition flex items-center gap-1.5 whitespace-nowrap <?= $aba_estoque === 'disponivel' ? 'bg-emerald-500 text-black font-bold shadow-md shadow-emerald-500/20' : 'text-zinc-400 hover:text-white' ?>">
                        <i data-lucide="check" class="w-3 h-3"></i>
                        <span>Em Estoque</span>
                        <span class="text-[10px] opacity-80">(<?= $totalDisp ?>)</span>
                    </a>

                    <a href="index.php?page=produtos&aba_estoque=esgotado<?= !empty($busca) ? '&busca='.urlencode($busca) : '' ?>" 
                       class="px-3 py-1.5 rounded-lg no-underline transition flex items-center gap-1.5 whitespace-nowrap <?= $aba_estoque === 'esgotado' ? 'bg-rose-500 text-white font-bold shadow-md shadow-rose-500/20' : 'text-zinc-400 hover:text-white' ?>">
                        <i data-lucide="alert-circle" class="w-3 h-3"></i>
                        <span>Esgotados</span>
                        <span class="text-[10px] opacity-80">(<?= $totalEsgotado ?>)</span>
                    </a>
                </div>
            </div>

            <!-- BUSCA RÁPIDA -->
            <form method="GET" action="index.php" class="m-0 relative w-full sm:w-64">
                <input type="hidden" name="page" value="produtos">
                <input type="hidden" name="aba_estoque" value="<?= htmlspecialchars($aba_estoque) ?>">
                
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-zinc-500 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <input type="text" name="busca" placeholder="Buscar produto..." value="<?= htmlspecialchars($busca) ?>"
                           class="pl-9 pr-8 py-2 bg-[#000000] border border-zinc-800 text-zinc-200 text-xs rounded-xl outline-none focus:border-emerald-500 transition-colors placeholder:text-zinc-600 w-full">
                    <?php if (!empty($busca)): ?>
                        <a href="index.php?page=produtos&aba_estoque=<?= htmlspecialchars($aba_estoque) ?>" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-300">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- LISTA DE CARDS DE PRODUTOS -->
    <?php if (count($produtos) > 0): ?>
        <div class="space-y-3">
            <?php foreach ($produtos as $p): 
                $precoCusto = (float)$p['preco_custo'];
                $precoVenda = (float)$p['preco_venda'];
                $estoqueQtd = (int)$p['estoque'];
                $lucroUn = $precoVenda - $precoCusto;
                $margemUn = $precoVenda > 0 ? (($lucroUn / $precoVenda) * 100) : 0;
            ?>
                <div class="produto-card bg-[#000000] border <?= $estoqueQtd > 0 ? 'border-zinc-800/80 hover:border-zinc-700' : 'border-rose-500/30' ?> rounded-2xl overflow-hidden transition" id="prod-card-<?= (int)$p['id'] ?>">
                    
                    <!-- LINHA RESUMO -->
                    <button type="button" class="w-full flex items-start sm:items-center justify-between gap-3 p-3.5 sm:p-4 bg-transparent border-none text-left cursor-pointer transition hover:bg-zinc-900/40" onclick="toggleProdutoCard(<?= (int)$p['id'] ?>)">
                        
                        <div class="flex items-start sm:items-center gap-3 min-w-0 flex-1">
                            <div class="p-2 bg-zinc-900 border border-zinc-800 rounded-xl text-zinc-400 shrink-0 mt-0.5 sm:mt-0">
                                <i data-lucide="package" class="w-4 h-4 <?= $estoqueQtd > 0 ? 'text-emerald-400' : 'text-rose-400' ?>"></i>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <strong class="text-white text-sm font-semibold truncate block"><?= htmlspecialchars($p['nome']) ?></strong>
                                    <?php if (!empty($p['fornecedor'])): ?>
                                        <span class="text-xs text-zinc-500 hidden sm:inline truncate">• Forn: <?= htmlspecialchars($p['fornecedor']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-2 mt-1 text-xs text-zinc-500">
                                    <?php if ($estoqueQtd > 0): ?>
                                        <span class="text-amber-400 font-semibold">Estoque: <?= $estoqueQtd ?> un.</span>
                                    <?php else: ?>
                                        <span class="text-rose-400 font-bold bg-rose-500/10 px-1.5 py-0.5 rounded text-[10px] uppercase">Esgotado (0 un.)</span>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($p['fornecedor'])): ?>
                                        <span class="sm:hidden">• <?= htmlspecialchars($p['fornecedor']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0 ml-2">
                            <div class="text-right">
                                <span class="text-[10px] text-zinc-500 uppercase tracking-wider block">Preço de Venda</span>
                                <strong class="text-sm font-bold text-white block whitespace-nowrap">
                                    R$ <?= number_format($precoVenda, 2, ',', '.') ?>
                                </strong>
                            </div>

                            <div class="produto-seta-icon w-7 h-7 rounded-lg bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-400 transition-transform duration-200">
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                            </div>
                        </div>
                    </button>

                    <!-- DETALHES EXPANSÍVEIS -->
                    <div class="produto-detalhes border-t border-zinc-900 p-4 bg-zinc-950/60">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-3.5 text-xs">
                            <div class="bg-[#09090b] p-2.5 rounded-xl border border-zinc-900">
                                <span class="text-zinc-500 block mb-0.5">Preço de Custo</span>
                                <strong class="text-zinc-300 font-medium">R$ <?= number_format($precoCusto, 2, ',', '.') ?></strong>
                            </div>

                            <div class="bg-[#09090b] p-2.5 rounded-xl border border-zinc-900">
                                <span class="text-zinc-500 block mb-0.5">Lucro Bruto / un.</span>
                                <strong class="font-bold <?= $lucroUn > 0 ? 'text-emerald-400' : 'text-zinc-400' ?>">
                                    R$ <?= number_format($lucroUn, 2, ',', '.') ?>
                                </strong>
                            </div>

                            <div class="bg-[#09090b] p-2.5 rounded-xl border border-zinc-900">
                                <span class="text-zinc-500 block mb-0.5">Margem Estimada</span>
                                <strong class="font-bold <?= $margemUn > 0 ? 'text-emerald-400' : 'text-zinc-400' ?>">
                                    <?= number_format($margemUn, 1, ',', '.') ?>%
                                </strong>
                            </div>

                            <div class="bg-[#09090b] p-2.5 rounded-xl border border-zinc-900">
                                <span class="text-zinc-500 block mb-0.5">Valor em Estoque</span>
                                <strong class="text-zinc-200 font-bold">
                                    R$ <?= number_format($estoqueQtd * $precoCusto, 2, ',', '.') ?>
                                </strong>
                            </div>
                        </div>

                        <!-- AÇÕES -->
                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-900">
                            <a href="index.php?page=compras&busca_compra=<?= urlencode($p['nome']) ?>" 
                               class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-emerald-400 font-semibold transition no-underline">
                                <i data-lucide="package-plus" class="w-3.5 h-3.5"></i>
                                <span>Adicionar Estoque</span>
                            </a>

                            <a href="index.php?page=produtos&acao=editar&id=<?= (int)$p['id'] ?>#form-produto-card" 
                               class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-200 font-semibold transition no-underline">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                <span>Editar</span>
                            </a>

                            <form method="POST" action="index.php?page=produtos" onsubmit="return confirm('Remover este produto do catálogo?');" class="m-0">
                                <?php if (function_exists('csrf_token')): ?>
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <?php endif; ?>
                                <input type="hidden" name="id_produto" value="<?= (int)$p['id'] ?>">
                                <button type="submit" name="excluir_produto" class="inline-flex items-center gap-1.5 text-xs px-3 py-1.5 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 font-semibold transition cursor-pointer">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                    <span>Excluir</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-zinc-500 text-center py-10 text-sm">
            Nenhum produto encontrado para o filtro selecionado.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});

function toggleProdutoCard(id) {
    const card = document.getElementById('prod-card-' + id);
    if (card) {
        card.classList.toggle('aberto');
    }
}
</script>