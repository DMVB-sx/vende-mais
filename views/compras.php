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
    
    // Se possui vírgula (ex: 85,00 ou 1.250,50)
    if (strpos($v, ',') !== false) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return (float)$v;
}

/*
|--------------------------------------------------------------------------
| 1. EXCLUIR / CANCELAR COMPRA
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['cancelar_compra'])
) {

    try {
        if (function_exists('validar_csrf')) {
            validar_csrf();
        }

        $id_deletar = (int)($_POST['id_compra'] ?? 0);

        if ($id_deletar <= 0) {
            throw new Exception('Registro de entrada inválido.');
        }

        $stmtCompOld = $pdo->prepare("
            SELECT produto_id, quantidade
            FROM compras
            WHERE id = ? AND empresa_id = ?
            LIMIT 1
        ");
        $stmtCompOld->execute([$id_deletar, $empresa_id]);
        $compraAntiga = $stmtCompOld->fetch(PDO::FETCH_ASSOC);

        if (!$compraAntiga) {
            throw new Exception('Registro de compra não encontrado.');
        }

        $produto_id = (int)$compraAntiga['produto_id'];
        $qtd_removida = (int)$compraAntiga['quantidade'];

        $pdo->beginTransaction();

        // 1. Exclui a compra
        $stmtDel = $pdo->prepare("
            DELETE FROM compras
            WHERE id = ? AND empresa_id = ?
        ");
        $stmtDel->execute([$id_deletar, $empresa_id]);

        // 2. Abate do estoque
        $stmtEstoque = $pdo->prepare("
            UPDATE produtos
            SET estoque = GREATEST(0, estoque - ?)
            WHERE id = ? AND empresa_id = ?
        ");
        $stmtEstoque->execute([$qtd_removida, $produto_id, $empresa_id]);

        $pdo->commit();

        $mensagem = '
            <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                <div>
                    <strong class="font-semibold block text-emerald-300">Entrada cancelada com sucesso!</strong>
                    <span class="text-xs text-emerald-400/80">A quantidade de ' . $qtd_removida . ' un. foi removida do estoque.</span>
                </div>
            </div>
        ';

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Erro ao cancelar compra: ' . $e->getMessage());
        $mensagem = '
            <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                <div><strong class="font-semibold block text-rose-300">' . htmlspecialchars($e->getMessage()) . '</strong></div>
            </div>
        ';
    }
}

/*
|--------------------------------------------------------------------------
| 2. CADASTRO / EDIÇÃO DE COMPRA
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['salvar_compra'])
) {

    try {
        if (function_exists('validar_csrf')) {
            validar_csrf();
        }

        $id_compra = (int)($_POST['id_compra'] ?? 0);
        $produto_id = (int)($_POST['produto_id'] ?? 0);
        $quantidade = (int)($_POST['quantidade'] ?? 0);
        
        $custo_unitario = converterMoedaParaFloat($_POST['custo_unitario'] ?? '0');
        $frete = converterMoedaParaFloat($_POST['frete'] ?? '0');
        
        $atualizar_custo_produto = isset($_POST['atualizar_custo_produto']);

        if ($produto_id <= 0) {
            throw new Exception('Selecione um produto.');
        }

        if ($quantidade <= 0) {
            throw new Exception('A quantidade comprada deve ser maior que zero.');
        }

        if ($custo_unitario < 0 || $frete < 0) {
            throw new Exception('Valores monetários não podem ser negativos.');
        }

        $custo_real_unitario_remessa = $custo_unitario + ($frete / $quantidade);

        $pdo->beginTransaction();

        $stmtP = $pdo->prepare("
            SELECT estoque, preco_custo
            FROM produtos
            WHERE id = ? AND empresa_id = ?
            LIMIT 1
        ");
        $stmtP->execute([$produto_id, $empresa_id]);
        $prodAtual = $stmtP->fetch(PDO::FETCH_ASSOC);

        if (!$prodAtual) {
            throw new Exception('Produto não encontrado.');
        }

        // EDIÇÃO
        if ($id_compra > 0) {

            $stmtCOld = $pdo->prepare("
                SELECT produto_id, quantidade
                FROM compras
                WHERE id = ? AND empresa_id = ?
                LIMIT 1
            ");
            $stmtCOld->execute([$id_compra, $empresa_id]);
            $cAntiga = $stmtCOld->fetch(PDO::FETCH_ASSOC);

            if (!$cAntiga) {
                throw new Exception('Compra não encontrada.');
            }

            $estoque_base = max(0, (int)$prodAtual['estoque'] - (int)$cAntiga['quantidade']);
            $novo_estoque = $estoque_base + $quantidade;

            $stmtUpC = $pdo->prepare("
                UPDATE compras
                SET produto_id = ?, quantidade = ?, custo_unitario = ?, frete = ?, custo_real_unitario = ?
                WHERE id = ? AND empresa_id = ?
            ");
            $stmtUpC->execute([
                $produto_id,
                $quantidade,
                $custo_unitario,
                $frete,
                $custo_real_unitario_remessa,
                $id_compra,
                $empresa_id
            ]);

            if ($atualizar_custo_produto) {
                $valor_investido_antigo = $estoque_base * (float)$prodAtual['preco_custo'];
                $valor_investido_novo = ($quantidade * $custo_unitario) + $frete;
                $novo_custo_medio = $novo_estoque > 0 ? ($valor_investido_antigo + $valor_investido_novo) / $novo_estoque : $custo_real_unitario_remessa;

                $stmtUpP = $pdo->prepare("UPDATE produtos SET estoque = ?, preco_custo = ? WHERE id = ? AND empresa_id = ?");
                $stmtUpP->execute([$novo_estoque, $novo_custo_medio, $produto_id, $empresa_id]);
            } else {
                $stmtUpP = $pdo->prepare("UPDATE produtos SET estoque = ? WHERE id = ? AND empresa_id = ?");
                $stmtUpP->execute([$novo_estoque, $produto_id, $empresa_id]);
            }

            $pdo->commit();

            $mensagem = '
                <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                    <div>
                        <strong class="font-semibold block text-emerald-300">Entrada atualizada com sucesso!</strong>
                    </div>
                </div>
            ';

        // NOVA COMPRA
        } else {

            $nova_qtd_total = (int)$prodAtual['estoque'] + $quantidade;

            $stmtC = $pdo->prepare("
                INSERT INTO compras (empresa_id, produto_id, quantidade, custo_unitario, frete, custo_real_unitario)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtC->execute([
                $empresa_id,
                $produto_id,
                $quantidade,
                $custo_unitario,
                $frete,
                $custo_real_unitario_remessa
            ]);

            if ($atualizar_custo_produto) {
                $qtd_antiga = (int)$prodAtual['estoque'];
                $custo_antigo = (float)$prodAtual['preco_custo'];
                $valor_total_antigo = $qtd_antiga * $custo_antigo;
                $valor_total_novo = ($quantidade * $custo_unitario) + $frete;
                $novo_custo_medio = $nova_qtd_total > 0 ? ($valor_total_antigo + $valor_total_novo) / $nova_qtd_total : $custo_real_unitario_remessa;

                $stmtP = $pdo->prepare("UPDATE produtos SET estoque = ?, preco_custo = ? WHERE id = ? AND empresa_id = ?");
                $stmtP->execute([$nova_qtd_total, $novo_custo_medio, $produto_id, $empresa_id]);
            } else {
                $stmtP = $pdo->prepare("UPDATE produtos SET estoque = ? WHERE id = ? AND empresa_id = ?");
                $stmtP->execute([$nova_qtd_total, $produto_id, $empresa_id]);
            }

            $pdo->commit();

            $mensagem = '
                <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                    <div>
                        <strong class="font-semibold block text-emerald-300">Entrada registrada com sucesso!</strong>
                        <span class="text-xs text-emerald-400/80">+' . $quantidade . ' un. adicionadas ao estoque do produto.</span>
                    </div>
                </div>
            ';
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Erro ao salvar compra: ' . $e->getMessage());
        $mensagem = '
            <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                <div><strong class="font-semibold block text-rose-300">' . htmlspecialchars($e->getMessage()) . '</strong></div>
            </div>
        ';
    }
}

/*
|--------------------------------------------------------------------------
| 3. EDIÇÃO
|--------------------------------------------------------------------------
*/

