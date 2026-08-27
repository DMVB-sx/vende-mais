<?php

$mensagem = '';
$empresa_id = $_SESSION['empresa_id'] ?? 0;

/*
|--------------------------------------------------------------------------
| 1. CANCELAR VENDA
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['cancelar_venda'])
) {

    try {

        if (function_exists('validar_csrf')) {
            validar_csrf();
        }

        $id_venda = (int)($_POST['id_venda'] ?? 0);

        if ($id_venda <= 0) {
            throw new Exception('Venda inválida.');
        }

        $stmtVenda = $pdo->prepare("
            SELECT id, produto_id, quantidade
            FROM vendas
            WHERE id = ? AND empresa_id = ?
            LIMIT 1
        ");

        $stmtVenda->execute([$id_venda, $empresa_id]);
        $venda = $stmtVenda->fetch(PDO::FETCH_ASSOC);

        if (!$venda) {
            throw new Exception('Venda não encontrada.');
        }

        $pdo->beginTransaction();

        $stmtEstoque = $pdo->prepare("
            UPDATE produtos
            SET estoque = estoque + ?
            WHERE id = ? AND empresa_id = ?
        ");
        $stmtEstoque->execute([(int)$venda['quantidade'], (int)$venda['produto_id'], $empresa_id]);

        $stmtDelConta = $pdo->prepare("
            DELETE FROM contas_receber 
            WHERE venda_id = ? AND empresa_id = ?
        ");
        $stmtDelConta->execute([$id_venda, $empresa_id]);

        $stmtExcluir = $pdo->prepare("
            DELETE FROM vendas
            WHERE id = ? AND empresa_id = ?
        ");
        $stmtExcluir->execute([$id_venda, $empresa_id]);

        $pdo->commit();

        $mensagem = '
            <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                <div>
                    <strong class="font-semibold block text-emerald-300">Venda cancelada com sucesso!</strong>
                    <span class="text-xs text-emerald-400/80">A quantidade foi devolvida ao estoque e as parcelas foram removidas.</span>
                </div>
            </div>
        ';

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Erro ao cancelar venda: ' . $e->getMessage());
        $mensagem = '
            <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                <div>
                    <strong class="font-semibold block text-rose-300">Não foi possível cancelar a venda.</strong>
                </div>
            </div>
        ';
    }
}

/*
|--------------------------------------------------------------------------
| 2. CADASTRAR OU EDITAR VENDA
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['salvar_venda'])
) {

    try {

        if (function_exists('validar_csrf')) {
            validar_csrf();
        }

        $id_venda = (int)($_POST['id_venda'] ?? 0);
        $produto_id = (int)($_POST['produto_id'] ?? 0);
        $canal = trim($_POST['canal'] ?? '');
        $forma_pagamento = trim($_POST['forma_pagamento'] ?? 'pix');
        $quantidade = (int)($_POST['quantidade'] ?? 0);
        $preco_venda = (float)str_replace(',', '.', trim($_POST['preco_venda'] ?? '0'));
        $taxas_e_frete = (float)str_replace(',', '.', trim($_POST['taxas_e_frete'] ?? '0'));

        $cliente_nome = trim($_POST['cliente_nome'] ?? '');
        $cliente_telefone = trim($_POST['cliente_telefone'] ?? '');
        $data_vencimento = trim($_POST['data_vencimento'] ?? '');
        $num_parcelas = max(1, (int)($_POST['num_parcelas'] ?? 1));
        $valor_entrada = (float)str_replace(['.', ','], ['', '.'], $_POST['valor_entrada'] ?? '0');

        if ($produto_id <= 0) {
            throw new Exception('Selecione um produto.');
        }

        if ($quantidade <= 0) {
            throw new Exception('A quantidade deve ser maior que zero.');
        }

        if ($preco_venda < 0) {
            throw new Exception('Preço de venda inválido.');
        }

        $valor_total = $preco_venda * $quantidade;

        if ($forma_pagamento === 'prazo') {
            if (empty($cliente_nome)) {
                throw new Exception('Informe o nome do cliente para vendas a prazo.');
            }
            if (empty($data_vencimento)) {
                throw new Exception('Informe a data do 1º vencimento.');
            }
            if ($valor_entrada < 0 || $valor_entrada > $valor_total) {
                throw new Exception('O valor de entrada não pode ser negativo nem superior ao total da venda.');
            }
        }

        if ($id_venda > 0) {

            $stmtVendaOld = $pdo->prepare("SELECT * FROM vendas WHERE id = ? AND empresa_id = ? LIMIT 1");
            $stmtVendaOld->execute([$id_venda, $empresa_id]);
            $vendaAntiga = $stmtVendaOld->fetch(PDO::FETCH_ASSOC);

            if (!$vendaAntiga) {
                throw new Exception('Venda não encontrada.');
            }

            $stmtProdutoNovo = $pdo->prepare("SELECT id, nome, preco_custo, estoque FROM produtos WHERE id = ? AND empresa_id = ? AND ativo = TRUE LIMIT 1");
            $stmtProdutoNovo->execute([$produto_id, $empresa_id]);
            $produtoNovo = $stmtProdutoNovo->fetch(PDO::FETCH_ASSOC);

            if (!$produtoNovo) {
                throw new Exception('Produto não encontrado.');
            }

            $pdo->beginTransaction();

            $stmtDevolver = $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ? AND empresa_id = ?");
            $stmtDevolver->execute([(int)$vendaAntiga['quantidade'], (int)$vendaAntiga['produto_id'], $empresa_id]);

            $stmtProdutoAtualizado = $pdo->prepare("SELECT estoque, preco_custo FROM produtos WHERE id = ? AND empresa_id = ? LIMIT 1");
            $stmtProdutoAtualizado->execute([$produto_id, $empresa_id]);
            $produtoAtualizado = $stmtProdutoAtualizado->fetch(PDO::FETCH_ASSOC);

            if ((int)$produtoAtualizado['estoque'] < $quantidade) {
                throw new Exception('Estoque insuficiente! Disponível: ' . $produtoAtualizado['estoque'] . ' un.');
            }

            $custo_unitario = (float)$produtoAtualizado['preco_custo'];
            $custo_total = $custo_unitario * $quantidade;
            $lucro_total_potencial = $valor_total - $custo_total - $taxas_e_frete;

            if ($forma_pagamento === 'prazo') {
                $proporcao_paga = $valor_total > 0 ? ($valor_entrada / $valor_total) : 0;
                $lucro_liquido = $lucro_total_potencial * $proporcao_paga;
            } else {
                $lucro_liquido = $lucro_total_potencial;
            }

            $stmtUpdate = $pdo->prepare("
                UPDATE vendas
                SET produto_id = ?, canal = ?, forma_pagamento = ?, quantidade = ?, preco_venda = ?, taxas_e_frete = ?, custo_produto = ?, lucro_liquido = ?, valor_total = ?
                WHERE id = ? AND empresa_id = ?
            ");
            $stmtUpdate->execute([
                $produto_id, $canal, $forma_pagamento, $quantidade, $preco_venda, $taxas_e_frete, $custo_total, $lucro_liquido, $valor_total, $id_venda, $empresa_id
            ]);

            $stmtDelC = $pdo->prepare("DELETE FROM contas_receber WHERE venda_id = ? AND empresa_id = ?");
            $stmtDelC->execute([$id_venda, $empresa_id]);

            if ($forma_pagamento === 'prazo') {
                $saldo_parcelar = max(0, $valor_total - $valor_entrada);

                if ($saldo_parcelar > 0) {
                    $valor_cada_parcela = round($saldo_parcelar / $num_parcelas, 2);
                    $diferenca_centavos = $saldo_parcelar - ($valor_cada_parcela * $num_parcelas);

                    for ($i = 1; $i <= $num_parcelas; $i++) {
                        $vencimentoParcela = date('Y-m-d', strtotime("+ " . ($i - 1) . " months", strtotime($data_vencimento)));
                        $valorEstaParcela = ($i === $num_parcelas) ? ($valor_cada_parcela + $diferenca_centavos) : $valor_cada_parcela;
                        $nomeClienteComParcela = ($num_parcelas > 1) ? "{$cliente_nome} ({$i}/{$num_parcelas})" : $cliente_nome;

                        $stmtInsParcela = $pdo->prepare("
                            INSERT INTO contas_receber (empresa_id, venda_id, cliente_nome, cliente_telefone, valor_total, valor_pago, data_vencimento, status)
                            VALUES (?, ?, ?, ?, ?, 0.00, ?, 'pendente')
                        ");
                        $stmtInsParcela->execute([$empresa_id, $id_venda, $nomeClienteComParcela, $cliente_telefone, $valorEstaParcela, $vencimentoParcela]);
                    }
                }
            }

            $stmtAbater = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND empresa_id = ?");
            $stmtAbater->execute([$quantidade, $produto_id, $empresa_id]);

            $pdo->commit();
            $mensagem = '
                <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                    <div>
                        <strong class="font-semibold block text-emerald-300">Venda atualizada com sucesso!</strong>
                    </div>
                </div>
            ';
        } else {

            $stmtProduto = $pdo->prepare("SELECT id, preco_custo, estoque FROM produtos WHERE id = ? AND empresa_id = ? AND ativo = TRUE LIMIT 1");
            $stmtProduto->execute([$produto_id, $empresa_id]);
            $produto = $stmtProduto->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                throw new Exception('Produto não encontrado ou inativo.');
            }

            if ((int)$produto['estoque'] < $quantidade) {
                throw new Exception('Estoque insuficiente! Disponível: ' . (int)$produto['estoque'] . ' un.');
            }

            $custo_unitario = (float)$produto['preco_custo'];
            $custo_total = $custo_unitario * $quantidade;
            $lucro_total_potencial = $valor_total - $custo_total - $taxas_e_frete;

            if ($forma_pagamento === 'prazo') {
                $proporcao_paga = $valor_total > 0 ? ($valor_entrada / $valor_total) : 0;
                $lucro_liquido = $lucro_total_potencial * $proporcao_paga;
            } else {
                $lucro_liquido = $lucro_total_potencial;
            }

            $pdo->beginTransaction();

            $stmtVenda = $pdo->prepare("
                INSERT INTO vendas (empresa_id, produto_id, canal, forma_pagamento, quantidade, preco_venda, taxas_e_frete, custo_produto, lucro_liquido, valor_total)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmtVenda->execute([
                $empresa_id, $produto_id, $canal, $forma_pagamento, $quantidade, $preco_venda, $taxas_e_frete, $custo_total, $lucro_liquido, $valor_total
            ]);
            $nova_venda_id = (int)$pdo->lastInsertId();

            if ($forma_pagamento === 'prazo') {
                $saldo_parcelar = max(0, $valor_total - $valor_entrada);

                if ($saldo_parcelar > 0) {
                    $valor_cada_parcela = round($saldo_parcelar / $num_parcelas, 2);
                    $diferenca_centavos = $saldo_parcelar - ($valor_cada_parcela * $num_parcelas);

                    for ($i = 1; $i <= $num_parcelas; $i++) {
                        $vencimentoParcela = date('Y-m-d', strtotime("+ " . ($i - 1) . " months", strtotime($data_vencimento)));
                        $valorEstaParcela = ($i === $num_parcelas) ? ($valor_cada_parcela + $diferenca_centavos) : $valor_cada_parcela;
                        $nomeClienteComParcela = ($num_parcelas > 1) ? "{$cliente_nome} ({$i}/{$num_parcelas})" : $cliente_nome;

                        $stmtInsParcela = $pdo->prepare("
                            INSERT INTO contas_receber (empresa_id, venda_id, cliente_nome, cliente_telefone, valor_total, valor_pago, data_vencimento, status)
                            VALUES (?, ?, ?, ?, ?, 0.00, ?, 'pendente')
                        ");
                        $stmtInsParcela->execute([$empresa_id, $nova_venda_id, $nomeClienteComParcela, $cliente_telefone, $valorEstaParcela, $vencimentoParcela]);
                    }
                }
            }

            $stmtEstoque = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND empresa_id = ?");
            $stmtEstoque->execute([$quantidade, $produto_id, $empresa_id]);

            $pdo->commit();

            $msgParcelas = ($forma_pagamento === 'prazo' && $num_parcelas > 1) ? " ({$num_parcelas} parcelas geradas em A Receber)" : "";

            $mensagem = '
                <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                    <div>
                        <strong class="font-semibold block text-emerald-300">Venda registrada com sucesso!' . $msgParcelas . '</strong>
                        <span class="text-xs text-emerald-400/80">Lucro computado no caixa: R$ ' . number_format($lucro_liquido, 2, ',', '.') . '</span>
                    </div>
                </div>
            ';
        }

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('Erro ao salvar venda: ' . $e->getMessage());
        $mensagem = '
            <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                <div>
                    <strong class="font-semibold block text-rose-300">' . htmlspecialchars($e->getMessage()) . '</strong>
                </div>
            </div>
        ';
    }
}

/*
|--------------------------------------------------------------------------
| 3. BUSCAR VENDA PARA EDIÇÃO COMPLETA
|--------------------------------------------------------------------------
*/

