<?php
// Configura fuso horário oficial de Brasília
date_default_timezone_set('America/Sao_Paulo');
if (isset($pdo)) {
    try {
        $pdo->exec("SET time_zone = '-03:00'");
    } catch (Throwable $e) {}
}

$empresa_id = $_SESSION['empresa_id'] ?? 0;
$periodo = $_GET['periodo'] ?? 'tudo';
$data_inicio = $_GET['data_inicio'] ?? '';
$data_fim = $_GET['data_fim'] ?? '';

/*
|--------------------------------------------------------------------------
| FILTROS DE PERÍODO (PADRÃO SAAS PROFISSIONAL)
|--------------------------------------------------------------------------
*/

$where_data_vendas = "";
$where_data_despesas = "";
$where_data_receber = "";

$titulo_periodo = "Todo o Histórico";
$titulo_grafico = "Evolução Geral do Negócio";
$formato_agrupamento = "%m/%Y";

switch ($periodo) {
    case 'hoje':
        $where_data_vendas = "
            AND v.data_venda >= CURDATE()
            AND v.data_venda < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ";
        $where_data_despesas = "
            AND d.data_vencimento >= CURDATE()
            AND d.data_vencimento < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ";
        $where_data_receber = "
            AND c.data_vencimento >= CURDATE()
            AND c.data_vencimento < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ";
        $titulo_periodo = "Hoje (" . date('d/m/Y') . ")";
        $titulo_grafico = "Desempenho de Hoje por Hora";
        $formato_agrupamento = "%H:00";
        break;

    case '7dias':
        $where_data_vendas = "
            AND v.data_venda >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            AND v.data_venda < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ";
        $where_data_despesas = "
            AND d.data_vencimento >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            AND d.data_vencimento < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ";
        $where_data_receber = "
            AND c.data_vencimento >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            AND c.data_vencimento < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ";
        $titulo_periodo = "Últimos 7 dias";
        $titulo_grafico = "Evolução Diária (Últimos 7 dias)";
        $formato_agrupamento = "%d/%m";
        break;

    case '30dias':
        $where_data_vendas = "
            AND v.data_venda >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            AND v.data_venda < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ";
        $where_data_despesas = "
            AND d.data_vencimento >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            AND d.data_vencimento < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ";
        $where_data_receber = "
            AND c.data_vencimento >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)
            AND c.data_vencimento < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ";
        $titulo_periodo = "Últimos 30 dias";
        $titulo_grafico = "Evolução Diária (Últimos 30 dias)";
        $formato_agrupamento = "%d/%m";
        break;

    case 'personalizado':
        if (!empty($data_inicio) && !empty($data_fim)) {
            $data_inicio_sql = date('Y-m-d 00:00:00', strtotime($data_inicio));
            $data_fim_sql = date('Y-m-d 23:59:59', strtotime($data_fim));

            $where_data_vendas = " AND v.data_venda >= '{$data_inicio_sql}' AND v.data_venda <= '{$data_fim_sql}' ";
            $where_data_despesas = " AND d.data_vencimento >= '{$data_inicio_sql}' AND d.data_vencimento <= '{$data_fim_sql}' ";
            $where_data_receber = " AND c.data_vencimento >= '{$data_inicio_sql}' AND c.data_vencimento <= '{$data_fim_sql}' ";

            $titulo_periodo = date('d/m/Y', strtotime($data_inicio)) . " até " . date('d/m/Y', strtotime($data_fim));
            $titulo_grafico = "Evolução no Período Selecionado";
            $formato_agrupamento = "%d/%m";
        } else {
            $periodo = 'tudo';
            $titulo_periodo = "Todo o Histórico";
            $titulo_grafico = "Evolução Geral do Negócio";
            $formato_agrupamento = "%m/%Y";
        }
        break;

    case 'tudo':
    default:
        $periodo = 'tudo';
        $where_data_vendas = "";
        $where_data_despesas = "";
        $where_data_receber = "";
        $titulo_periodo = "Todo o Histórico";
        $titulo_grafico = "Evolução Geral do Negócio";
        $formato_agrupamento = "%m/%Y";
        break;
}

function obterClasseCor($valor) {
    if ($valor > 0) return 'text-emerald-400';
    if ($valor < 0) return 'text-rose-500';
    return 'text-zinc-400';
}

