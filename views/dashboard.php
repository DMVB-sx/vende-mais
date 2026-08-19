<?php
$empresa_id = $_SESSION['empresa_id'] ?? 0;
$periodo = $_GET['periodo'] ?? 'tudo';

/*
|--------------------------------------------------------------------------
| FILTROS DE PERÍODO
|--------------------------------------------------------------------------
| Todos os valores possíveis são definidos internamente.
| Isso evita problemas com SQL ao trocar o período.
*/

$where_data_vendas = "";
$where_data_despesas = "";

$titulo_periodo = "Todo o Histórico";
$titulo_grafico = "📊 Evolução Geral do Negócio";
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

        $titulo_periodo = "Hoje (" . date('d/m/Y') . ")";
        $titulo_grafico = "📊 Desempenho de Hoje por Hora";
        $formato_agrupamento = "%H:00";
        break;


    case 'mes_atual':
        $where_data_vendas = "
            AND v.data_venda >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
            AND v.data_venda < DATE_ADD(
                DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01'),
                INTERVAL 1 MONTH
            )
        ";

        $where_data_despesas = "
            AND d.data_vencimento >= DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
            AND d.data_vencimento < DATE_ADD(
                DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01'),
                INTERVAL 1 MONTH
            )
        ";

        $titulo_periodo = "Este Mês (" . date('m/Y') . ")";
        $titulo_grafico = "📊 Evolução Diária (Este Mês)";
        $formato_agrupamento = "%d/%m";
        break;


    case 'mes_passado':
        $where_data_vendas = "
            AND v.data_venda >= DATE_SUB(
                DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01'),
                INTERVAL 1 MONTH
            )
            AND v.data_venda < DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
        ";

        $where_data_despesas = "
            AND d.data_vencimento >= DATE_SUB(
                DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01'),
                INTERVAL 1 MONTH
            )
            AND d.data_vencimento < DATE_FORMAT(CURRENT_DATE(), '%Y-%m-01')
        ";

        $titulo_periodo = "Mês Passado";
        $titulo_grafico = "📊 Evolução Diária (Mês Passado)";
        $formato_agrupamento = "%d/%m";
        break;


    case 'ano_atual':
        $where_data_vendas = "
            AND v.data_venda >= DATE_FORMAT(CURRENT_DATE(), '%Y-01-01')
            AND v.data_venda < DATE_ADD(
                DATE_FORMAT(CURRENT_DATE(), '%Y-01-01'),
                INTERVAL 1 YEAR
            )
        ";

        $where_data_despesas = "
            AND d.data_vencimento >= DATE_FORMAT(CURRENT_DATE(), '%Y-01-01')
            AND d.data_vencimento < DATE_ADD(
                DATE_FORMAT(CURRENT_DATE(), '%Y-01-01'),
                INTERVAL 1 YEAR
            )
        ";

        $titulo_periodo = "Ano de " . date('Y');
        $titulo_grafico = "📊 Evolução Mensal (" . date('Y') . ")";
        $formato_agrupamento = "%m/%Y";
        break;


    case 'tudo':
    default:
        $periodo = 'tudo';

        $where_data_vendas = "";
        $where_data_despesas = "";

        $titulo_periodo = "Todo o Histórico";
        $titulo_grafico = "📊 Evolução Geral do Negócio";
        $formato_agrupamento = "%m/%Y";
        break;
}


/*
|--------------------------------------------------------------------------
| FUNÇÃO PARA COR DOS VALORES
|--------------------------------------------------------------------------
*/