$venda_editar = null;
$conta_vinculada = null;
$total_parcelas_existentes = 1;
$valor_entrada_existente = 0.00;
$produto_selecionado_estoque_max = 999;
$produto_selecionado_custo = 0.0;

if (isset($_GET['acao']) && $_GET['acao'] === 'editar' && isset($_GET['id'])) {
    $id_editar = (int)$_GET['id'];
    $stmtEd = $pdo->prepare("SELECT * FROM vendas WHERE id = ? AND empresa_id = ? LIMIT 1");
    $stmtEd->execute([$id_editar, $empresa_id]);
    $venda_editar = $stmtEd->fetch(PDO::FETCH_ASSOC);

    if ($venda_editar) {
        $stmtC = $pdo->prepare("SELECT * FROM contas_receber WHERE venda_id = ? AND empresa_id = ? ORDER BY id ASC");
        $stmtC->execute([$id_editar, $empresa_id]);
        $todasContas = $stmtC->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($todasContas)) {
            $total_parcelas_existentes = count($todasContas);
            $conta_vinculada = $todasContas[0];
            
            $somaParcelas = 0.0;
            foreach ($todasContas as $c) {
                $somaParcelas += (float)$c['valor_total'];
            }
            $valor_entrada_existente = max(0, (float)$venda_editar['valor_total'] - $somaParcelas);
        }
    }
}