/*
|--------------------------------------------------------------------------
| 1. TOTAIS DO PERÍODO
|--------------------------------------------------------------------------
*/
$stmtTotais = $pdo->prepare("
    SELECT
        COUNT(v.id) AS quantidade_vendas,
        COALESCE(SUM(v.valor_total), 0) AS faturamento,
        COALESCE(SUM(v.custo_produto + COALESCE(v.taxas_e_frete, 0)), 0) AS custos_diretos,
        COALESCE(SUM(v.lucro_liquido), 0) AS lucro_vendas
    FROM vendas v
    WHERE v.empresa_id = ?
    {$where_data_vendas}
");
$stmtTotais->execute([$empresa_id]);
$totais = $stmtTotais->fetch(PDO::FETCH_ASSOC);

$faturamento = (float)($totais['faturamento'] ?? 0);
$custos_diretos = (float)($totais['custos_diretos'] ?? 0);
$lucro_vendas = (float)($totais['lucro_vendas'] ?? 0);

/*
|--------------------------------------------------------------------------
| 2. DESPESAS PAGAS
|--------------------------------------------------------------------------
*/
$stmtDesp = $pdo->prepare("
    SELECT COALESCE(SUM(d.valor), 0) AS total_despesas
    FROM despesas d
    WHERE d.empresa_id = ?
      AND d.pago = TRUE
      {$where_data_despesas}
");
$stmtDesp->execute([$empresa_id]);
$total_despesas = (float)($stmtDesp->fetchColumn() ?? 0);

$custos_totais = $custos_diretos + $total_despesas;
$lucro_real_final = $lucro_vendas - $total_despesas;
$margem_final = $faturamento > 0 ? (($lucro_real_final / $faturamento) * 100) : 0;

$classe_lucro = obterClasseCor($lucro_real_final);
$classe_margem = obterClasseCor($margem_final);

/*
|--------------------------------------------------------------------------
| 3. CONTAS A RECEBER GERAL
|--------------------------------------------------------------------------
*/
$stmtReceberGeral = $pdo->prepare("
    SELECT 
        COALESCE(SUM(valor_total - valor_pago), 0) as total_a_receber,
        COUNT(id) as qtd_pendentes,
        COALESCE(SUM(CASE WHEN data_vencimento < CURDATE() THEN (valor_total - valor_pago) ELSE 0 END), 0) as total_atrasado
    FROM contas_receber 
    WHERE empresa_id = ? AND status != 'pago'
");
$stmtReceberGeral->execute([$empresa_id]);
$receberGeral = $stmtReceberGeral->fetch(PDO::FETCH_ASSOC);

$total_a_receber = (float)($receberGeral['total_a_receber'] ?? 0);
$qtd_a_receber = (int)($receberGeral['qtd_pendentes'] ?? 0);
$total_atrasado = (float)($receberGeral['total_atrasado'] ?? 0);

/*
|--------------------------------------------------------------------------
| 4. RESUMO DE HOJE
|--------------------------------------------------------------------------
*/
$stmtHoje = $pdo->prepare("
    SELECT
        COUNT(id) AS total_vendas_hoje,
        COALESCE(SUM(valor_total), 0) AS faturamento_hoje,
        COALESCE(SUM(lucro_liquido), 0) AS lucro_hoje
    FROM vendas
    WHERE empresa_id = ?
      AND data_venda >= CURDATE()
      AND data_venda < DATE_ADD(CURDATE(), INTERVAL 1 DAY)
");
$stmtHoje->execute([$empresa_id]);
$resumo_hoje = $stmtHoje->fetch(PDO::FETCH_ASSOC);

$qtd_hoje = (int)($resumo_hoje['total_vendas_hoje'] ?? 0);
$fat_hoje = (float)($resumo_hoje['faturamento_hoje'] ?? 0);
$lucro_hoje = (float)($resumo_hoje['lucro_hoje'] ?? 0);
$classe_lucro_hoje = obterClasseCor($lucro_hoje);

$stmtRecHoje = $pdo->prepare("
    SELECT COALESCE(SUM(valor_total - valor_pago), 0) as vencendo_hoje
    FROM contas_receber
    WHERE empresa_id = ? 
      AND status != 'pago'
      AND data_vencimento = CURDATE()
");
$stmtRecHoje->execute([$empresa_id]);
$vencendo_hoje = (float)($stmtRecHoje->fetchColumn() ?? 0);

/*
|--------------------------------------------------------------------------
| 5. ESTOQUE BAIXO
|--------------------------------------------------------------------------
*/
$stmtEstoqueBaixo = $pdo->prepare("
    SELECT *
    FROM produtos
    WHERE empresa_id = ?
      AND ativo = TRUE
      AND (alerta_estoque = TRUE OR alerta_estoque IS NULL)
      AND estoque <= 3
    ORDER BY estoque ASC
");
$stmtEstoqueBaixo->execute([$empresa_id]);
$produtos_estoque_baixo = $stmtEstoqueBaixo->fetchAll(PDO::FETCH_ASSOC);

/*
|--------------------------------------------------------------------------
| 6. DADOS DO GRÁFICO
|--------------------------------------------------------------------------
*/
$stmtGrafico = $pdo->prepare("
    SELECT
        DATE_FORMAT(v.data_venda, '{$formato_agrupamento}') AS label_tempo,
        COALESCE(SUM(v.valor_total), 0) AS faturamento_tempo,
        COALESCE(SUM(v.lucro_liquido), 0) AS lucro_tempo
    FROM vendas v
    WHERE v.empresa_id = ?
      {$where_data_vendas}
    GROUP BY label_tempo
    ORDER BY MIN(v.data_venda) ASC
");
$stmtGrafico->execute([$empresa_id]);
$dadosGrafico = $stmtGrafico->fetchAll(PDO::FETCH_ASSOC);

$labelsGrafico = [];
$fatGrafico = [];
$lucroGrafico = [];

foreach ($dadosGrafico as $dg) {
    $labelsGrafico[] = $dg['label_tempo'];
    $fatGrafico[] = (float)$dg['faturamento_tempo'];
    $lucroGrafico[] = (float)$dg['lucro_tempo'];
}

if (count($labelsGrafico) === 0) {
    $labelsGrafico = ['Sem Vendas'];
    $fatGrafico = [0];
    $lucroGrafico = [0];
}

/*
|--------------------------------------------------------------------------
| 7. VENDAS RECENTES
|--------------------------------------------------------------------------
*/
$stmtRecentes = $pdo->prepare("
    SELECT
        v.*,
        p.nome AS produto_nome,
        (SELECT COUNT(*) FROM contas_receber cr WHERE cr.venda_id = v.id AND cr.empresa_id = v.empresa_id) AS total_parcelas
    FROM vendas v
    LEFT JOIN produtos p ON v.produto_id = p.id
    WHERE v.empresa_id = ?
      {$where_data_vendas}
    ORDER BY v.data_venda DESC, v.id DESC
    LIMIT 10
");
$stmtRecentes->execute([$empresa_id]);
$recentes = $stmtRecentes->fetchAll(PDO::FETCH_ASSOC);

function formatarPagamentoDashboard($pagamento, $totalParcelas = 0) {
    $pagamentoLower = strtolower(trim((string)$pagamento));
    $qtd = (int)$totalParcelas;

    if ($qtd > 0 || in_array($pagamentoLower, ['prazo', 'a_prazo', 'fiado', 'a prazo'])) {
        $parcelasFinal = $qtd > 0 ? $qtd : 1;
        return "A Prazo ({$parcelasFinal}x)";
    }

    $pagamentos = [
        'pix' => 'PIX',
        'cartao_credito' => 'Cartão de Crédito',
        'cartao_debito' => 'Cartão de Débito',
        'dinheiro' => 'Dinheiro'
    ];
    return $pagamentos[$pagamentoLower] ?? strtoupper($pagamentoLower);
}
?>

<script src="https://unpkg.com/lucide@latest"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
@media print {
    *, *::before, *::after {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }
    @page {
        margin: 10mm;
        size: A4 portrait;
        background-color: #09090b;
    }
    html, body {
        background-color: #09090b !important;
        color: #f4f4f5 !important;
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    aside, nav, #sidebar, .no-print, form, button, a[href*="vendas"] {
        display: none !important;
    }
    main, .main-content {
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
        max-width: 100% !important;
    }
    .bg-\[\#09090b\], .bg-\[\#000000\], .bg-zinc-900, .bg-zinc-950 {
        background-color: #121215 !important;
        border: 1px solid #27272a !important;
    }
    .grid > div, .card, table, tr {
        break-inside: avoid !important;
        page-break-inside: avoid !important;
    }
    .print-header {
        display: flex !important;
    }
}
</style>

<!-- CABEÇALHO IMPRESSO / PDF -->
<div class="hidden print-header items-center justify-between border-b border-zinc-800 pb-4 mb-6">
    <div class="flex items-center gap-2">
        <span class="text-xl font-black text-white">vende<span class="text-emerald-400">+</span></span>
        <span class="text-xs text-zinc-400 ml-2">| Relatório Executivo de Desempenho</span>
    </div>
    <div class="text-right text-xs text-zinc-400">
        <strong class="text-white block"><?= htmlspecialchars($_SESSION['empresa_nome'] ?? 'Minha Empresa') ?></strong>
        <span>Emissão: <?= date('d/m/Y') ?></span>
    </div>
</div>

<!-- 1. CABEÇALHO DO DASHBOARD (RESPONSIVO COM EXPORTAR MINIMALISTA NO MOBILE) -->
<header class="mb-6">
    <div class="flex items-center justify-between gap-3 mb-1">
        <div class="flex items-center gap-2.5 min-w-0">
            <div class="p-2 bg-emerald-500/10 rounded-xl border border-emerald-500/20 shrink-0">
                <i data-lucide="layout-dashboard" class="w-5 h-5 text-emerald-400"></i>
            </div>
            <div>
                <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight m-0 truncate">Visão Geral</h2>
            </div>
        </div>

        <button onclick="window.print()" type="button" title="Exportar Relatório"
                class="sm:hidden no-print inline-flex items-center justify-center w-10 h-10 bg-[#09090b] hover:bg-zinc-900 border border-zinc-800 text-zinc-300 hover:text-white rounded-xl transition cursor-pointer shrink-0">
            <i data-lucide="printer" class="w-4 h-4"></i>
        </button>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mt-1 sm:mt-0">
        <p class="text-xs sm:text-sm text-zinc-400 m-0">
            Exibindo dados de: <strong class="text-zinc-200 font-semibold"><?= htmlspecialchars($titulo_periodo) ?></strong>
        </p>

        <div class="flex items-center gap-2.5 w-full sm:w-auto no-print mt-2 sm:mt-0">
            <form method="GET" action="index.php" id="formPeriodo" class="m-0 flex-1 sm:flex-none">
                <input type="hidden" name="page" value="dashboard">
                
                <div class="relative">
                    <i data-lucide="calendar" class="w-3.5 h-3.5 text-zinc-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <select name="periodo" id="selectPeriodo" onchange="tratarTrocaPeriodo(this.value)" 
                            class="w-full sm:w-44 pl-8 pr-7 py-2.5 bg-[#09090b] border border-zinc-800 text-zinc-200 text-xs font-medium rounded-xl appearance-none outline-none focus:border-emerald-500 transition cursor-pointer hover:bg-zinc-900">
                        <option value="tudo" <?= $periodo === 'tudo' ? 'selected' : '' ?>>Todo o Período</option>
                        <option value="hoje" <?= $periodo === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                        <option value="7dias" <?= $periodo === '7dias' ? 'selected' : '' ?>>Últimos 7 dias</option>
                        <option value="30dias" <?= $periodo === '30dias' ? 'selected' : '' ?>>Últimos 30 dias</option>
                        <option value="personalizado" <?= $periodo === 'personalizado' ? 'selected' : '' ?>>Personalizado</option>
                    </select>
                    <i data-lucide="chevron-down" class="w-3.5 h-3.5 text-zinc-500 absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                </div>

                <div id="camposPersonalizados" class="<?= $periodo === 'personalizado' ? 'flex' : 'hidden' ?> items-center gap-1.5 mt-2 sm:mt-0 sm:absolute sm:right-full sm:mr-2">
                    <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>" 
                           class="flex-1 sm:w-32 bg-[#09090b] border border-zinc-800 text-zinc-200 text-xs rounded-xl px-2.5 py-1.5 outline-none focus:border-emerald-500">
                    <span class="text-zinc-500 text-xs">até</span>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" 
                           class="flex-1 sm:w-32 bg-[#09090b] border border-zinc-800 text-zinc-200 text-xs rounded-xl px-2.5 py-1.5 outline-none focus:border-emerald-500">
                    <button type="submit" class="bg-zinc-800 hover:bg-zinc-700 text-white text-xs font-semibold px-2.5 py-1.5 rounded-xl transition cursor-pointer">
                        OK
                    </button>
                </div>
            </form>

            <button onclick="window.print()" type="button"
                    class="hidden sm:inline-flex items-center justify-center gap-2 bg-[#09090b] hover:bg-zinc-900 border border-zinc-800 text-zinc-300 text-xs font-semibold rounded-xl px-3.5 py-2.5 transition cursor-pointer shrink-0">
                <i data-lucide="printer" class="w-3.5 h-3.5"></i>
                <span>Exportar</span>
            </button>

            <a href="index.php?page=vendas" 
               class="flex-1 sm:flex-none inline-flex items-center justify-center gap-1.5 bg-emerald-500 hover:bg-emerald-400 text-black text-xs font-bold rounded-xl px-3.5 sm:px-4 py-2.5 transition shadow-[0_0_20px_rgba(16,185,129,0.15)] whitespace-nowrap no-underline shrink-0">
                <i data-lucide="plus" class="w-3.5 h-3.5"></i>
                <span>Registrar Venda</span>
            </a>
        </div>
    </div>
</header>

<!-- 2. CARDS PRINCIPAIS -->
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 sm:gap-4 mb-6">
    <!-- Faturamento -->
    <div class="bg-[#09090b] p-4 sm:p-5 rounded-2xl border border-zinc-800/80">
        <div class="flex items-center gap-2 mb-3">
            <div class="p-1.5 bg-zinc-800/80 rounded-lg text-zinc-300">
                <i data-lucide="line-chart" class="w-3.5 h-3.5"></i>
            </div>
            <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-widest">Faturamento</span>
        </div>
        <div class="text-xl sm:text-2xl font-black text-white tracking-tight truncate">R$ <?= number_format($faturamento, 2, ',', '.') ?></div>
        <div class="text-xs text-zinc-500 mt-1 font-medium">Total bruto gerado</div>
    </div>

    <!-- A Receber -->
    <a href="index.php?page=a-receber" class="bg-[#09090b] p-4 sm:p-5 rounded-2xl border border-zinc-800/80 hover:border-amber-500/50 transition no-underline">
        <div class="flex items-center gap-2 mb-3">
            <div class="p-1.5 bg-amber-500/10 rounded-lg text-amber-400">
                <i data-lucide="wallet" class="w-3.5 h-3.5"></i>
            </div>
            <span class="text-[11px] font-bold text-amber-500/80 uppercase tracking-widest">A Receber</span>
        </div>
        <div class="text-xl sm:text-2xl font-black text-amber-400 tracking-tight truncate">R$ <?= number_format($total_a_receber, 2, ',', '.') ?></div>
        <div class="text-xs text-zinc-500 mt-1 font-medium flex justify-between items-center">
            <span><?= $qtd_a_receber ?> pendência(s)</span>
            <?php if ($total_atrasado > 0): ?>
                <span class="text-rose-400 bg-rose-500/10 px-1.5 py-0.5 rounded text-[10px] uppercase font-bold">Atrasado</span>
            <?php endif; ?>
        </div>
    </a>

    <!-- Custos & Despesas -->
    <div class="bg-[#09090b] p-4 sm:p-5 rounded-2xl border border-zinc-800/80">
        <div class="flex items-center gap-2 mb-3">
            <div class="p-1.5 bg-rose-500/10 rounded-lg text-rose-400">
                <i data-lucide="trending-down" class="w-3.5 h-3.5"></i>
            </div>
            <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-widest">Custos & Desp.</span>
        </div>
        <div class="text-xl sm:text-2xl font-black text-rose-500 tracking-tight truncate">R$ <?= number_format($custos_totais, 2, ',', '.') ?></div>
        <div class="text-xs text-zinc-500 mt-1 font-medium">Produtos + fixos</div>
    </div>

    <!-- Lucro Real (DESTAQUE MÁXIMO) -->
    <div class="bg-[#09090b] p-4 sm:p-5 rounded-2xl border border-emerald-500/30 shadow-[0_0_25px_rgba(16,185,129,0.06)]">
        <div class="flex items-center gap-2 mb-3">
            <div class="p-1.5 bg-emerald-500/10 rounded-lg text-emerald-400">
                <i data-lucide="piggy-bank" class="w-3.5 h-3.5"></i>
            </div>
            <span class="text-[11px] font-bold text-emerald-400 uppercase tracking-widest">Lucro Real</span>
        </div>
        <div class="text-xl sm:text-2xl font-black <?= $classe_lucro ?> tracking-tight truncate">R$ <?= number_format($lucro_real_final, 2, ',', '.') ?></div>
        <div class="text-xs text-zinc-500 mt-1 font-medium">Caixa apurado</div>
    </div>

    <!-- Margem -->
    <div class="bg-[#09090b] p-4 sm:p-5 rounded-2xl border border-zinc-800/80">
        <div class="flex items-center gap-2 mb-3">
            <div class="p-1.5 bg-zinc-800/60 rounded-lg text-zinc-400">
                <i data-lucide="target" class="w-3.5 h-3.5"></i>
            </div>
            <span class="text-[11px] font-bold text-zinc-500 uppercase tracking-widest">Margem</span>
        </div>
        <div class="text-xl sm:text-2xl font-black <?= $classe_margem ?> tracking-tight truncate"><?= number_format($margem_final, 1, ',', '.') ?>%</div>
        <div class="text-xs text-zinc-500 mt-1 font-medium">Retorno líquido</div>
    </div>
</div>

<!-- 3. ALERTA DE ESTOQUE -->
<?php if (count($produtos_estoque_baixo) > 0): ?>
<div id="alertaEstoque" class="no-print bg-[#09090b] border border-amber-500/30 rounded-2xl p-4 sm:p-5 mb-6" style="display: none;">
    <div class="flex items-center justify-between flex-wrap gap-2 mb-3">
        <h3 class="text-xs sm:text-sm font-bold text-amber-500 flex items-center gap-1.5 m-0">
            <i data-lucide="alert-triangle" class="w-4 h-4"></i>
            Estoque Crítico (<?= count($produtos_estoque_baixo) ?> item(ns) com até 3 un.)
        </h3>
        <button onclick="ocultarAlertaEstoque()" class="bg-transparent border-none text-zinc-500 hover:text-zinc-300 text-xs font-medium cursor-pointer">
            Fechar por agora
        </button>
    </div>

    <div class="flex gap-2.5 overflow-x-auto pb-1 no-scrollbar">
        <?php foreach ($produtos_estoque_baixo as $pb): ?>
            <div class="bg-black/60 border border-zinc-800/80 p-3 rounded-xl min-w-[160px] shrink-0">
                <strong class="text-white text-xs block truncate"><?= htmlspecialchars($pb['nome']) ?></strong>
                <span class="text-amber-400 text-[11px] font-medium mt-0.5 block">Restam <?= (int)$pb['estoque'] ?> un.</span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- 4. GRÁFICO COM HIERARQUIA VISUAL (FATURAMENTO NEUTRO E LUCRO EM ESMERALDA) -->
<div class="bg-[#09090b] border border-zinc-800/80 rounded-2xl p-4 sm:p-6 mb-6">
    <div class="flex items-center gap-2 mb-4">
        <div class="p-1.5 bg-zinc-800/80 rounded-xl border border-zinc-700/50">
            <i data-lucide="bar-chart-2" class="w-4 h-4 text-zinc-300"></i>
        </div>
        <div>
            <h3 class="text-sm sm:text-base font-bold text-white m-0">
                <?= htmlspecialchars($titulo_grafico) ?>
            </h3>
            <p class="text-xs text-zinc-500 m-0">
                Comparativo entre faturamento bruto e lucro líquido real
            </p>
        </div>
    </div>

    <div style="height: 280px; position: relative;">
        <canvas id="graficoFluxo"></canvas>
    </div>
</div>

<!-- 5. RESUMO DE HOJE -->
<div class="mb-6">
    <div class="flex items-center gap-1.5 mb-2.5">
        <div class="p-1 bg-amber-500/10 rounded-lg">
            <i data-lucide="sun" class="w-3.5 h-3.5 text-amber-500"></i>
        </div>
        <h3 class="text-xs font-bold text-zinc-300 uppercase tracking-wider m-0">
            Desempenho de Hoje <span class="text-zinc-500 font-medium">(<?= date('d/m/Y') ?>)</span>
        </h3>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
        <div class="bg-[#09090b] p-3.5 sm:p-4 rounded-xl border border-zinc-800/80">
            <span class="text-[10px] sm:text-[11px] font-bold text-zinc-500 uppercase tracking-wider block mb-1">Vendas</span>
            <strong class="text-lg sm:text-xl font-black text-white"><?= $qtd_hoje ?></strong>
        </div>

        <div class="bg-[#09090b] p-3.5 sm:p-4 rounded-xl border border-zinc-800/80">
            <span class="text-[10px] sm:text-[11px] font-bold text-zinc-500 uppercase tracking-wider block mb-1">Faturado</span>
            <strong class="text-lg sm:text-xl font-black text-white truncate block">R$ <?= number_format($fat_hoje, 2, ',', '.') ?></strong>
        </div>

        <div class="bg-[#09090b] p-3.5 sm:p-4 rounded-xl border border-zinc-800/80">
            <span class="text-[10px] sm:text-[11px] font-bold text-zinc-500 uppercase tracking-wider block mb-1">Lucro Real</span>
            <strong class="text-lg sm:text-xl font-black <?= $classe_lucro_hoje ?> truncate block">
                R$ <?= number_format($lucro_hoje, 2, ',', '.') ?>
            </strong>
        </div>

        <div class="bg-[#09090b] p-3.5 sm:p-4 rounded-xl border <?= $vencendo_hoje > 0 ? 'border-amber-500/30' : 'border-zinc-800/80' ?>">
            <span class="text-[10px] sm:text-[11px] font-bold <?= $vencendo_hoje > 0 ? 'text-amber-500' : 'text-zinc-500' ?> uppercase tracking-wider block mb-1">Vence Hoje</span>
            <strong class="text-lg sm:text-xl font-black <?= $vencendo_hoje > 0 ? 'text-amber-400' : 'text-zinc-400' ?> truncate block">
                <?= $vencendo_hoje > 0 ? 'R$ ' . number_format($vencendo_hoje, 2, ',', '.') : 'R$ 0,00' ?>
            </strong>
        </div>
    </div>
</div>

<!-- 6. VENDAS RECENTES COM RÓTULO CLARO -->
<div class="bg-[#09090b] border border-zinc-800/80 rounded-2xl p-4 sm:p-6">
    <div class="flex items-center justify-between gap-4 mb-4">
        <div class="flex items-center gap-2">
            <div class="p-1.5 bg-emerald-500/10 rounded-xl border border-emerald-500/20">
                <i data-lucide="clock" class="w-4 h-4 text-emerald-400"></i>
            </div>
            <div>
                <h3 class="text-sm sm:text-base font-bold text-white m-0">Vendas Recentes</h3>
            </div>
        </div>

        <a href="index.php?page=vendas" class="text-xs font-semibold text-emerald-400 hover:text-emerald-300 no-underline flex items-center gap-1 transition">
            <span>Ver todas</span>
            <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
        </a>
    </div>

    <?php if (count($recentes) > 0): ?>
        <div class="space-y-2.5">
            <?php foreach ($recentes as $v): 
                $totalV = (float)$v['valor_total'];
                $lucroV = (float)$v['lucro_liquido'];
                $cor_lucro_venda = obterClasseCor($lucroV);
                $totalParcelas = (int)($v['total_parcelas'] ?? 0);
                $isPrazo = ($totalParcelas > 0 || in_array(strtolower(trim((string)$v['forma_pagamento'])), ['prazo', 'a_prazo', 'fiado', 'a prazo']));
            ?>
                <div class="bg-[#000000] border border-zinc-800/80 hover:border-zinc-700 rounded-xl p-3 sm:p-3.5 flex items-center justify-between gap-3 transition">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="p-2 bg-zinc-900 border border-zinc-800 rounded-lg text-zinc-400 shrink-0">
                            <i data-lucide="shopping-bag" class="w-4 h-4 text-emerald-400"></i>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <strong class="text-white text-xs sm:text-sm font-semibold truncate block">
                                    <?= htmlspecialchars($v['produto_nome'] ?? 'Produto removido') ?>
                                </strong>
                                <span class="text-xs text-zinc-500 font-medium shrink-0"><?= (int)$v['quantidade'] ?> un.</span>
                            </div>

                            <div class="flex items-center gap-1.5 mt-0.5 text-xs text-zinc-500">
                                <span><?= !empty($v['data_venda']) ? date('d/m/Y', strtotime($v['data_venda'])) : '—' ?></span>
                                <span>•</span>
                                <?php if ($isPrazo): ?>
                                    <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-400 truncate">
                                        <?= formatarPagamentoDashboard($v['forma_pagamento'], $totalParcelas) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-[11px] font-medium text-zinc-400 truncate">
                                        <?= htmlspecialchars(formatarPagamentoDashboard($v['forma_pagamento'], $totalParcelas)) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- RÓTULOS EXPLÍCITOS -->
                    <div class="flex items-center gap-3 shrink-0 ml-2">
                        <div class="text-right">
                            <span class="text-[10px] text-zinc-500 block">Total</span>
                            <strong class="text-xs sm:text-sm font-bold text-zinc-200 block whitespace-nowrap">
                                R$ <?= number_format($totalV, 2, ',', '.') ?>
                            </strong>
                        </div>

                        <div class="text-right">
                            <span class="text-[10px] text-zinc-500 block">Lucro Real</span>
                            <strong class="text-xs sm:text-sm font-bold <?= $cor_lucro_venda ?> block whitespace-nowrap">
                                R$ <?= number_format($lucroV, 2, ',', '.') ?>
                            </strong>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-zinc-500 text-center py-8 text-sm">
            Nenhuma venda registrada neste período.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    const desativadoGeral = localStorage.getItem("vende_ocultar_alerta_estoque") === "true";
    const fechadoNestaSessao = sessionStorage.getItem("vende_fechou_alerta_temp") === "true";
    const alertaBox = document.getElementById("alertaEstoque");

    if (alertaBox && !desativadoGeral && !fechadoNestaSessao) {
        alertaBox.style.display = "block";
    }
});

function ocultarAlertaEstoque() {
    const alertaBox = document.getElementById("alertaEstoque");
    if (alertaBox) {
        alertaBox.style.display = "none";
        sessionStorage.setItem("vende_fechou_alerta_temp", "true");
    }
}

function tratarTrocaPeriodo(valor) {
    const campos = document.getElementById('camposPersonalizados');
    if (valor === 'personalizado') {
        campos.classList.remove('hidden');
        campos.classList.add('flex');
    } else {
        campos.classList.remove('flex');
        campos.classList.add('hidden');
        document.getElementById('formPeriodo').submit();
    }
}

/*
|--------------------------------------------------------------------------
| CHART.JS CONFIGURAÇÃO: FATURAMENTO GRAFITE (#3f3f46) + LUCRO ESMERALDA (#10b981)
|--------------------------------------------------------------------------
*/
Chart.defaults.color = '#71717a';
Chart.defaults.borderColor = '#18181b';

const canvasFluxo = document.getElementById('graficoFluxo');

if (canvasFluxo) {
    const ctxFluxo = canvasFluxo.getContext('2d');

    new Chart(ctxFluxo, {
        type: 'bar',
        data: {
            labels: <?= json_encode($labelsGrafico, JSON_UNESCAPED_UNICODE) ?>,
            datasets: [
                {
                    label: 'Faturamento (Volume)',
                    data: <?= json_encode($fatGrafico) ?>,
                    backgroundColor: '#3f3f46',
                    hoverBackgroundColor: '#52525b',
                    borderRadius: 6,
                    maxBarThickness: 32,
                    barPercentage: 0.5,
                    categoryPercentage: 0.5
                },
                {
                    label: 'Lucro Líquido Real',
                    data: <?= json_encode($lucroGrafico) ?>,
                    backgroundColor: '#10b981',
                    hoverBackgroundColor: '#34d399',
                    borderRadius: 6,
                    maxBarThickness: 32,
                    barPercentage: 0.5,
                    categoryPercentage: 0.5
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    align: 'end',
                    labels: {
                        boxWidth: 8,
                        boxHeight: 8,
                        borderRadius: 3,
                        useBorderRadius: true,
                        font: { size: 11, weight: '600' },
                        color: '#a1a1aa',
                        padding: 12
                    }
                },
                tooltip: {
                    backgroundColor: '#09090b',
                    titleColor: '#fff',
                    bodyColor: '#cbd5e1',
                    borderColor: '#27272a',
                    borderWidth: 1,
                    padding: 10,
                    cornerRadius: 8,
                    callbacks: {
                        label: function(context) {
                            const label = context.dataset.label || '';
                            const value = context.parsed.y || 0;
                            return ' ' + label + ': R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(255, 255, 255, 0.04)' },
                    ticks: {
                        color: '#71717a',
                        font: { size: 10 },
                        callback: function(value) {
                            return 'R$ ' + value.toLocaleString('pt-BR');
                        }
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { 
                        color: '#71717a',
                        font: { size: 10 }
                    }
                }
            }
        }
    });
}
</script>