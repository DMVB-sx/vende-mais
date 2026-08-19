<?php
$empresa_id = $_SESSION['empresa_id'];
$periodo = $_GET['periodo'] ?? 'tudo';

$where_data_vendas = "";
$where_data_despesas = "";
$formato_agrupamento = "%d/%m";

switch ($periodo) {
    case 'hoje':
        $where_data_vendas = " AND DATE(v.data_venda) = CURDATE() ";
        $where_data_despesas = " AND DATE(data_vencimento) = CURDATE() ";
        $titulo_periodo = "Hoje (" . date('d/m/Y') . ")";
        $titulo_grafico = "📊 Desempenho de Hoje por Hora";
        $formato_agrupamento = "%H:00";
        break;
    case 'mes_atual':
        $where_data_vendas = " AND YEAR(v.data_venda) = YEAR(CURRENT_DATE()) AND MONTH(v.data_venda) = MONTH(CURRENT_DATE()) ";
        $where_data_despesas = " AND YEAR(data_vencimento) = YEAR(CURRENT_DATE()) AND MONTH(data_vencimento) = MONTH(CURRENT_DATE()) ";
        $titulo_periodo = "Este Mês (" . date('m/Y') . ")";
        $titulo_grafico = "📊 Evolução Diária (Este Mês)";
        $formato_agrupamento = "%d/%m";
        break;
    case 'mes_passado':
        $where_data_vendas = " AND v.data_venda >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 1 MONTH) AND v.data_venda < DATE_FORMAT(NOW(), '%Y-%m-01') ";
        $where_data_despesas = " AND data_vencimento >= DATE_SUB(DATE_FORMAT(NOW(), '%Y-%m-01'), INTERVAL 1 MONTH) AND data_vencimento < DATE_FORMAT(NOW(), '%Y-%m-01') ";
        $titulo_periodo = "Mês Passado";
        $titulo_grafico = "📊 Evolução Diária (Mês Passado)";
        $formato_agrupamento = "%d/%m";
        break;
    case 'ano_atual':
        $where_data_vendas = " AND YEAR(v.data_venda) = YEAR(CURRENT_DATE()) ";
        $where_data_despesas = " AND YEAR(data_vencimento) = YEAR(CURRENT_DATE()) ";
        $titulo_periodo = "Ano de " . date('Y');
        $titulo_grafico = "📊 Evolução Mensal (" . date('Y') . ")";
        $formato_agrupamento = "%m/%Y";
        break;
    case 'tudo':
    default:
        $where_data_vendas = "";
        $where_data_despesas = "";
        $titulo_periodo = "Todo o Histórico";
        $titulo_grafico = "📊 Evolução Geral do Negócio";
        $formato_agrupamento = "%m/%Y";
        break;
}