/*
|--------------------------------------------------------------------------
| 4. PRODUTOS & HISTÓRICO
|--------------------------------------------------------------------------
*/

$stmtProd = $pdo->prepare("SELECT id, nome, fornecedor, preco_venda, preco_custo, estoque FROM produtos WHERE empresa_id = ? AND ativo = TRUE ORDER BY estoque DESC, nome ASC");
$stmtProd->execute([$empresa_id]);
$produtos = $stmtProd->fetchAll(PDO::FETCH_ASSOC);

$totalComEstoque = 0;
foreach ($produtos as $p) {
    if ((int)$p['estoque'] > 0) $totalComEstoque++;
}

$busca_venda = trim($_GET['busca_venda'] ?? '');
$pagamento_filtro = trim($_GET['pagamento_filtro'] ?? '');
$periodo = trim($_GET['periodo'] ?? 'todos');
$data_inicio = trim($_GET['data_inicio'] ?? '');
$data_fim = trim($_GET['data_fim'] ?? '');

$paramsVendas = [$empresa_id];
$sql_filtro = '';

if (!empty($busca_venda)) {
    $sql_filtro .= " AND (p.nome LIKE ? OR p.fornecedor LIKE ? OR v.canal LIKE ? OR EXISTS (SELECT 1 FROM contas_receber cr WHERE cr.venda_id = v.id AND cr.empresa_id = v.empresa_id AND cr.cliente_nome LIKE ?)) ";
    $term = "%{$busca_venda}%";
    $paramsVendas[] = $term;
    $paramsVendas[] = $term;
    $paramsVendas[] = $term;
    $paramsVendas[] = $term;
}

if (!empty($pagamento_filtro)) {
    if ($pagamento_filtro === 'prazo') {
        $sql_filtro .= " AND (v.forma_pagamento IN ('prazo', 'a_prazo', 'fiado', 'a prazo') OR EXISTS (SELECT 1 FROM contas_receber cr WHERE cr.venda_id = v.id AND cr.empresa_id = v.empresa_id)) ";
    } else {
        $sql_filtro .= " AND v.forma_pagamento = ? ";
        $paramsVendas[] = $pagamento_filtro;
    }
}

if ($periodo === 'hoje') {
    $sql_filtro .= " AND DATE(v.data_venda) = CURDATE() ";
} elseif ($periodo === '7dias') {
    $sql_filtro .= " AND v.data_venda >= DATE_SUB(NOW(), INTERVAL 7 DAY) ";
} elseif ($periodo === 'mes') {
    $sql_filtro .= " AND MONTH(v.data_venda) = MONTH(CURRENT_DATE()) AND YEAR(v.data_venda) = YEAR(CURRENT_DATE()) ";
} elseif ($periodo === 'personalizado' && !empty($data_inicio) && !empty($data_fim)) {
    $sql_filtro .= " AND DATE(v.data_venda) BETWEEN ? AND ? ";
    $paramsVendas[] = $data_inicio;
    $paramsVendas[] = $data_fim;
}