$compra_editar = null;

if (
    isset($_GET['acao']) &&
    $_GET['acao'] === 'editar' &&
    isset($_GET['id'])
) {
    $id_editar = (int)$_GET['id'];

    $stmtEd = $pdo->prepare("
        SELECT *
        FROM compras
        WHERE id = ? AND empresa_id = ?
        LIMIT 1
    ");
    $stmtEd->execute([$id_editar, $empresa_id]);
    $compra_editar = $stmtEd->fetch(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| 4. PRODUTOS & HISTÓRICO COM FILTROS
|--------------------------------------------------------------------------
*/

$stmtProd = $pdo->prepare("
    SELECT id, nome, fornecedor, preco_custo, estoque
    FROM produtos
    WHERE empresa_id = ? AND ativo = TRUE
    ORDER BY nome ASC
");
$stmtProd->execute([$empresa_id]);
$produtos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

$busca_compra = trim($_GET['busca_compra'] ?? '');
$periodo = trim($_GET['periodo'] ?? 'todos');
$data_inicio = trim($_GET['data_inicio'] ?? '');
$data_fim = trim($_GET['data_fim'] ?? '');

$paramsCompras = [$empresa_id];
$sql_filtro = '';

if (!empty($busca_compra)) {
    $sql_filtro .= " AND (p.nome LIKE ? OR p.fornecedor LIKE ?) ";
    $term = "%{$busca_compra}%";
    $paramsCompras[] = $term;
    $paramsCompras[] = $term;
}

if ($periodo === 'hoje') {
    $sql_filtro .= " AND DATE(c.data_compra) = CURDATE() ";
} elseif ($periodo === '7dias') {
    $sql_filtro .= " AND c.data_compra >= DATE_SUB(NOW(), INTERVAL 7 DAY) ";
} elseif ($periodo === 'mes') {
    $sql_filtro .= " AND MONTH(c.data_compra) = MONTH(CURRENT_DATE()) AND YEAR(c.data_compra) = YEAR(CURRENT_DATE()) ";
} elseif ($periodo === 'personalizado' && !empty($data_inicio) && !empty($data_fim)) {
    $sql_filtro .= " AND DATE(c.data_compra) BETWEEN ? AND ? ";
    $paramsCompras[] = $data_inicio;
    $paramsCompras[] = $data_fim;
}

$stmtTotal = $pdo->prepare("
    SELECT COUNT(*) 
    FROM compras c 
    LEFT JOIN produtos p ON c.produto_id = p.id 
    WHERE c.empresa_id = ? {$sql_filtro}
");
$stmtTotal->execute($paramsCompras);
$total_compras = (int)$stmtTotal->fetchColumn();

$por_pagina = 15;
$pagina_atual = max(1, (int)($_GET['pagina'] ?? 1));
$total_paginas = max(1, (int)ceil($total_compras / $por_pagina));

if ($pagina_atual > $total_paginas) {
    $pagina_atual = $total_paginas;
}

$offset = ($pagina_atual - 1) * $por_pagina;

$stmtHist = $pdo->prepare("
    SELECT c.*, p.nome AS produto_nome, p.fornecedor AS produto_fornecedor
    FROM compras c
    JOIN produtos p ON c.produto_id = p.id
    WHERE c.empresa_id = ? {$sql_filtro}
    ORDER BY c.id DESC
    LIMIT {$por_pagina} OFFSET {$offset}
");
$stmtHist->execute($paramsCompras);
$historico_compras = $stmtHist->fetchAll(PDO::FETCH_ASSOC);

$produtoSelecionadoNome = '';
if ($compra_editar) {
    foreach ($produtos as $p) {
        if ((int)$p['id'] === (int)$compra_editar['produto_id']) {
            $produtoSelecionadoNome = $p['nome'] . ' (Estoque: ' . (int)$p['estoque'] . ' un.)';
            break;
        }
    }
}

$temFiltroAtivo = (!empty($busca_compra) || ($periodo !== 'todos'));

$queryFiltros = http_build_query([
    'page' => 'compras',
    'busca_compra' => $busca_compra,
    'periodo' => $periodo,
    'data_inicio' => $data_inicio,
    'data_fim' => $data_fim
]);
?>

<script src="https://unpkg.com/lucide@latest"></script>

<style>
.compra-card.aberto .compra-seta-icon {
    transform: rotate(180deg);
}
.compra-card-detalhes {
    display: none;
}
.compra-card.aberto .compra-card-detalhes {
    display: block;
}
</style>

<!-- CABEÇALHO DA PÁGINA -->
<header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-black text-white flex items-center gap-2.5 tracking-tight m-0">
            <div class="p-2 bg-emerald-500/10 rounded-xl border border-emerald-500/20">
                <i data-lucide="shopping-cart" class="w-5 h-5 text-emerald-400"></i>
            </div>
            <?= $compra_editar ? 'Editar Compra' : 'Compras' ?>
        </h2>
        <p class="text-sm text-zinc-400 mt-2 m-0">
            Registre entradas de mercadorias com reposição automática de estoque
        </p>
    </div>
</header>

<?= $mensagem ?>

<!-- FORMULÁRIO DE CADASTRO / EDIÇÃO -->
<div id="form-compra-card" class="bg-[#09090b] border <?= $compra_editar ? 'border-emerald-500/40 shadow-[0_0_30px_rgba(16,185,129,0.08)]' : 'border-zinc-800/80' ?> rounded-2xl p-4 sm:p-6 mb-8 max-w-3xl transition-all">
    <div class="flex items-center gap-2.5 mb-6">
        <div class="p-1.5 bg-zinc-800/60 rounded-lg text-zinc-400">
            <i data-lucide="<?= $compra_editar ? 'pencil' : 'plus' ?>" class="w-4 h-4 <?= $compra_editar ? 'text-emerald-400' : '' ?>"></i>
        </div>
        <h3 class="text-base font-bold text-white m-0">
            <?= $compra_editar ? 'Editar Entrada de Compra' : 'Registrar Entrada de Estoque' ?>
        </h3>
    </div>

    <form method="POST" action="index.php?page=compras" class="space-y-4">
        <?php if (function_exists('csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <?php endif; ?>
        <?php if ($compra_editar): ?>
            <input type="hidden" name="id_compra" value="<?= (int)$compra_editar['id'] ?>">
        <?php endif; ?>

        <!-- SELEÇÃO DE PRODUTO COM BUSCA INTEGRADA -->
        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Produto que está entrando *</label>
            
            <input type="hidden" name="produto_id" id="compra_produto_id" value="<?= $compra_editar ? (int)$compra_editar['produto_id'] : '' ?>" required>
            
            <div class="relative" id="custom-search-container-compra">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-zinc-500 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    
                    <input type="text" id="input_busca_produto_compra" 
                           placeholder="Digite o nome do produto..." 
                           autocomplete="off"
                           value="<?= htmlspecialchars($produtoSelecionadoNome) ?>"
                           class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl pl-10 pr-10 py-2.5 outline-none focus:border-emerald-500 transition-colors placeholder:text-zinc-600">

                    <!-- BOTÃO X -->
                    <button type="button" id="btn_limpar_produto_compra" 
                            title="Limpar produto selecionado"
                            class="<?= empty($produtoSelecionadoNome) ? 'hidden' : '' ?> absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-white p-1 bg-transparent border-none cursor-pointer flex items-center justify-center">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>

                    <!-- SETINHA -->
                    <i data-lucide="chevron-down" id="chevron_icon_compra" 
                       class="<?= !empty($produtoSelecionadoNome) ? 'hidden' : '' ?> w-4 h-4 text-zinc-500 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none transition-transform"></i>
                </div>

                <!-- DROPDOWN COM RESULTADOS FILTRAVEIS -->
                <div id="lista_dropdown_produtos_compra" class="hidden absolute left-0 right-0 top-full mt-1.5 bg-[#121215] border border-zinc-800 rounded-xl shadow-2xl max-h-60 overflow-y-auto z-50 divide-y divide-zinc-900">
                    <?php if (empty($produtos)): ?>
                        <div class="p-3 text-xs text-zinc-500 text-center">Nenhum produto cadastrado.</div>
                    <?php else: ?>
                        <?php foreach ($produtos as $p): ?>
                            <div class="item-produto-opcao-compra p-3 hover:bg-zinc-900/80 cursor-pointer transition flex items-center justify-between gap-3 text-sm"
                                 data-id="<?= (int)$p['id'] ?>"
                                 data-nome="<?= htmlspecialchars($p['nome']) ?>"
                                 data-fornecedor="<?= htmlspecialchars($p['fornecedor'] ?? '') ?>"
                                 data-custo="<?= number_format((float)$p['preco_custo'], 2, ',', '.') ?>"
                                 data-estoque="<?= (int)$p['estoque'] ?>">
                                <div class="min-w-0">
                                    <strong class="text-white block text-xs font-semibold truncate"><?= htmlspecialchars($p['nome']) ?></strong>
                                    <?php if (!empty($p['fornecedor'])): ?>
                                        <span class="text-[11px] text-zinc-500 block truncate">Forn: <?= htmlspecialchars($p['fornecedor']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="text-right shrink-0">
                                    <span class="text-zinc-300 font-medium block text-xs">Custo atual: R$ <?= number_format((float)$p['preco_custo'], 2, ',', '.') ?></span>
                                    <span class="text-[10px] text-zinc-500 block">Estoque: <?= (int)$p['estoque'] ?> un.</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- QUANTIDADE, CUSTO E FRETE -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Quantidade *</label>
                <input type="number" name="quantidade" id="compra_qtd" min="1" required
                       value="<?= $compra_editar ? (int)$compra_editar['quantidade'] : '1' ?>"
                       class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Custo Unitário (R$) *</label>
                <input type="text" name="custo_unitario" id="compra_custo" required placeholder="0,00"
                       value="<?= $compra_editar ? number_format((float)$compra_editar['custo_unitario'], 2, ',', '.') : '' ?>"
                       class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Frete Total (R$)</label>
                <input type="text" name="frete" id="compra_frete" placeholder="0,00"
                       value="<?= $compra_editar ? number_format((float)$compra_editar['frete'], 2, ',', '.') : '0,00' ?>"
                       class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors">
            </div>
        </div>

        <!-- OPÇÃO: ATUALIZAR CUSTO NO CADASTRO -->
        <div class="bg-[#000000] border border-zinc-800/80 p-3.5 rounded-xl flex items-center gap-3">
            <input type="checkbox" name="atualizar_custo_produto" id="atualizar_custo_produto" class="w-4 h-4 accent-emerald-500 cursor-pointer rounded" <?= !$compra_editar ? 'checked' : '' ?>>
            <label for="atualizar_custo_produto" class="text-xs text-zinc-300 cursor-pointer font-medium">
                Equilibrar e atualizar o custo médio deste produto no catálogo
            </label>
        </div>

        <!-- BOTÕES DE AÇÃO -->
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" name="salvar_compra"
                    class="flex-1 inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-black text-sm font-bold rounded-xl px-6 py-3 transition-all shadow-[0_0_20px_rgba(16,185,129,0.15)] cursor-pointer">
                <i data-lucide="check" class="w-4 h-4"></i>
                <?= $compra_editar ? 'Atualizar Compra' : 'Confirmar Entrada no Estoque' ?>
            </button>

            <?php if ($compra_editar): ?>
                <a href="index.php?page=compras" 
                   class="inline-flex items-center justify-center bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 text-sm font-semibold rounded-xl px-5 py-3 transition-colors no-underline">
                    Cancelar
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- HISTÓRICO DE ENTRADAS -->
<div class="bg-[#09090b] border border-zinc-800/80 rounded-2xl p-4 sm:p-6">
    
    <!-- HEADER -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-5 border-b border-zinc-900">
        
        <div class="flex items-center gap-2.5">
            <div class="p-1.5 bg-emerald-500/10 rounded-xl border border-emerald-500/20">
                <i data-lucide="history" class="w-4.5 h-4.5 text-emerald-400"></i>
            </div>
            <h3 class="text-base font-bold text-white m-0">Histórico de Entradas</h3>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-zinc-800 text-zinc-400 border border-zinc-700/50">
                <?= $total_compras ?> <?= $total_compras === 1 ? 'registro' : 'registros' ?>
            </span>
        </div>

        <div class="flex items-center gap-2.5 w-full sm:w-auto">
            <form method="GET" action="index.php" class="m-0 relative flex-1 sm:flex-initial">
                <input type="hidden" name="page" value="compras">
                <input type="hidden" name="periodo" value="<?= htmlspecialchars($periodo) ?>">
                <input type="hidden" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>">
                <input type="hidden" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">

                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-zinc-500 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <input type="text" name="busca_compra" placeholder="Buscar produto..." 
                           value="<?= htmlspecialchars($busca_compra) ?>"
                           class="pl-9 pr-8 py-2 bg-[#000000] border border-zinc-800 text-zinc-200 text-xs rounded-xl outline-none focus:border-emerald-500 transition-colors placeholder:text-zinc-600 w-full sm:w-56">
                    
                    <?php if (!empty($busca_compra)): ?>
                        <a href="index.php?<?= http_build_query(['page' => 'compras', 'periodo' => $periodo, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim]) ?>" 
                           class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-300">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="relative" id="container_popover_filtro_compra">
                <button type="button" id="btn_toggle_filtro_compra" 
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold transition cursor-pointer <?= $temFiltroAtivo ? 'bg-emerald-500 text-black shadow-lg shadow-emerald-500/20' : 'bg-zinc-900 border border-zinc-800 text-zinc-300 hover:text-white hover:bg-zinc-800' ?>">
                    <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i>
                    <span>Filtrar</span>
                    <?php if ($temFiltroAtivo): ?>
                        <span class="w-2 h-2 rounded-full bg-black"></span>
                    <?php endif; ?>
                </button>

                <div id="popover_filtro_compra" class="hidden absolute right-0 top-full mt-2 w-72 bg-[#121215] border border-zinc-800 rounded-2xl p-4 shadow-2xl z-50 space-y-4">
                    <form method="GET" action="index.php" class="m-0 space-y-3.5">
                        <input type="hidden" name="page" value="compras">
                        <input type="hidden" name="busca_compra" value="<?= htmlspecialchars($busca_compra) ?>">

                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">Período</label>
                            <select name="periodo" id="filtro_periodo_compra" onchange="toggleCustomDatesCompra(this.value)"
                                    class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-xs rounded-xl px-3 py-2 outline-none focus:border-emerald-500">
                                <option value="todos" <?= $periodo === 'todos' ? 'selected' : '' ?>>Todo o Período</option>
                                <option value="hoje" <?= $periodo === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                                <option value="7dias" <?= $periodo === '7dias' ? 'selected' : '' ?>>Últimos 7 dias</option>
                                <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Este Mês</option>
                                <option value="personalizado" <?= $periodo === 'personalizado' ? 'selected' : '' ?>>Personalizado</option>
                            </select>
                        </div>

                        <div id="container_datas_personalizadas_compra" class="<?= $periodo === 'personalizado' ? '' : 'hidden' ?> space-y-2 pt-1">
                            <div>
                                <span class="text-[10px] text-zinc-500 block mb-1">Data Inicial:</span>
                                <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" 
                                       class="w-full bg-[#000000] border border-zinc-800 text-zinc-300 text-xs rounded-xl px-2.5 py-1.5 outline-none focus:border-emerald-500">
                            </div>
                            <div>
                                <span class="text-[10px] text-zinc-500 block mb-1">Data Final:</span>
                                <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" 
                                       class="w-full bg-[#000000] border border-zinc-800 text-zinc-300 text-xs rounded-xl px-2.5 py-1.5 outline-none focus:border-emerald-500">
                            </div>
                        </div>

                        <div class="flex items-center gap-2 pt-2 border-t border-zinc-900">
                            <button type="submit" class="flex-1 bg-emerald-500 hover:bg-emerald-400 text-black text-xs font-bold py-2 rounded-xl transition cursor-pointer">
                                Aplicar
                            </button>
                            <?php if ($temFiltroAtivo): ?>
                                <a href="index.php?page=compras" class="px-3 py-2 bg-zinc-900 hover:bg-zinc-800 text-rose-400 text-xs font-semibold rounded-xl text-center no-underline">
                                    Limpar
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- LISTA DE CARDS INTERATIVOS -->
    <?php if (count($historico_compras) > 0): ?>
        <div class="space-y-3">
            <?php foreach ($historico_compras as $c): 
                $custoUn = (float)$c['custo_unitario'];
                $freteTot = (float)$c['frete'];
                $custoReal = (float)$c['custo_real_unitario'];
                $qtdEntrada = (int)$c['quantidade'];
                $totalInvestido = ($qtdEntrada * $custoUn) + $freteTot;
            ?>
                <div class="compra-card bg-[#000000] border border-zinc-800/80 rounded-2xl overflow-hidden transition hover:border-zinc-700" id="compra-card-<?= (int)$c['id'] ?>">
                    
                    <button type="button" class="w-full flex items-start sm:items-center justify-between gap-3 p-3.5 sm:p-4 bg-transparent border-none text-left cursor-pointer transition hover:bg-zinc-900/40" onclick="toggleCompraCard(<?= (int)$c['id'] ?>)">
                        
                        <div class="flex items-start sm:items-center gap-3 min-w-0 flex-1">
                            <div class="p-2 bg-zinc-900 border border-zinc-800 rounded-xl text-zinc-400 shrink-0 mt-0.5 sm:mt-0">
                                <i data-lucide="package-plus" class="w-4 h-4 text-emerald-400"></i>
                            </div>
                            
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <strong class="text-white text-sm font-semibold truncate block"><?= htmlspecialchars($c['produto_nome']) ?></strong>
                                    <span class="text-xs font-bold text-emerald-400 px-2 py-0.5 rounded-md bg-emerald-500/10 border border-emerald-500/20 shrink-0">
                                        +<?= $qtdEntrada ?> un.
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 mt-1 text-xs text-zinc-500">
                                    <span><?= !empty($c['data_compra']) ? date('d/m/Y', strtotime($c['data_compra'])) : '—' ?></span>
                                    <?php if (!empty($c['produto_fornecedor'])): ?>
                                        <span>•</span>
                                        <span class="truncate">Forn: <?= htmlspecialchars($c['produto_fornecedor']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 shrink-0 ml-2">
                            <div class="text-right">
                                <span class="text-[10px] text-zinc-500 uppercase tracking-wider block">Custo Unit.</span>
                                <strong class="text-sm font-bold text-white block whitespace-nowrap">
                                    R$ <?= number_format($custoReal, 2, ',', '.') ?>
                                </strong>
                                <span class="text-xs text-zinc-400 block whitespace-nowrap mt-0.5">
                                    Tot: R$ <?= number_format($totalInvestido, 2, ',', '.') ?>
                                </span>
                            </div>

                            <div class="compra-seta-icon w-7 h-7 rounded-lg bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-400 transition-transform duration-200">
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                            </div>
                        </div>
                    </button>

                    <div class="compra-card-detalhes border-t border-zinc-900 p-4 bg-zinc-950/60">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-4 text-xs">
                            <div class="bg-[#09090b] p-2.5 rounded-xl border border-zinc-900">
                                <span class="text-zinc-500 block mb-0.5">Custo Unitário Base</span>
                                <strong class="text-zinc-300 font-medium">R$ <?= number_format($custoUn, 2, ',', '.') ?></strong>
                            </div>

                            <div class="bg-[#09090b] p-2.5 rounded-xl border border-zinc-900">
                                <span class="text-zinc-500 block mb-0.5">Frete Total</span>
                                <strong class="text-zinc-300 font-medium">R$ <?= number_format($freteTot, 2, ',', '.') ?></strong>
                            </div>

                            <div class="bg-[#09090b] p-2.5 rounded-xl border border-zinc-900">
                                <span class="text-zinc-500 block mb-0.5">Frete por Peça</span>
                                <strong class="text-zinc-300 font-medium">R$ <?= number_format($qtdEntrada > 0 ? ($freteTot / $qtdEntrada) : 0, 2, ',', '.') ?></strong>
                            </div>

                            <div class="bg-[#09090b] p-2.5 rounded-xl border border-zinc-900">
                                <span class="text-zinc-500 block mb-0.5">Custo Final Real / Peça</span>
                                <strong class="text-emerald-400 font-bold">R$ <?= number_format($custoReal, 2, ',', '.') ?></strong>
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-900">
                            <a href="index.php?page=compras&acao=editar&id=<?= (int)$c['id'] ?>#form-compra-card" 
                               class="inline-flex items-center gap-1.5 text-xs px-3.5 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-200 font-semibold transition no-underline">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                <span>Editar</span>
                            </a>

                            <button type="button" 
                                    onclick="abrirModalCancelarCompra(<?= (int)$c['id'] ?>, '<?= htmlspecialchars($c['produto_nome']) ?>', <?= $qtdEntrada ?>)"
                                    class="inline-flex items-center gap-1.5 text-xs px-3.5 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 font-semibold transition cursor-pointer">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                <span>Cancelar</span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-zinc-500 text-center py-10 text-sm">
            Nenhuma entrada encontrada para os filtros selecionados.
        </div>
    <?php endif; ?>

    <!-- PAGINAÇÃO -->
    <?php if ($total_paginas > 1): ?>
        <div class="flex items-center justify-center gap-1.5 mt-8 pt-4 border-t border-zinc-900">
            <?php if ($pagina_atual > 1): ?>
                <a href="index.php?<?= $queryFiltros ?>&pagina=<?= $pagina_atual - 1 ?>" 
                   class="px-3 py-1.5 rounded-xl bg-zinc-900 border border-zinc-800 text-zinc-300 text-xs hover:bg-zinc-800 no-underline transition">
                    ‹
                </a>
            <?php endif; ?>

            <?php for ($i = max(1, $pagina_atual - 2); $i <= min($total_paginas, $pagina_atual + 2); $i++): ?>
                <a href="index.php?<?= $queryFiltros ?>&pagina=<?= $i ?>" 
                   class="px-3 py-1.5 rounded-xl text-xs font-semibold no-underline transition <?= $i === $pagina_atual ? 'bg-emerald-500 text-black' : 'bg-zinc-900 border border-zinc-800 text-zinc-300 hover:bg-zinc-800' ?>">
                    <?= $i ?>
                </a>
            <?php endfor; ?>

            <?php if ($pagina_atual < $total_paginas): ?>
                <a href="index.php?<?= $queryFiltros ?>&pagina=<?= $pagina_atual + 1 ?>" 
                   class="px-3 py-1.5 rounded-xl bg-zinc-900 border border-zinc-800 text-zinc-300 text-xs hover:bg-zinc-800 no-underline transition">
                    ›
                </a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- MODAL DE CANCELAMENTO DARK -->
<div id="modal-cancelar-compra" class="hidden fixed inset-0 bg-black/75 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-[#121215] border border-zinc-800 rounded-2xl p-6 text-center shadow-2xl">
        <div class="w-12 h-12 bg-rose-500/10 text-rose-500 border border-rose-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <i data-lucide="alert-triangle" class="w-6 h-6"></i>
        </div>
        
        <h3 class="text-base font-bold text-white mb-1.5">Cancelar entrada de mercadoria?</h3>
        <p class="text-xs text-zinc-400 mb-4">
            Deseja cancelar a entrada de <strong class="text-white" id="modal-compra-produto"></strong>?
        </p>
        <p class="text-xs text-amber-400/90 bg-amber-500/10 border border-amber-500/20 p-3 rounded-xl mb-6">
            A quantidade de <strong class="text-white" id="modal-compra-qtd"></strong> un. adicionada por esta entrada será abatida do estoque do produto.
        </p>

        <form method="POST" action="index.php?page=compras">
            <?php if (function_exists('csrf_token')): ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <?php endif; ?>
            <input type="hidden" name="cancelar_compra" value="1">
            <input type="hidden" name="id_compra" id="modal-cancelar-compra-id" value="">

            <div class="flex items-center gap-2.5">
                <button type="button" onclick="fecharModalCancelarCompra()"
                        class="flex-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 text-xs font-semibold py-2.5 rounded-xl transition cursor-pointer">
                    Não, voltar
                </button>
                <button type="submit"
                        class="flex-1 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold py-2.5 rounded-xl transition cursor-pointer">
                    Sim, cancelar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    const btnToggle = document.getElementById('btn_toggle_filtro_compra');
    const popover = document.getElementById('popover_filtro_compra');
    const container = document.getElementById('container_popover_filtro_compra');

    if (btnToggle && popover) {
        btnToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            popover.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
            if (container && !container.contains(e.target)) {
                popover.classList.add('hidden');
            }
        });
    }

    const inputBusca = document.getElementById('input_busca_produto_compra');
    const dropdown = document.getElementById('lista_dropdown_produtos_compra');
    const hiddenId = document.getElementById('compra_produto_id');
    const campoCusto = document.getElementById('compra_custo');
    const chevron = document.getElementById('chevron_icon_compra');
    const btnLimpar = document.getElementById('btn_limpar_produto_compra');
    const containerSearch = document.getElementById('custom-search-container-compra');
    const itens = document.querySelectorAll('.item-produto-opcao-compra');

    if (!inputBusca || !dropdown) return;

    function atualizarEstadoIcones(temProduto) {
        if (temProduto) {
            if (btnLimpar) btnLimpar.classList.remove('hidden');
            if (chevron) chevron.classList.add('hidden');
        } else {
            if (btnLimpar) btnLimpar.classList.add('hidden');
            if (chevron) chevron.classList.remove('hidden');
        }
    }

    if (btnLimpar) {
        btnLimpar.addEventListener('click', (e) => {
            e.stopPropagation();
            hiddenId.value = '';
            inputBusca.value = '';
            
            atualizarEstadoIcones(false);

            if (campoCusto) campoCusto.value = '';
            
            inputBusca.focus();
            dropdown.classList.remove('hidden');
            filtrarItensCompra();
        });
    }

    inputBusca.addEventListener('focus', () => {
        dropdown.classList.remove('hidden');
        filtrarItensCompra();
    });

    inputBusca.addEventListener('input', () => {
        dropdown.classList.remove('hidden');
        atualizarEstadoIcones(inputBusca.value.trim().length > 0);
        filtrarItensCompra();
    });

    function filtrarItensCompra() {
        const termo = inputBusca.value.toLowerCase().trim();
        itens.forEach(item => {
            const nome = item.getAttribute('data-nome').toLowerCase();
            const forn = item.getAttribute('data-fornecedor').toLowerCase();

            if (nome.includes(termo) || forn.includes(termo)) {
                item.style.display = 'flex';
            } else {
                item.style.display = 'none';
            }
        });
    }

    itens.forEach(item => {
        item.addEventListener('click', () => {
            const id = item.getAttribute('data-id');
            const nome = item.getAttribute('data-nome');
            const custoFormatado = item.getAttribute('data-custo');
            const estoque = parseInt(item.getAttribute('data-estoque')) || 0;

            hiddenId.value = id;
            inputBusca.value = `${nome} (Estoque: ${estoque} un.)`;

            atualizarEstadoIcones(true);

            if (campoCusto) {
                campoCusto.value = custoFormatado;
            }

            dropdown.classList.add('hidden');
        });
    });

    document.addEventListener('click', (e) => {
        if (!containerSearch.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
});

function toggleCompraCard(id) {
    const card = document.getElementById('compra-card-' + id);
    if (card) {
        card.classList.toggle('aberto');
    }
}

function toggleCustomDatesCompra(val) {
    const container = document.getElementById('container_datas_personalizadas_compra');
    if (container) {
        if (val === 'personalizado') {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    }
}

function abrirModalCancelarCompra(id, produtoNome, qtd) {
    document.getElementById('modal-cancelar-compra-id').value = id;
    document.getElementById('modal-compra-produto').textContent = produtoNome;
    document.getElementById('modal-compra-qtd').textContent = qtd;

    const modal = document.getElementById('modal-cancelar-compra');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function fecharModalCancelarCompra() {
    const modal = document.getElementById('modal-cancelar-compra');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}
</script>