function obterClasseCor($valor)
{
    if ($valor > 0) {
        return 'text-positive';
    }

    if ($valor < 0) {
        return 'text-negative';
    }

    return 'text-neutral';
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


/*
|--------------------------------------------------------------------------
| VALORES DAS VENDAS
|--------------------------------------------------------------------------
*/

$quantidade_vendas = (int)($totais['quantidade_vendas'] ?? 0);

$faturamento = (float)($totais['faturamento'] ?? 0);

$custos_diretos = (float)($totais['custos_diretos'] ?? 0);

$lucro_vendas = (float)($totais['lucro_vendas'] ?? 0);


/*
|--------------------------------------------------------------------------
| 2. DESPESAS PAGAS
|--------------------------------------------------------------------------
*/

$stmtDesp = $pdo->prepare("
    SELECT
        COALESCE(SUM(d.valor), 0) AS total_despesas
    FROM despesas d
    WHERE d.empresa_id = ?
      AND d.pago = TRUE
      {$where_data_despesas}
");

$stmtDesp->execute([$empresa_id]);

$total_despesas = (float)(
    $stmtDesp->fetchColumn() ?? 0
);


/*
|--------------------------------------------------------------------------
| CÁLCULOS FINAIS
|--------------------------------------------------------------------------
*/

$custos_totais = $custos_diretos + $total_despesas;

$lucro_real_final = $lucro_vendas - $total_despesas;

$margem_final = $faturamento > 0
    ? (($lucro_real_final / $faturamento) * 100)
    : 0;

$classe_lucro = obterClasseCor($lucro_real_final);
$classe_margem = obterClasseCor($margem_final);


/*
|--------------------------------------------------------------------------
| 3. RESUMO DE HOJE
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


/*
|--------------------------------------------------------------------------
| 4. ESTOQUE BAIXO
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
| 5. DADOS DO GRÁFICO
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


/*
|--------------------------------------------------------------------------
| CASO NÃO EXISTAM VENDAS
|--------------------------------------------------------------------------
*/

if (count($labelsGrafico) === 0) {

    $labelsGrafico = ['Sem Vendas'];

    $fatGrafico = [0];

    $lucroGrafico = [0];
}


/*
|--------------------------------------------------------------------------
| 6. VENDAS RECENTES
|--------------------------------------------------------------------------
*/

$stmtRecentes = $pdo->prepare("
    SELECT
        v.*,
        p.nome AS produto_nome
    FROM vendas v
    LEFT JOIN produtos p
        ON v.produto_id = p.id
    WHERE v.empresa_id = ?
      {$where_data_vendas}
    ORDER BY v.data_venda DESC, v.id DESC
    LIMIT 10
");

$stmtRecentes->execute([$empresa_id]);

$recentes = $stmtRecentes->fetchAll(PDO::FETCH_ASSOC);

?>

<style>

@media print {

    .sidebar,
    .header button,
    .header form,
    .header a,
    .btn-print,
    .no-print {
        display: none !important;
    }

    body {
        background: #fff !important;
        color: #000 !important;
    }

    .card,
    .table-container {
        background: #fff !important;
        border: 1px solid #ccc !important;
        color: #000 !important;
    }

    th,
    td {
        color: #000 !important;
        border-bottom: 1px solid #ccc !important;
    }

    .card-title,
    h2,
    h3,
    p {
        color: #000 !important;
    }
}

</style>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>


<!-- CABEÇALHO -->

<header class="header">

    <div>

        <h2>📊 Visão Geral</h2>

        <p style="
            color: #a1a1aa;
            font-size: 13.5px;
            margin-top: 2px;
        ">
            Exibindo dados de:
            <strong><?= htmlspecialchars($titulo_periodo) ?></strong>
        </p>

    </div>


    <div
        style="
            display: flex;
            gap: 10px;
            align-items: center;
        "
        class="no-print"
    >

        <button
            onclick="window.print()"
            class="btn-print"
            style="
                background: #18181b !important;
                color: #fff !important;
                border: 1px solid #27272a !important;
                padding: 9px 15px;
                border-radius: 6px;
                font-weight: 500;
                cursor: pointer;
                font-size: 13px;
            "
        >
            📄 Exportar
        </button>


        <form
            method="GET"
            action="index.php"
            style="
                display: flex;
                gap: 10px;
            "
        >

            <input
                type="hidden"
                name="page"
                value="dashboard"
            >

            <select
                name="periodo"
                onchange="this.form.submit()"
                style="
                    padding: 8px 12px;
                    font-size: 13px;
                "
            >

                <option
                    value="tudo"
                    <?= $periodo === 'tudo' ? 'selected' : '' ?>
                >
                    🗓️ Todo o Período
                </option>

                <option
                    value="hoje"
                    <?= $periodo === 'hoje' ? 'selected' : '' ?>
                >
                    ☀️ Hoje
                </option>

                <option
                    value="mes_atual"
                    <?= $periodo === 'mes_atual' ? 'selected' : '' ?>
                >
                    📅 Este Mês
                </option>

                <option
                    value="mes_passado"
                    <?= $periodo === 'mes_passado' ? 'selected' : '' ?>
                >
                    ⏪ Mês Passado
                </option>

                <option
                    value="ano_atual"
                    <?= $periodo === 'ano_atual' ? 'selected' : '' ?>
                >
                    📆 Ano Atual
                </option>

            </select>

        </form>


        <a
            href="index.php?page=vendas"
            style="
                background: #27272a;
                color: #fff;
                text-decoration: none;
                padding: 9px 16px;
                border-radius: 6px;
                font-weight: 500;
                font-size: 13px;
                border: 1px solid #3f3f46;
            "
        >
            + Registrar venda
        </a>

    </div>

</header>



<!-- RESUMO DE HOJE -->

<div
    class="table-container"
    style="
        margin-bottom: 24px;
        background: #09090b;
        border: 1px solid #18181b;
    "
>

    <div
        style="
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        "
    >

        <div>

            <h3
                style="
                    color: #ffffff;
                    margin-bottom: 4px;
                    font-size: 15px;
                "
            >
                ☀️ Resumo de Hoje (<?= date('d/m/Y') ?>)
            </h3>

            <p
                style="
                    color: #71717a;
                    font-size: 13px;
                    margin: 0;
                "
            >
                Desempenho do dia atual
            </p>

        </div>


        <div
            style="
                display: flex;
                gap: 16px;
                flex-wrap: wrap;
            "
        >

            <!-- VENDAS -->

            <div
                style="
                    background: #000000;
                    padding: 10px 16px;
                    border-radius: 6px;
                    border: 1px solid #18181b;
                "
            >

                <span
                    style="
                        font-size: 11.5px;
                        color: #a1a1aa;
                        display: block;
                        text-transform: uppercase;
                    "
                >
                    🛒 Vendas
                </span>

                <strong
                    style="
                        color: #fff;
                        font-size: 15px;
                    "
                >
                    <?= $qtd_hoje ?>
                </strong>

            </div>


            <!-- FATURAMENTO -->

            <div
                style="
                    background: #000000;
                    padding: 10px 16px;
                    border-radius: 6px;
                    border: 1px solid #18181b;
                "
            >

                <span
                    style="
                        font-size: 11.5px;
                        color: #a1a1aa;
                        display: block;
                        text-transform: uppercase;
                    "
                >
                    💵 Faturado
                </span>

                <strong
                    style="
                        color: #fff;
                        font-size: 15px;
                    "
                >
                    R$ <?= number_format($fat_hoje, 2, ',', '.') ?>
                </strong>

            </div>


            <!-- LUCRO -->

            <div
                style="
                    background: #000000;
                    padding: 10px 16px;
                    border-radius: 6px;
                    border: 1px solid #18181b;
                "
            >

                <span
                    style="
                        font-size: 11.5px;
                        color: #a1a1aa;
                        display: block;
                        text-transform: uppercase;
                    "
                >
                    💰 Lucro
                </span>

                <strong
                    class="<?= $classe_lucro_hoje ?>"
                    style="font-size: 15px;"
                >
                    R$ <?= number_format($lucro_hoje, 2, ',', '.') ?>
                </strong>

            </div>

        </div>

    </div>

</div>



<!-- CARDS PRINCIPAIS -->

<div class="cards-grid">


    <!-- VENDAS -->

    <div class="card">

        <div class="card-title">
            🛒 Vendas
        </div>

        <div class="card-value">
            <?= number_format($quantidade_vendas, 0, ',', '.') ?>
        </div>

    </div>


    <!-- FATURAMENTO -->

    <div class="card">

        <div class="card-title">
            📈 Faturamento
        </div>

        <div class="card-value">
            R$ <?= number_format($faturamento, 2, ',', '.') ?>
        </div>

    </div>


    <!-- CUSTOS -->

    <div class="card">

        <div class="card-title">
            📉 Custos & Despesas
        </div>

        <div
            class="card-value"
            style="color: #f43f5e;"
        >
            R$ <?= number_format($custos_totais, 2, ',', '.') ?>
        </div>

    </div>


    <!-- LUCRO -->

    <div class="card">

        <div class="card-title">
            💰 Lucro
        </div>

        <div class="card-value <?= $classe_lucro ?>">
            R$ <?= number_format($lucro_real_final, 2, ',', '.') ?>
        </div>

    </div>


    <!-- MARGEM -->

    <div class="card">

        <div class="card-title">
            🎯 Margem
        </div>

        <div class="card-value <?= $classe_margem ?>">
            <?= number_format($margem_final, 1, ',', '.') ?>%
        </div>

    </div>

</div>



<!-- ALERTA DE ESTOQUE -->

<?php if (count($produtos_estoque_baixo) > 0): ?>

<div
    id="alertaEstoque"
    class="table-container no-print"
    style="
        margin-bottom: 28px;
        border: 1px solid #27272a;
        background: #09090b;
        display: none;
    "
>

    <div
        style="
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            flex-wrap: wrap;
            gap: 10px;
        "
    >

        <h3
            style="
                color: #f59e0b;
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 0;
                font-size: 14px;
            "
        >
            ⚠️ Estoque Crítico
            (<?= count($produtos_estoque_baixo) ?> item(ns) com até 3 un.)
        </h3>


        <div
            style="
                display: flex;
                align-items: center;
                gap: 12px;
            "
        >

            <button
                onclick="ocultarAlertaEstoque(true)"
                style="
                    background: transparent;
                    border: 1px solid #27272a;
                    color: #a1a1aa;
                    padding: 4px 10px;
                    border-radius: 4px;
                    font-size: 11.5px;
                    cursor: pointer;
                "
            >
                Não mostrar novamente
            </button>


            <button
                onclick="ocultarAlertaEstoque(false)"
                title="Fechar por agora"
                style="
                    background: transparent;
                    border: none;
                    color: #71717a;
                    font-size: 16px;
                    font-weight: bold;
                    cursor: pointer;
                    padding: 0 4px;
                "
            >
                ✕
            </button>

        </div>

    </div>


    <div
        style="
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        "
    >

        <?php foreach ($produtos_estoque_baixo as $pb): ?>

            <div
                style="
                    background: #000000;
                    padding: 10px 14px;
                    border-radius: 6px;
                    border: 1px solid #18181b;
                    flex: 1;
                    min-width: 180px;
                "
            >

                <strong
                    style="
                        color: #fff;
                        display: block;
                        font-size: 13.5px;
                    "
                >
                    <?= htmlspecialchars($pb['nome']) ?>
                </strong>

                <span
                    style="
                        font-size: 12.5px;
                        color: #f59e0b;
                    "
                >
                    Restam <?= (int)$pb['estoque'] ?> un.
                </span>

            </div>

        <?php endforeach; ?>

    </div>

</div>

<?php endif; ?>



<!-- GRÁFICO -->

<div
    class="table-container"
    style="margin-bottom: 28px;"
>

    <div style="margin-bottom: 16px;">

        <h3
            style="
                font-size: 15px;
                margin: 0;
            "
        >
            <?= htmlspecialchars($titulo_grafico) ?>
        </h3>

        <p
            style="
                color: #71717a;
                font-size: 12.5px;
                margin: 2px 0 0 0;
            "
        >
            Comparativo entre faturamento e lucro líquido
        </p>

    </div>


    <div style="height: 300px;">

        <canvas id="graficoFluxo"></canvas>

    </div>

</div>



<!-- VENDAS RECENTES -->

<div class="table-container">

    <h3>
        🛒 Vendas Recentes
    </h3>


    <table>

        <thead>

            <tr>

                <th>Data</th>

                <th>Produto</th>

                <th>Canal</th>

                <th>Quantidade</th>

                <th>Valor</th>

                <th>Lucro Líquido</th>

            </tr>

        </thead>


        <tbody>

            <?php if (count($recentes) > 0): ?>

                <?php foreach ($recentes as $v): ?>

                    <?php
                    $cor_lucro_venda = obterClasseCor(
                        (float)$v['lucro_liquido']
                    );
                    ?>

                    <tr>

                        <td>
                            <?= date(
                                'd/m/Y H:i',
                                strtotime($v['data_venda'])
                            ) ?>
                        </td>


                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $v['produto_nome'] ?? 'Produto removido'
                                ) ?>
                            </strong>

                        </td>


                        <td>
                            <?= htmlspecialchars(
                                $v['canal'] ?? '-'
                            ) ?>
                        </td>


                        <td>
                            <?= (int)$v['quantidade'] ?>x
                        </td>


                        <td>

                            R$
                            <?= number_format(
                                (float)$v['valor_total'],
                                2,
                                ',',
                                '.'
                            ) ?>

                        </td>


                        <td class="<?= $cor_lucro_venda ?>">

                            <strong>

                                R$
                                <?= number_format(
                                    (float)$v['lucro_liquido'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>

                            </strong>

                        </td>

                    </tr>

                <?php endforeach; ?>


            <?php else: ?>

                <tr>

                    <td
                        colspan="6"
                        style="
                            color: #71717a;
                            text-align: center;
                        "
                    >
                        Nenhuma venda registrada neste período.
                    </td>

                </tr>

            <?php endif; ?>

        </tbody>

    </table>

</div>



<script>

/*
|--------------------------------------------------------------------------
| ALERTA DE ESTOQUE
|--------------------------------------------------------------------------
*/

document.addEventListener("DOMContentLoaded", function () {

    const ocultarPermanente =
        localStorage.getItem(
            "vende_ocultar_alerta_estoque"
        );

    const alertaBox =
        document.getElementById("alertaEstoque");


    if (
        alertaBox &&
        ocultarPermanente !== "true"
    ) {

        alertaBox.style.display = "block";

    }

});


function ocultarAlertaEstoque(permanente) {

    const alertaBox =
        document.getElementById("alertaEstoque");


    if (alertaBox) {

        alertaBox.style.display = "none";

    }


    if (permanente) {

        localStorage.setItem(
            "vende_ocultar_alerta_estoque",
            "true"
        );

    }

}


/*
|--------------------------------------------------------------------------
| GRÁFICO
|--------------------------------------------------------------------------
*/

Chart.defaults.color = '#71717a';

Chart.defaults.borderColor = '#18181b';


const canvasFluxo =
    document.getElementById('graficoFluxo');


if (canvasFluxo) {

    const ctxFluxo =
        canvasFluxo.getContext('2d');


    new Chart(ctxFluxo, {

        type: 'bar',


        data: {

            labels:
                <?= json_encode(
                    $labelsGrafico,
                    JSON_UNESCAPED_UNICODE
                ) ?>,


            datasets: [

                {

                    label: 'Faturamento',

                    data:
                        <?= json_encode(
                            $fatGrafico
                        ) ?>,

                    backgroundColor: '#3b82f6',

                    borderRadius: 6,

                    barPercentage: 0.5,

                    categoryPercentage: 0.6

                },


                {

                    label: 'Lucro Líquido',

                    data:
                        <?= json_encode(
                            $lucroGrafico
                        ) ?>,

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

                        font: {
                            size: 12,
                            weight: '500'
                        },

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

                            const label =
                                context.dataset.label || '';

                            const value =
                                context.parsed.y || 0;


                            return label +
                                ': R$ ' +
                                value.toLocaleString(
                                    'pt-BR',
                                    {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    }
                                );

                        }

                    }

                }

            },


            scales: {

                y: {

                    beginAtZero: true,

                    grid: {
                        color: '#18181b'
                    },


                    ticks: {

                        color: '#71717a',


                        callback: function(value) {

                            return 'R$ ' +
                                value.toLocaleString(
                                    'pt-BR'
                                );

                        }

                    }

                },


                x: {

                    grid: {
                        display: false
                    },


                    ticks: {
                        color: '#71717a'
                    }

                }

            }

        }

    });

}

</script>