$stmtTotais = $pdo->prepare("
    SELECT COUNT(*) as qtd_vendas
    FROM vendas v
    LEFT JOIN produtos p ON v.produto_id = p.id
    WHERE v.empresa_id = ? {$sql_filtro}
");
$stmtTotais->execute($paramsVendas);
$total_vendas = (int)$stmtTotais->fetchColumn();

$por_pagina = 15;
$pagina_atual = max(1, (int)($_GET['pagina'] ?? 1));
$total_paginas = max(1, (int)ceil($total_vendas / $por_pagina));

if ($pagina_atual > $total_paginas) {
    $pagina_atual = $total_paginas;
}

$offset = ($pagina_atual - 1) * $por_pagina;

$stmtVendas = $pdo->prepare("
    SELECT 
        v.*, 
        p.nome AS produto_nome, 
        p.fornecedor AS produto_fornecedor,
        (SELECT COUNT(*) FROM contas_receber cr WHERE cr.venda_id = v.id AND cr.empresa_id = v.empresa_id) AS total_parcelas,
        (SELECT cr.cliente_nome FROM contas_receber cr WHERE cr.venda_id = v.id AND cr.empresa_id = v.empresa_id ORDER BY cr.id ASC LIMIT 1) AS cliente_nome,
        (SELECT cr.cliente_telefone FROM contas_receber cr WHERE cr.venda_id = v.id AND cr.empresa_id = v.empresa_id ORDER BY cr.id ASC LIMIT 1) AS cliente_telefone
    FROM vendas v
    LEFT JOIN produtos p ON v.produto_id = p.id
    WHERE v.empresa_id = ? {$sql_filtro}
    ORDER BY v.id DESC
    LIMIT {$por_pagina} OFFSET {$offset}
");
$stmtVendas->execute($paramsVendas);
$historico_vendas = $stmtVendas->fetchAll(PDO::FETCH_ASSOC);

function formatarPagamentoVenda($pagamento, $totalParcelas = 0) {
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

$produtoSelecionadoNome = '';
if ($venda_editar) {
    foreach ($produtos as $p) {
        if ((int)$p['id'] === (int)$venda_editar['produto_id']) {
            $produtoSelecionadoNome = $p['nome'];
            $produto_selecionado_estoque_max = (int)$p['estoque'] + (int)$venda_editar['quantidade'];
            $produto_selecionado_custo = (float)$p['preco_custo'];
            break;
        }
    }
    if (empty($produtoSelecionadoNome)) {
        $stmtPDel = $pdo->prepare("SELECT nome, estoque, preco_custo FROM produtos WHERE id = ? AND empresa_id = ? LIMIT 1");
        $stmtPDel->execute([(int)$venda_editar['produto_id'], $empresa_id]);
        $pDel = $stmtPDel->fetch(PDO::FETCH_ASSOC);
        if ($pDel) {
            $produtoSelecionadoNome = $pDel['nome'];
            $produto_selecionado_estoque_max = (int)$pDel['estoque'] + (int)$venda_editar['quantidade'];
            $produto_selecionado_custo = (float)$pDel['preco_custo'];
        }
    }
}

$temFiltroAtivo = (!empty($busca_venda) || !empty($pagamento_filtro) || ($periodo !== 'todos'));

$queryFiltros = http_build_query([
    'page' => 'vendas',
    'busca_venda' => $busca_venda,
    'pagamento_filtro' => $pagamento_filtro,
    'periodo' => $periodo,
    'data_inicio' => $data_inicio,
    'data_fim' => $data_fim
]);
?>

<script src="https://unpkg.com/lucide@latest"></script>

<style>
.venda-card.aberto .venda-seta-icon {
    transform: rotate(180deg);
}
.venda-detalhes {
    display: none;
}
.venda-card.aberto .venda-detalhes {
    display: block;
}
</style>

<!-- CABEÇALHO -->
<header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-black text-white flex items-center gap-2.5 tracking-tight m-0">
            <div class="p-2 bg-emerald-500/10 rounded-xl border border-emerald-500/20">
                <i data-lucide="shopping-bag" class="w-5 h-5 text-emerald-400"></i>
            </div>
            <?= $venda_editar ? 'Editar Venda' : 'Vendas' ?>
        </h2>
        <p class="text-sm text-zinc-400 mt-2 m-0">
            Controle operacional de saídas com cálculo automático de estoque e caixa
        </p>
    </div>
</header>

<?= $mensagem ?>

<!-- FORMULÁRIO DE LANÇAMENTO / EDIÇÃO -->
<div id="form-venda-card" class="bg-[#09090b] border <?= $venda_editar ? 'border-emerald-500/40 shadow-[0_0_30px_rgba(16,185,129,0.08)]' : 'border-zinc-800/80' ?> rounded-2xl p-4 sm:p-6 mb-8 max-w-3xl transition-all">
    <div class="flex items-center justify-between gap-2.5 mb-6">
        <div class="flex items-center gap-2.5">
            <div class="p-1.5 bg-zinc-800/60 rounded-lg text-zinc-400">
                <i data-lucide="<?= $venda_editar ? 'pencil' : 'plus' ?>" class="w-4 h-4 <?= $venda_editar ? 'text-emerald-400' : '' ?>"></i>
            </div>
            <h3 class="text-base font-bold text-white m-0">
                <?= $venda_editar ? 'Alterar Dados da Venda' : 'Lançar Nova Venda' ?>
            </h3>
        </div>

        <?php if (!$venda_editar): ?>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <?= $totalComEstoque ?> com estoque
            </span>
        <?php endif; ?>
    </div>

    <form method="POST" action="index.php?page=vendas" class="space-y-4">
        <?php if (function_exists('csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <?php endif; ?>
        <?php if ($venda_editar): ?>
            <input type="hidden" name="id_venda" value="<?= (int)$venda_editar['id'] ?>">
        <?php endif; ?>

        <!-- SELEÇÃO DE PRODUTO -->
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 m-0">Produto ou Serviço *</label>
                <span id="badge_estoque_selecionado" class="text-xs font-bold text-emerald-400 hidden">
                    Disp: <span id="txt_qtd_estoque">0</span> un.
                </span>
            </div>
            
            <input type="hidden" name="produto_id" id="produto_id" value="<?= $venda_editar ? (int)$venda_editar['produto_id'] : '' ?>" required>
            <input type="hidden" id="produto_custo_hidden" value="<?= $produto_selecionado_custo ?>">
            
            <div class="relative" id="custom-search-container">
                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-zinc-500 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    
                    <input type="text" id="input_busca_produto" 
                           placeholder="Buscar por nome ou fornecedor..." 
                           autocomplete="off"
                           value="<?= htmlspecialchars($produtoSelecionadoNome) ?>"
                           class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl pl-10 pr-10 py-2.5 outline-none focus:border-emerald-500 transition-colors placeholder:text-zinc-600">

                    <button type="button" id="btn_limpar_produto_venda" 
                            title="Limpar produto selecionado"
                            class="<?= empty($produtoSelecionadoNome) ? 'hidden' : '' ?> absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-white p-1 bg-transparent border-none cursor-pointer flex items-center justify-center">
                        <i data-lucide="x" class="w-4 h-4"></i>
                    </button>

                    <i data-lucide="chevron-down" id="chevron_icon" 
                       class="<?= !empty($produtoSelecionadoNome) ? 'hidden' : '' ?> w-4 h-4 text-zinc-500 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none transition-transform"></i>
                </div>

                <div id="lista_dropdown_produtos" class="hidden absolute left-0 right-0 top-full mt-1.5 bg-[#121215] border border-zinc-800 rounded-2xl shadow-2xl z-50 overflow-hidden">
                    <div class="p-2.5 bg-zinc-950/80 border-b border-zinc-900 flex items-center justify-between text-xs">
                        <div class="inline-flex bg-[#000000] p-0.5 rounded-lg border border-zinc-800">
                            <button type="button" id="btn_filtro_disp" onclick="setFiltroEstoqueDropdown('disp')" 
                                    class="px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-500 text-black transition cursor-pointer">
                                Disponíveis (<?= $totalComEstoque ?>)
                            </button>
                            <button type="button" id="btn_filtro_todos" onclick="setFiltroEstoqueDropdown('todos')" 
                                    class="px-2.5 py-1 rounded-md text-[11px] font-semibold text-zinc-400 hover:text-white transition cursor-pointer">
                                Todos (<?= count($produtos) ?>)
                            </button>
                        </div>
                    </div>

                    <div class="max-h-56 overflow-y-auto divide-y divide-zinc-900/60" id="container_itens_dropdown">
                        <?php if (empty($produtos)): ?>
                            <div class="p-4 text-xs text-zinc-500 text-center">Nenhum produto cadastrado.</div>
                        <?php else: ?>
                            <?php foreach ($produtos as $p): 
                                $temEstoque = (int)$p['estoque'] > 0;
                            ?>
                                <div class="item-produto-opcao p-3 transition flex items-center justify-between gap-3 text-sm <?= $temEstoque ? 'hover:bg-zinc-900/80 cursor-pointer' : 'opacity-40 cursor-not-allowed bg-zinc-950/30' ?>"
                                     data-id="<?= (int)$p['id'] ?>"
                                     data-nome="<?= htmlspecialchars($p['nome']) ?>"
                                     data-fornecedor="<?= htmlspecialchars($p['fornecedor'] ?? '') ?>"
                                     data-preco="<?= htmlspecialchars((string)$p['preco_venda']) ?>"
                                     data-custo="<?= htmlspecialchars((string)$p['preco_custo']) ?>"
                                     data-estoque="<?= (int)$p['estoque'] ?>">
                                    <div class="min-w-0">
                                        <strong class="text-white block text-xs font-semibold truncate"><?= htmlspecialchars($p['nome']) ?></strong>
                                        <?php if (!empty($p['fornecedor'])): ?>
                                            <span class="text-[11px] text-zinc-500 block truncate">Forn: <?= htmlspecialchars($p['fornecedor']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="text-emerald-400 font-bold block text-xs">R$ <?= number_format((float)$p['preco_venda'], 2, ',', '.') ?></span>
                                        <?php if ($temEstoque): ?>
                                            <span class="text-[10px] font-bold text-zinc-400 block">Estoque: <?= (int)$p['estoque'] ?> un.</span>
                                        <?php else: ?>
                                            <span class="text-[10px] font-bold text-rose-400/80 block">Esgotado (0 un.)</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- CANAL E FORMA DE PAGAMENTO -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Canal de Venda</label>
                <div class="relative">
                    <select name="canal"
                            class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors cursor-pointer appearance-none">
                        <option value="WhatsApp" <?= ($venda_editar && $venda_editar['canal'] === 'WhatsApp') ? 'selected' : '' ?>>WhatsApp</option>
                        <option value="Instagram" <?= ($venda_editar && $venda_editar['canal'] === 'Instagram') ? 'selected' : '' ?>>Instagram</option>
                        <option value="Loja Física" <?= ($venda_editar && $venda_editar['canal'] === 'Loja Física') ? 'selected' : '' ?>>Loja Física</option>
                        <option value="Site" <?= ($venda_editar && $venda_editar['canal'] === 'Site') ? 'selected' : '' ?>>Site / E-commerce</option>
                    </select>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Forma de Pagamento</label>
                <div class="relative">
                    <select name="forma_pagamento" id="forma_pagamento" onchange="toggleCamposPrazo()"
                            class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors cursor-pointer appearance-none">
                        <option value="pix" <?= ($venda_editar && $venda_editar['forma_pagamento'] === 'pix' && $total_parcelas_existentes === 0) ? 'selected' : '' ?>>Pix</option>
                        <option value="cartao_credito" <?= ($venda_editar && $venda_editar['forma_pagamento'] === 'cartao_credito') ? 'selected' : '' ?>>Cartão de Crédito</option>
                        <option value="cartao_debito" <?= ($venda_editar && $venda_editar['forma_pagamento'] === 'cartao_debito') ? 'selected' : '' ?>>Cartão de Débito</option>
                        <option value="dinheiro" <?= ($venda_editar && $venda_editar['forma_pagamento'] === 'dinheiro') ? 'selected' : '' ?>>Dinheiro</option>
                        <option value="prazo" <?= ($venda_editar && ($total_parcelas_existentes > 0 || in_array(strtolower($venda_editar['forma_pagamento']), ['prazo', 'a_prazo', 'fiado', 'a prazo']))) ? 'selected' : '' ?>>A Prazo / Fiado</option>
                    </select>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                </div>
            </div>
        </div>

        <!-- SEÇÃO DE PARCELAMENTO -->
        <div id="campos_prazo_container" style="display: none;" class="bg-amber-500/5 border border-amber-500/20 rounded-2xl p-4 sm:p-5 space-y-4">
            <div class="flex items-center gap-2">
                <div class="p-1 bg-amber-500/10 rounded-lg">
                    <i data-lucide="wallet" class="w-4 h-4 text-amber-500"></i>
                </div>
                <span class="text-xs font-bold uppercase tracking-wider text-amber-400">Configuração de Venda a Prazo</span>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Nome do Cliente *</label>
                    <input type="text" name="cliente_nome" id="cliente_nome" placeholder="Ex: Maria Souza" 
                           value="<?= htmlspecialchars(preg_replace('/\s*\(\d+\/\d+\)$/', '', $conta_vinculada['cliente_nome'] ?? '')) ?>"
                           class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-amber-500 transition-colors">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">WhatsApp / Telefone</label>
                    <input type="text" name="cliente_telefone" id="cliente_telefone" placeholder="Ex: 75999999999" 
                           value="<?= htmlspecialchars($conta_vinculada['cliente_telefone'] ?? '') ?>"
                           class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-amber-500 transition-colors">
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Parcelas *</label>
                    <div class="relative">
                        <select name="num_parcelas" id="num_parcelas"
                                class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-amber-500 transition-colors cursor-pointer appearance-none">
                            <?php for ($p = 1; $p <= 12; $p++): ?>
                                <option value="<?= $p ?>" <?= ($total_parcelas_existentes === $p) ? 'selected' : '' ?>><?= $p ?>x</option>
                            <?php endfor; ?>
                        </select>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Data 1º Vencimento *</label>
                    <input type="date" name="data_vencimento" id="data_vencimento" 
                           value="<?= htmlspecialchars($conta_vinculada['data_vencimento'] ?? date('Y-m-d', strtotime('+30 days'))) ?>"
                           class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-amber-500 transition-colors">
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Entrada Paga (R$)</label>
                    <input type="text" name="valor_entrada" id="valor_entrada" placeholder="0,00" 
                           value="<?= $venda_editar ? number_format($valor_entrada_existente, 2, ',', '.') : '0,00' ?>"
                           class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-amber-500 transition-colors">
                </div>
            </div>
        </div>

        <!-- QUANTIDADE, PREÇO E TAXAS -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Quantidade *</label>
                <input type="number" name="quantidade" id="venda_qtd" min="1" max="<?= $produto_selecionado_estoque_max ?>" required
                       value="<?= $venda_editar ? (int)$venda_editar['quantidade'] : '1' ?>"
                       class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Preço Un. (R$) *</label>
                <input type="number" step="0.01" min="0" name="preco_venda" id="venda_preco" required placeholder="0.00"
                       value="<?= $venda_editar ? number_format((float)$venda_editar['preco_venda'], 2, '.', '') : '' ?>"
                       class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors">
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Taxas/Frete (R$)</label>
                <input type="number" step="0.01" min="0" name="taxas_e_frete" id="venda_taxas" placeholder="0.00"
                       value="<?= $venda_editar ? number_format((float)$venda_editar['taxas_e_frete'], 2, '.', '') : '0.00' ?>"
                       class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors">
            </div>
        </div>

        <!-- PREVIEW DE SUBTOTAL DINÂMICO -->
        <div id="preview_calculo_venda" class="bg-[#000000] border border-zinc-800/80 rounded-xl p-3.5 flex items-center justify-between text-xs">
            <div class="text-zinc-400">
                <span>Total:</span>
                <strong class="text-white text-sm ml-1 font-bold" id="preview_total_venda">R$ 0,00</strong>
            </div>
            <div class="text-zinc-400">
                <span>Lucro Real:</span>
                <strong class="text-emerald-400 text-sm ml-1 font-bold" id="preview_lucro_estimado">R$ 0,00</strong>
            </div>
        </div>

        <!-- BOTÕES DE AÇÃO -->
        <div class="flex items-center gap-3 pt-2">
            <button type="submit" name="salvar_venda"
                    class="flex-1 inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-black text-sm font-bold rounded-xl px-6 py-3 transition-all shadow-[0_0_20px_rgba(16,185,129,0.15)] cursor-pointer">
                <i data-lucide="check" class="w-4 h-4"></i>
                <?= $venda_editar ? 'Atualizar Venda' : 'Confirmar Venda' ?>
            </button>

            <?php if ($venda_editar): ?>
                <a href="index.php?page=vendas" 
                   class="inline-flex items-center justify-center bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 text-sm font-semibold rounded-xl px-5 py-3 transition-colors no-underline">
                    Cancelar
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- HISTÓRICO DE VENDAS -->
<div class="bg-[#09090b] border border-zinc-800/80 rounded-2xl p-4 sm:p-6">
    
    <!-- HEADER: TÍTULO, CONTADOR, BUSCA E FILTRO -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-5 border-b border-zinc-900">
        <div class="flex items-center gap-2.5">
            <div class="p-1.5 bg-emerald-500/10 rounded-xl border border-emerald-500/20">
                <i data-lucide="history" class="w-4.5 h-4.5 text-emerald-400"></i>
            </div>
            <h3 class="text-base font-bold text-white m-0">Histórico de Vendas</h3>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-zinc-800 text-zinc-400 border border-zinc-700/50">
                <?= $total_vendas ?> <?= $total_vendas === 1 ? 'venda' : 'vendas' ?>
            </span>
        </div>

        <div class="flex items-center gap-2.5 w-full sm:w-auto">
            <form method="GET" action="index.php" class="m-0 relative flex-1 sm:flex-initial">
                <input type="hidden" name="page" value="vendas">
                <input type="hidden" name="pagamento_filtro" value="<?= htmlspecialchars($pagamento_filtro) ?>">
                <input type="hidden" name="periodo" value="<?= htmlspecialchars($periodo) ?>">
                <input type="hidden" name="data_inicio" value="<?= htmlspecialchars($data_inicio) ?>">
                <input type="hidden" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>">

                <div class="relative">
                    <i data-lucide="search" class="w-4 h-4 text-zinc-500 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                    <input type="text" name="busca_venda" placeholder="Buscar venda..." 
                           value="<?= htmlspecialchars($busca_venda) ?>"
                           class="pl-9 pr-8 py-2 bg-[#000000] border border-zinc-800 text-zinc-200 text-xs rounded-xl outline-none focus:border-emerald-500 transition-colors placeholder:text-zinc-600 w-full sm:w-56">
                    
                    <?php if (!empty($busca_venda)): ?>
                        <a href="index.php?<?= http_build_query(['page' => 'vendas', 'pagamento_filtro' => $pagamento_filtro, 'periodo' => $periodo, 'data_inicio' => $data_inicio, 'data_fim' => $data_fim]) ?>" 
                           class="absolute right-2.5 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-300">
                            <i data-lucide="x" class="w-3.5 h-3.5"></i>
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <div class="relative" id="container_popover_filtro">
                <button type="button" id="btn_toggle_filtro" 
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-semibold transition cursor-pointer <?= $temFiltroAtivo ? 'bg-emerald-500 text-black shadow-lg shadow-emerald-500/20' : 'bg-zinc-900 border border-zinc-800 text-zinc-300 hover:text-white hover:bg-zinc-800' ?>">
                    <i data-lucide="sliders-horizontal" class="w-3.5 h-3.5"></i>
                    <span>Filtrar</span>
                    <?php if ($temFiltroAtivo): ?>
                        <span class="w-2 h-2 rounded-full bg-black"></span>
                    <?php endif; ?>
                </button>

                <div id="popover_filtro" class="hidden absolute right-0 top-full mt-2 w-72 bg-[#121215] border border-zinc-800 rounded-2xl p-4 shadow-2xl z-50 space-y-4">
                    <form method="GET" action="index.php" class="m-0 space-y-3.5">
                        <input type="hidden" name="page" value="vendas">
                        <input type="hidden" name="busca_venda" value="<?= htmlspecialchars($busca_venda) ?>">

                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">Forma de Pagamento</label>
                            <select name="pagamento_filtro" class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-xs rounded-xl px-3 py-2 outline-none focus:border-emerald-500">
                                <option value="">Todas as Formas</option>
                                <option value="pix" <?= $pagamento_filtro === 'pix' ? 'selected' : '' ?>>Pix</option>
                                <option value="cartao_credito" <?= $pagamento_filtro === 'cartao_credito' ? 'selected' : '' ?>>Cartão de Crédito</option>
                                <option value="cartao_debito" <?= $pagamento_filtro === 'cartao_debito' ? 'selected' : '' ?>>Cartão de Débito</option>
                                <option value="dinheiro" <?= $pagamento_filtro === 'dinheiro' ? 'selected' : '' ?>>Dinheiro</option>
                                <option value="prazo" <?= $pagamento_filtro === 'prazo' ? 'selected' : '' ?>>A Prazo / Fiado</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-[11px] font-semibold uppercase tracking-wider text-zinc-400 mb-1.5">Período</label>
                            <select name="periodo" id="filtro_periodo_select" onchange="toggleCustomDates(this.value)"
                                    class="w-full bg-[#000000] border border-zinc-800 text-zinc-200 text-xs rounded-xl px-3 py-2 outline-none focus:border-emerald-500">
                                <option value="todos" <?= $periodo === 'todos' ? 'selected' : '' ?>>Todo o Período</option>
                                <option value="hoje" <?= $periodo === 'hoje' ? 'selected' : '' ?>>Hoje</option>
                                <option value="7dias" <?= $periodo === '7dias' ? 'selected' : '' ?>>Últimos 7 dias</option>
                                <option value="mes" <?= $periodo === 'mes' ? 'selected' : '' ?>>Este Mês</option>
                                <option value="personalizado" <?= $periodo === 'personalizado' ? 'selected' : '' ?>>Personalizado</option>
                            </select>
                        </div>

                        <div id="container_datas_personalizadas" class="<?= $periodo === 'personalizado' ? '' : 'hidden' ?> space-y-2 pt-1">
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
                                <a href="index.php?page=vendas" class="px-3 py-2 bg-zinc-900 hover:bg-zinc-800 text-rose-400 text-xs font-semibold rounded-xl text-center no-underline">
                                    Limpar
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- LISTA DE CARDS INTERATIVOS (LAYOUT SEM SOBREPOSIÇÃO) -->
    <?php if (count($historico_vendas) > 0): ?>
        <div class="space-y-3">
            <?php foreach ($historico_vendas as $v): 
                $totalV = (float)$v['valor_total'];
                $lucroV = (float)$v['lucro_liquido'];
                $margemV = $totalV > 0 ? (($lucroV / $totalV) * 100) : 0;
                $totalParcelas = (int)($v['total_parcelas'] ?? 0);
                $isPrazo = ($totalParcelas > 0 || in_array(strtolower(trim((string)$v['forma_pagamento'])), ['prazo', 'a_prazo', 'fiado', 'a prazo']));
            ?>
                <div class="venda-card bg-[#000000] border border-zinc-800/80 rounded-2xl overflow-hidden transition hover:border-zinc-700" id="venda-card-<?= (int)$v['id'] ?>">
                    
                    <!-- LINHA RESUMO PRINCIPAL -->
                    <button type="button" class="w-full flex items-start sm:items-center justify-between gap-3 p-3.5 sm:p-4 bg-transparent border-none text-left cursor-pointer transition hover:bg-zinc-900/40" onclick="toggleVendaCard(<?= (int)$v['id'] ?>)">
                        
                        <!-- LADO ESQUERDO -->
                        <div class="flex items-start sm:items-center gap-3 min-w-0 flex-1">
                            <div class="p-2 bg-zinc-900 border border-zinc-800 rounded-xl text-zinc-400 shrink-0 mt-0.5 sm:mt-0">
                                <i data-lucide="shopping-bag" class="w-4 h-4 text-emerald-400"></i>
                            </div>
                            
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <strong class="text-white text-sm font-semibold truncate block"><?= htmlspecialchars($v['produto_nome'] ?? 'Produto') ?></strong>
                                    <span class="text-xs text-zinc-500 font-medium shrink-0"><?= (int)$v['quantidade'] ?> un.</span>
                                </div>
                                
                                <div class="flex flex-wrap items-center gap-1.5 mt-1 text-xs text-zinc-500">
                                    <span><?= !empty($v['data_venda']) ? date('d/m/Y', strtotime($v['data_venda'])) : '—' ?></span>
                                    <span>•</span>
                                    
                                    <?php if ($isPrazo): ?>
                                        <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-400">
                                            <i data-lucide="wallet" class="w-3 h-3"></i>
                                            <?= formatarPagamentoVenda($v['forma_pagamento'], $totalParcelas) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[11px] font-medium text-zinc-400">
                                            <?= htmlspecialchars(formatarPagamentoVenda($v['forma_pagamento'], $totalParcelas)) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- LADO DIREITO (VALORES EM COLUNA SEM SOBREPOSIÇÃO) -->
                        <div class="flex items-center gap-3 shrink-0 ml-2">
                            <div class="text-right">
                                <span class="text-[10px] text-zinc-500 uppercase tracking-wider block">Total</span>
                                <strong class="text-sm font-bold text-white block whitespace-nowrap">
                                    R$ <?= number_format($totalV, 2, ',', '.') ?>
                                </strong>
                                <span class="text-xs font-semibold <?= $lucroV > 0 ? 'text-emerald-400' : 'text-zinc-500' ?> block whitespace-nowrap mt-0.5">
                                    +R$ <?= number_format($lucroV, 2, ',', '.') ?>
                                </span>
                            </div>

                            <div class="venda-seta-icon w-7 h-7 rounded-lg bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-400 transition-transform duration-200">
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                            </div>
                        </div>
                    </button>

                    <!-- DETALHES EXPANSÍVEIS -->
                    <div class="venda-detalhes border-t border-zinc-900 p-4 bg-zinc-950/60">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 mb-3.5 text-xs">
                            <div class="bg-[#09090b] p-2.5 rounded-xl border border-zinc-900">
                                <span class="text-zinc-500 block mb-0.5">Canal</span>
                                <strong class="text-zinc-300 font-medium"><?= !empty($v['canal']) ? htmlspecialchars($v['canal']) : '—' ?></strong>
                            </div>

                            <div class="bg-[#09090b] p-2.5 rounded-xl border border-zinc-900">
                                <span class="text-zinc-500 block mb-0.5">Margem</span>
                                <strong class="font-bold <?= $margemV > 0 ? 'text-emerald-400' : 'text-zinc-400' ?>">
                                    <?= number_format($margemV, 1, ',', '.') ?>%
                                </strong>
                            </div>

                            <div class="bg-[#09090b] p-2.5 rounded-xl border border-zinc-900">
                                <span class="text-zinc-500 block mb-0.5">Custo Mercadoria</span>
                                <strong class="text-zinc-300 font-medium">R$ <?= number_format((float)$v['custo_produto'], 2, ',', '.') ?></strong>
                            </div>

                            <div class="bg-[#09090b] p-2.5 rounded-xl border border-zinc-900">
                                <span class="text-zinc-500 block mb-0.5">Taxas / Frete</span>
                                <strong class="text-zinc-300 font-medium">R$ <?= number_format((float)$v['taxas_e_frete'], 2, ',', '.') ?></strong>
                            </div>
                        </div>

                        <?php if ($isPrazo && !empty($v['cliente_nome'])): ?>
                            <div class="bg-amber-500/5 border border-amber-500/20 rounded-xl p-3 mb-3.5 flex items-center justify-between text-xs">
                                <div class="flex items-center gap-2">
                                    <i data-lucide="user" class="w-4 h-4 text-amber-400"></i>
                                    <span class="text-zinc-300">Cliente: <strong class="text-white"><?= htmlspecialchars($v['cliente_nome']) ?></strong></span>
                                </div>
                                <a href="index.php?page=a-receber&busca=<?= urlencode($v['cliente_nome']) ?>" class="text-amber-400 hover:text-amber-300 font-semibold no-underline flex items-center gap-1">
                                    <span>Ver parcelas</span>
                                    <i data-lucide="arrow-right" class="w-3.5 h-3.5"></i>
                                </a>
                            </div>
                        <?php endif; ?>

                        <!-- AÇÕES -->
                        <div class="flex items-center justify-end gap-2 pt-2 border-t border-zinc-900">
                            <a href="index.php?page=vendas&acao=editar&id=<?= (int)$v['id'] ?>#form-venda-card" 
                               class="inline-flex items-center gap-1.5 text-xs px-3.5 py-2 rounded-xl bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-200 font-semibold transition no-underline">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                <span>Editar</span>
                            </a>

                            <button type="button" 
                                    onclick="abrirModalCancelamento(<?= (int)$v['id'] ?>)"
                                    class="inline-flex items-center gap-1.5 text-xs px-3.5 py-2 rounded-xl bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 font-semibold transition cursor-pointer">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                <span>Excluir</span>
                            </button>

                            <form method="POST" action="index.php?page=vendas" id="form-cancelar-<?= (int)$v['id'] ?>" style="display:none;">
                                <?php if (function_exists('csrf_token')): ?>
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <?php endif; ?>
                                <input type="hidden" name="id_venda" value="<?= (int)$v['id'] ?>">
                                <input type="hidden" name="cancelar_venda" value="1">
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-zinc-500 text-center py-10 text-sm">
            Nenhuma venda encontrada para os filtros selecionados.
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
<div id="modal-cancelamento" class="hidden fixed inset-0 bg-black/75 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
    <div class="w-full max-w-md bg-[#121215] border border-zinc-800 rounded-2xl p-6 text-center shadow-2xl">
        <div class="w-12 h-12 bg-rose-500/10 text-rose-500 border border-rose-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <i data-lucide="alert-triangle" class="w-6 h-6"></i>
        </div>
        
        <h3 class="text-base font-bold text-white mb-2">Cancelar venda?</h3>
        <p class="text-xs text-zinc-400 mb-4">Tem certeza que deseja cancelar esta venda?</p>
        <p class="text-xs text-amber-400/90 bg-amber-500/10 border border-amber-500/20 p-3 rounded-xl mb-6">
            A quantidade retornará ao estoque e as parcelas financeiras vinculadas serão removidas.
        </p>

        <div class="flex items-center gap-3">
            <button type="button" 
                    class="flex-1 bg-zinc-800 hover:bg-zinc-700 text-zinc-200 text-xs font-semibold py-2.5 rounded-xl transition cursor-pointer" 
                    onclick="fecharModalCancelamento()">
                Não, voltar
            </button>
            <button type="button" 
                    class="flex-1 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold py-2.5 rounded-xl transition cursor-pointer" 
                    onclick="confirmarCancelamento()">
                Sim, cancelar
            </button>
        </div>
    </div>
</div>

<script>
let vendaParaCancelar = null;
let filtroEstoqueAtual = 'disp';

function toggleVendaCard(id) {
    const card = document.getElementById('venda-card-' + id);
    if (card) {
        card.classList.toggle('aberto');
    }
}

function abrirModalCancelamento(id) {
    vendaParaCancelar = id;
    const modal = document.getElementById('modal-cancelamento');
    if (modal) {
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function fecharModalCancelamento() {
    vendaParaCancelar = null;
    const modal = document.getElementById('modal-cancelamento');
    if (modal) {
        modal.classList.remove('flex');
        modal.classList.add('hidden');
    }
}

function confirmarCancelamento() {
    if (!vendaParaCancelar) return;
    const form = document.getElementById('form-cancelar-' + vendaParaCancelar);
    if (form) form.submit();
}

const modalCancelamento = document.getElementById('modal-cancelamento');
if (modalCancelamento) {
    modalCancelamento.addEventListener('click', function(event) {
        if (event.target === this) fecharModalCancelamento();
    });
}

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape' && vendaParaCancelar) fecharModalCancelamento();
});

function toggleCamposPrazo() {
    const selectPagto = document.getElementById('forma_pagamento');
    const container = document.getElementById('campos_prazo_container');
    const clienteInput = document.getElementById('cliente_nome');
    const vencimentoInput = document.getElementById('data_vencimento');

    if (selectPagto && container) {
        const val = selectPagto.value.toLowerCase().trim();
        if (val === 'prazo' || val === 'a_prazo' || val === 'fiado' || val === 'a prazo') {
            container.style.display = 'block';
            if (clienteInput) clienteInput.required = true;
            if (vencimentoInput) vencimentoInput.required = true;
        } else {
            container.style.display = 'none';
            if (clienteInput) clienteInput.required = false;
            if (vencimentoInput) vencimentoInput.required = false;
        }
    }
}

function toggleCustomDates(val) {
    const container = document.getElementById('container_datas_personalizadas');
    if (container) {
        if (val === 'personalizado') {
            container.classList.remove('hidden');
        } else {
            container.classList.add('hidden');
        }
    }
}

function setFiltroEstoqueDropdown(tipo) {
    filtroEstoqueAtual = tipo;
    const btnDisp = document.getElementById('btn_filtro_disp');
    const btnTodos = document.getElementById('btn_filtro_todos');

    if (tipo === 'disp') {
        btnDisp.className = 'px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-500 text-black transition cursor-pointer';
        btnTodos.className = 'px-2.5 py-1 rounded-md text-[11px] font-semibold text-zinc-400 hover:text-white transition cursor-pointer';
    } else {
        btnTodos.className = 'px-2.5 py-1 rounded-md text-[11px] font-bold bg-emerald-500 text-black transition cursor-pointer';
        btnDisp.className = 'px-2.5 py-1 rounded-md text-[11px] font-semibold text-zinc-400 hover:text-white transition cursor-pointer';
    }

    filtrarItensProdutos();
}

function filtrarItensProdutos() {
    const inputBusca = document.getElementById('input_busca_produto');
    const termo = inputBusca ? inputBusca.value.toLowerCase().trim() : '';
    const itens = document.querySelectorAll('.item-produto-opcao');

    itens.forEach(item => {
        const nome = item.getAttribute('data-nome').toLowerCase();
        const forn = item.getAttribute('data-fornecedor').toLowerCase();
        const estoque = parseInt(item.getAttribute('data-estoque')) || 0;

        const bateBusca = (nome.includes(termo) || forn.includes(termo));
        const bateEstoque = (filtroEstoqueAtual === 'todos' || estoque > 0);

        if (bateBusca && bateEstoque) {
            item.style.display = 'flex';
        } else {
            item.style.display = 'none';
        }
    });
}

function atualizarPreviewCalculo() {
    const campoQtd = document.getElementById('venda_qtd');
    const campoPreco = document.getElementById('venda_preco');
    const campoTaxas = document.getElementById('venda_taxas');
    const custoHidden = document.getElementById('produto_custo_hidden');

    const qtd = parseInt(campoQtd ? campoQtd.value : 1) || 0;
    const preco = parseFloat(campoPreco ? campoPreco.value : 0) || 0;
    const taxas = parseFloat(campoTaxas ? campoTaxas.value : 0) || 0;
    const custoUn = parseFloat(custoHidden ? custoHidden.value : 0) || 0;

    const total = (qtd * preco);
    const lucro = total - (qtd * custoUn) - taxas;

    const txtTotal = document.getElementById('preview_total_venda');
    const txtLucro = document.getElementById('preview_lucro_estimado');

    if (txtTotal) txtTotal.textContent = 'R$ ' + total.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    if (txtLucro) txtLucro.textContent = 'R$ ' + lucro.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

document.addEventListener('DOMContentLoaded', function() {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
    
    toggleCamposPrazo();
    filtrarItensProdutos();
    atualizarPreviewCalculo();

    ['venda_qtd', 'venda_preco', 'venda_taxas'].forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', atualizarPreviewCalculo);
        }
    });

    const btnToggleFiltro = document.getElementById('btn_toggle_filtro');
    const popoverFiltro = document.getElementById('popover_filtro');
    const containerPopover = document.getElementById('container_popover_filtro');

    if (btnToggleFiltro && popoverFiltro) {
        btnToggleFiltro.addEventListener('click', (e) => {
            e.stopPropagation();
            popoverFiltro.classList.toggle('hidden');
        });

        document.addEventListener('click', (e) => {
            if (containerPopover && !containerPopover.contains(e.target)) {
                popoverFiltro.classList.add('hidden');
            }
        });
    }

    const inputBusca = document.getElementById('input_busca_produto');
    const dropdown = document.getElementById('lista_dropdown_produtos');
    const hiddenId = document.getElementById('produto_id');
    const hiddenCusto = document.getElementById('produto_custo_hidden');
    const campoPreco = document.getElementById('venda_preco');
    const campoQtd = document.getElementById('venda_qtd');
    const chevron = document.getElementById('chevron_icon');
    const btnLimpar = document.getElementById('btn_limpar_produto_venda');
    const containerSearch = document.getElementById('custom-search-container');
    const badgeEstoque = document.getElementById('badge_estoque_selecionado');
    const txtQtdEstoque = document.getElementById('txt_qtd_estoque');
    const itens = document.querySelectorAll('.item-produto-opcao');

    if (!inputBusca || !dropdown) return;

    function atualizarEstadoIcones(temProduto) {
        if (temProduto) {
            if (btnLimpar) btnLimpar.classList.remove('hidden');
            if (chevron) chevron.classList.add('hidden');
        } else {
            if (btnLimpar) btnLimpar.classList.add('hidden');
            if (chevron) chevron.classList.remove('hidden');
            if (badgeEstoque) badgeEstoque.classList.add('hidden');
        }
    }

    if (btnLimpar) {
        btnLimpar.addEventListener('click', (e) => {
            e.stopPropagation();
            hiddenId.value = '';
            inputBusca.value = '';
            if (hiddenCusto) hiddenCusto.value = '0';
            
            atualizarEstadoIcones(false);

            if (campoPreco) campoPreco.value = '';
            if (campoQtd) campoQtd.removeAttribute('max');
            
            inputBusca.focus();
            dropdown.classList.remove('hidden');
            filtrarItensProdutos();
            atualizarPreviewCalculo();
        });
    }

    inputBusca.addEventListener('focus', () => {
        dropdown.classList.remove('hidden');
        filtrarItensProdutos();
    });

    inputBusca.addEventListener('input', () => {
        dropdown.classList.remove('hidden');
        atualizarEstadoIcones(inputBusca.value.trim().length > 0);
        filtrarItensProdutos();
    });

    itens.forEach(item => {
        item.addEventListener('click', () => {
            const estoque = parseInt(item.getAttribute('data-estoque')) || 0;
            
            if (estoque <= 0) {
                return;
            }

            const id = item.getAttribute('data-id');
            const nome = item.getAttribute('data-nome');
            const preco = item.getAttribute('data-preco');
            const custo = item.getAttribute('data-custo');

            hiddenId.value = id;
            if (hiddenCusto) hiddenCusto.value = custo;
            inputBusca.value = nome;

            if (badgeEstoque && txtQtdEstoque) {
                txtQtdEstoque.textContent = estoque;
                badgeEstoque.classList.remove('hidden');
            }

            if (campoQtd) {
                campoQtd.max = estoque;
                if (parseInt(campoQtd.value) > estoque) {
                    campoQtd.value = estoque;
                }
            }

            atualizarEstadoIcones(true);

            if (campoPreco && (!campoPreco.value || campoPreco.value === '0.00' || campoPreco.value === '0')) {
                campoPreco.value = parseFloat(preco).toFixed(2);
            }

            dropdown.classList.add('hidden');
            atualizarPreviewCalculo();
        });
    });

    document.addEventListener('click', (e) => {
        if (!containerSearch.contains(e.target)) {
            dropdown.classList.add('hidden');
        }
    });
});
</script>