// 1. Totais acumulados
$stmtTotais = $pdo->prepare("
    SELECT 
        SUM(v.valor_total) as faturamento,
        SUM(v.custo_produto + v.taxas_e_frete) as custos_diretos,
        SUM(v.lucro_liquido) as lucro_bruto
    FROM vendas v
    WHERE v.empresa_id = ? {$where_data_vendas}
");
$stmtTotais->execute([$empresa_id]);
$totais = $stmtTotais->fetch();

$faturamento = (float)($totais['faturamento'] ?? 0);
$custos_diretos = (float)($totais['custos_diretos'] ?? 0);
$lucro_bruto = (float)($totais['lucro_bruto'] ?? 0);

// 2. Despesas Pagas
$stmtDesp = $pdo->prepare("
    SELECT SUM(valor) as total_despesas 
    FROM despesas 
    WHERE empresa_id = ? AND pago = TRUE {$where_data_despesas}
");
$stmtDesp->execute([$empresa_id]);
$total_despesas = (float)($stmtDesp->fetch()['total_despesas'] ?? 0);

$custos_totais = $custos_diretos + $total_despesas;
$lucro_real_final = $lucro_bruto - $total_despesas;
$margem_final = $faturamento > 0 ? (($lucro_real_final / $faturamento) * 100) : 0;

function obterClasseCor($valor) {
    if ($valor > 0) return 'text-positive';
    if ($valor < 0) return 'text-negative';
    return 'text-neutral';
}

$classe_lucro = obterClasseCor($lucro_real_final);
$classe_margem = obterClasseCor($margem_final);

// 3. Resumo de Hoje
$stmtHoje = $pdo->prepare("
    SELECT 
        COUNT(id) as total_vendas_hoje,
        SUM(valor_total) as faturamento_hoje,
        SUM(lucro_liquido) as lucro_hoje
    FROM vendas
    WHERE empresa_id = ? AND DATE(data_venda) = CURDATE()
");
$stmtHoje->execute([$empresa_id]);
$resumo_hoje = $stmtHoje->fetch();

$qtd_hoje = (int)($resumo_hoje['total_vendas_hoje'] ?? 0);
$fat_hoje = (float)($resumo_hoje['faturamento_hoje'] ?? 0);
$lucro_hoje = (float)($resumo_hoje['lucro_hoje'] ?? 0);
$classe_lucro_hoje = obterClasseCor($lucro_hoje);

// 4. Estoque Baixo
$stmtEstoqueBaixo = $pdo->prepare("
    SELECT * FROM produtos 
    WHERE empresa_id = ? 
      AND ativo = TRUE 
      AND (alerta_estoque = TRUE OR alerta_estoque IS NULL)
      AND estoque <= 3 
    ORDER BY estoque ASC
");
$stmtEstoqueBaixo->execute([$empresa_id]);
$produtos_estoque_baixo = $stmtEstoqueBaixo->fetchAll();

// 5. Dados do Gráfico (Garante dados vazios se não houver registros)
$stmtGrafico = $pdo->prepare("
    SELECT 
        DATE_FORMAT(v.data_venda, '{$formato_agrupamento}') as label_tempo,
        SUM(v.valor_total) as faturamento_tempo,
        SUM(v.lucro_liquido) as lucro_tempo
    FROM vendas v
    WHERE v.empresa_id = ? {$where_data_vendas}
    GROUP BY label_tempo
    ORDER BY MIN(v.data_venda) ASC
");
$stmtGrafico->execute([$empresa_id]);
$dadosGrafico = $stmtGrafico->fetchAll();

$labelsGrafico = [];
$fatGrafico = [];
$lucroGrafico = [];

if (count($dadosGrafico) > 0) {
    foreach ($dadosGrafico as $dg) {
        $labelsGrafico[] = $dg['label_tempo'];
        $fatGrafico[] = (float)$dg['faturamento_tempo'];
        $lucroGrafico[] = (float)$dg['lucro_tempo'];
    }
} else {
    $labelsGrafico = ['Sem Vendas'];
    $fatGrafico = [0];
    $lucroGrafico = [0];
}

// 6. Vendas recentes
$stmtRecentes = $pdo->prepare("
    SELECT v.*, p.nome as produto_nome 
    FROM vendas v 
    JOIN produtos p ON v.produto_id = p.id 
    WHERE v.empresa_id = ? {$where_data_vendas}
    ORDER BY v.id DESC LIMIT 10
");
$stmtRecentes->execute([$empresa_id]);
$recentes = $stmtRecentes->fetchAll();
?>

<style>
@media print {
    .sidebar, .header button, .header form, .header a, .btn-print, .no-print {
        display: none !important;
    }
    body { background: #fff !important; color: #000 !important; }
    .card, .table-container { background: #fff !important; border: 1px solid #ccc !important; color: #000 !important; }
    th, td { color: #000 !important; border-bottom: 1px solid #ccc !important; }
    .card-title, h2, h3, p { color: #000 !important; }
}
</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<header class="header">
  <div>
    <h2>📊 Visão Geral</h2>
    <p style="color: #a1a1aa; font-size: 13.5px; margin-top: 2px;">Exibindo dados de: <strong><?= $titulo_periodo ?></strong></p>
  </div>
  
  <div style="display: flex; gap: 10px; align-items: center;" class="no-print">
    <button onclick="window.print()" class="btn-print" style="background: #18181b !important; color: #fff !important; border: 1px solid #27272a !important; padding: 9px 15px; border-radius: 6px; font-weight: 500; cursor: pointer; font-size: 13px;">📄 Exportar</button>

    <form method="GET" action="index.php" style="display: flex; gap: 10px;">
        <input type="hidden" name="page" value="dashboard">
        <select name="periodo" onchange="this.form.submit()" style="padding: 8px 12px; font-size: 13px;">
            <option value="tudo" <?= $periodo == 'tudo' ? 'selected' : '' ?>>🗓️ Todo o Período</option>
            <option value="hoje" <?= $periodo == 'hoje' ? 'selected' : '' ?>>☀️ Hoje</option>
            <option value="mes_atual" <?= $periodo == 'mes_atual' ? 'selected' : '' ?>>📅 Este Mês</option>
            <option value="mes_passado" <?= $periodo == 'mes_passado' ? 'selected' : '' ?>>⏪ Mês Passado</option>
            <option value="ano_atual" <?= $periodo == 'ano_atual' ? 'selected' : '' ?>>📆 Ano Atual</option>
        </select>
    </form>

    <a href="index.php?page=vendas" style="background: #27272a; color: #fff; text-decoration: none; padding: 9px 16px; border-radius: 6px; font-weight: 500; font-size: 13px; border: 1px solid #3f3f46;">+ Registrar venda</a>
  </div>
</header>

<!-- RESUMO DO DIA (HOJE) -->
<div class="table-container" style="margin-bottom: 24px; background: #09090b; border: 1px solid #18181b;">
  <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;">
    <div>
      <h3 style="color: #ffffff; margin-bottom: 4px; font-size: 15px;">☀️ Resumo de Hoje (<?= date('d/m/Y') ?>)</h3>
      <p style="color: #71717a; font-size: 13px; margin: 0;">Desempenho em tempo real do dia atual</p>
    </div>

    <div style="display: flex; gap: 16px; flex-wrap: wrap;">
      <div style="background: #000000; padding: 10px 16px; border-radius: 6px; border: 1px solid #18181b;">
        <span style="font-size: 11.5px; color: #a1a1aa; display: block; text-transform: uppercase;">🛍️ Pedidos</span>
        <strong style="color: #fff; font-size: 15px;"><?= $qtd_hoje ?> vendas</strong>
      </div>

      <div style="background: #000000; padding: 10px 16px; border-radius: 6px; border: 1px solid #18181b;">
        <span style="font-size: 11.5px; color: #a1a1aa; display: block; text-transform: uppercase;">💵 Faturado</span>
        <strong style="color: #fff; font-size: 15px;">R$ <?= number_format($fat_hoje, 2, ',', '.') ?></strong>
      </div>

      <div style="background: #000000; padding: 10px 16px; border-radius: 6px; border: 1px solid #18181b;">
        <span style="font-size: 11.5px; color: #a1a1aa; display: block; text-transform: uppercase;">💰 Lucro</span>
        <strong class="<?= $classe_lucro_hoje ?>" style="font-size: 15px;">R$ <?= number_format($lucro_hoje, 2, ',', '.') ?></strong>
      </div>
    </div>
  </div>
</div>

<!-- CARDS PRINCIPAIS -->
<div class="cards-grid">
  <div class="card">
    <div class="card-title">📈 Faturamento</div>
    <div class="card-value">R$ <?= number_format($faturamento, 2, ',', '.') ?></div>
  </div>

  <div class="card">
    <div class="card-title">📉 Custos & Despesas</div>
    <div class="card-value" style="color: #f43f5e;">R$ <?= number_format($custos_totais, 2, ',', '.') ?></div>
  </div>

  <div class="card">
    <div class="card-title">💰 Lucro</div>
    <div class="card-value <?= $classe_lucro ?>">R$ <?= number_format($lucro_real_final, 2, ',', '.') ?></div>
  </div>

  <div class="card">
    <div class="card-title">🎯 Margem</div>
    <div class="card-value <?= $classe_margem ?>"><?= number_format($margem_final, 1, ',', '.') ?>%</div>
  </div>
</div>

<!-- ALERTA DE ESTOQUE BAIXO -->
<?php if (count($produtos_estoque_baixo) > 0): ?>
<div id="alertaEstoque" class="table-container no-print" style="margin-bottom: 28px; border: 1px solid #27272a; background: #09090b; display: none;">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
    <h3 style="color: #f59e0b; display: flex; align-items: center; gap: 8px; margin: 0; font-size: 14px;">
      ⚠️ Estoque Crítico (<?= count($produtos_estoque_baixo) ?> item(ns) com até 3 un.)
    </h3>
    
    <div style="display: flex; align-items: center; gap: 12px;">
      <button onclick="ocultarAlertaEstoque(true)" style="background: transparent; border: 1px solid #27272a; color: #a1a1aa; padding: 4px 10px; border-radius: 4px; font-size: 11.5px; cursor: pointer; transition: all 0.2s;" onmouseover="this.style.color='#fff'; this.style.borderColor='#3f3f46';" onmouseout="this.style.color='#a1a1aa'; this.style.borderColor='#27272a';">
        Não mostrar novamente
      </button>
      <button onclick="ocultarAlertaEstoque(false)" title="Fechar por agora" style="background: transparent; border: none; color: #71717a; font-size: 16px; font-weight: bold; cursor: pointer; padding: 0 4px; line-height: 1;" onmouseover="this.style.color='#fff';" onmouseout="this.style.color='#71717a';">
        ✕
      </button>
    </div>
  </div>

  <div style="display: flex; gap: 12px; flex-wrap: wrap;">
    <?php foreach ($produtos_estoque_baixo as $pb): ?>
      <div style="background: #000000; padding: 10px 14px; border-radius: 6px; border: 1px solid #18181b; flex: 1; min-width: 180px;">
        <strong style="color: #fff; display: block; font-size: 13.5px;"><?= htmlspecialchars($pb['nome']) ?></strong>
        <span style="font-size: 12.5px; color: #f59e0b;">Restam <?= $pb['estoque'] ?> un.</span>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<!-- GRÁFICO (SEMPRE VISÍVEL) -->
<div class="table-container" style="margin-bottom: 28px;">
  <div style="margin-bottom: 16px;">
    <h3 style="font-size: 15px; margin: 0;"><?= $titulo_grafico ?></h3>
    <p style="color: #71717a; font-size: 12.5px; margin: 2px 0 0 0;">Comparativo entre faturamento e lucro líquido</p>
  </div>

  <div style="height: 300px;">
    <canvas id="graficoFluxo"></canvas>
  </div>
</div>

<!-- TABELA DE VENDAS RECENTES -->
<div class="table-container">
  <h3>🛒 Vendas Recentes</h3>
  <table>
    <thead>
      <tr>
        <th>Data</th>
        <th>Produto</th>
        <th>Canal</th>
        <th>Lucro Líquido</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($recentes) > 0): ?>
        <?php foreach ($recentes as $v): ?>
          <?php $cor_lucro_venda = obterClasseCor($v['lucro_liquido']); ?>
          <tr>
            <td><?= date('d/m/Y H:i', strtotime($v['data_venda'])) ?></td>
            <td><strong><?= htmlspecialchars($v['produto_nome']) ?></strong> (<?= $v['quantidade'] ?>x)</td>
            <td><?= htmlspecialchars($v['canal']) ?></td>
            <td class="<?= $cor_lucro_venda ?>"><strong>R$ <?= number_format($v['lucro_liquido'], 2, ',', '.') ?></strong></td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="4" style="color: #71717a; text-align: center;">Nenhuma venda registrada neste período.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
// Alerta LocalStorage
document.addEventListener("DOMContentLoaded", () => {
    const ocultarPermanente = localStorage.getItem("vende_ocultar_alerta_estoque");
    const alertaBox = document.getElementById("alertaEstoque");

    if (alertaBox && ocultarPermanente !== "true") {
        alertaBox.style.display = "block";
    }
});

function ocultarAlertaEstoque(permanente) {
    const alertaBox = document.getElementById("alertaEstoque");
    if (alertaBox) {
        alertaBox.style.display = "none";
    }
    if (permanente) {
        localStorage.setItem("vende_ocultar_alerta_estoque", "true");
    }
}

// Configuração padrão Chart.js
Chart.defaults.color = '#71717a';
Chart.defaults.borderColor = '#18181b';

const ctxFluxo = document.getElementById('graficoFluxo').getContext('2d');
new Chart(ctxFluxo, {
    type: 'bar',
    data: {
        labels: <?= json_encode($labelsGrafico) ?>,
        datasets: [
            {
                label: 'Faturamento',
                data: <?= json_encode($fatGrafico) ?>,
                backgroundColor: '#3b82f6',
                borderRadius: 6,
                barPercentage: 0.5,
                categoryPercentage: 0.6
            },
            {
                label: 'Lucro Líquido',
                data: <?= json_encode($lucroGrafico) ?>,
                backgroundColor: '#10b981',
                borderRadius: 6,
                barPercentage: 0.5,
                categoryPercentage: 0.6
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
                labels: {
                    boxWidth: 12,
                    boxHeight: 12,
                    borderRadius: 3,
                    useBorderRadius: true,
                    font: { size: 12, weight: '500' },
                    color: '#a1a1aa'
                }
            },
            tooltip: {
                backgroundColor: '#09090b',
                titleColor: '#fff',
                bodyColor: '#cbd5e1',
                borderColor: '#27272a',
                borderWidth: 1,
                padding: 10,
                callbacks: {
                    label: function(context) {
                        let label = context.dataset.label || '';
                        let value = context.parsed.y || 0;
                        return label + ': R$ ' + value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    }
                }
            }
        },
        scales: {
            y: { 
                beginAtZero: true, 
                grid: { color: '#18181b' },
                ticks: {
                    color: '#71717a',
                    callback: function(value) {
                        return 'R$ ' + value.toLocaleString('pt-BR');
                    }
                }
            },
            x: { 
                grid: { display: false },
                ticks: { color: '#71717a' }
            }
        }
    }
});
</script>