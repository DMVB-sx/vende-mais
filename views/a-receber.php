<?php

$mensagem = '';
$empresa_id = $_SESSION['empresa_id'] ?? 0;

/*
|--------------------------------------------------------------------------
| FUNÇÃO AUXILIAR: RECALCULAR LUCRO DA VENDA
|--------------------------------------------------------------------------
*/
function recalcularLucroVenda($pdo, $venda_id, $empresa_id) {
    if ($venda_id <= 0) return;

    $stmtV = $pdo->prepare("SELECT valor_total, custo_produto, taxas_e_frete FROM vendas WHERE id = ? AND empresa_id = ? LIMIT 1");
    $stmtV->execute([$venda_id, $empresa_id]);
    $venda = $stmtV->fetch(PDO::FETCH_ASSOC);

    if ($venda) {
        $stmtSomaPaga = $pdo->prepare("SELECT COALESCE(SUM(valor_pago), 0) as total_recebido FROM contas_receber WHERE venda_id = ? AND empresa_id = ?");
        $stmtSomaPaga->execute([$venda_id, $empresa_id]);
        $totalRecebido = (float)$stmtSomaPaga->fetchColumn();

        $totalVenda = (float)$venda['valor_total'];
        $potencialLucro = $totalVenda - (float)$venda['custo_produto'] - (float)$venda['taxas_e_frete'];
        $proporcao = $totalVenda > 0 ? ($totalRecebido / $totalVenda) : 0;
        $lucroRecalculado = $potencialLucro * min(1, max(0, $proporcao));

        $stmtUpV = $pdo->prepare("UPDATE vendas SET lucro_liquido = ? WHERE id = ? AND empresa_id = ?");
        $stmtUpV->execute([$lucroRecalculado, $venda_id, $empresa_id]);
    }
}

/*
|--------------------------------------------------------------------------
| 1. DAR BAIXA EM PARCELA INDIVIDUAL
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dar_baixa_parcela'])) {
    if (function_exists('validar_csrf')) {
        validar_csrf();
    }

    $conta_id = (int)($_POST['conta_id'] ?? 0);
    $valor_pago_agora = (float)str_replace(['.', ','], ['', '.'], $_POST['valor_pago'] ?? '0');

    if ($conta_id > 0 && $valor_pago_agora > 0) {
        try {
            $stmtC = $pdo->prepare("SELECT * FROM contas_receber WHERE id = ? AND empresa_id = ? LIMIT 1");
            $stmtC->execute([$conta_id, $empresa_id]);
            $conta = $stmtC->fetch(PDO::FETCH_ASSOC);

            if ($conta) {
                $novo_pago = (float)$conta['valor_pago'] + $valor_pago_agora;
                $total_conta = (float)$conta['valor_total'];
                $novo_status = ($novo_pago >= $total_conta) ? 'pago' : 'parcial';

                $pdo->beginTransaction();

                $stmtUp = $pdo->prepare("
                    UPDATE contas_receber 
                    SET valor_pago = ?, status = ? 
                    WHERE id = ? AND empresa_id = ?
                ");
                $stmtUp->execute([min($novo_pago, $total_conta), $novo_status, $conta_id, $empresa_id]);

                recalcularLucroVenda($pdo, (int)$conta['venda_id'], $empresa_id);

                $pdo->commit();

                $mensagem = '
                    <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                        <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                        <div>
                            <strong class="font-semibold block text-emerald-300">Baixa registrada com sucesso!</strong>
                            <span class="text-xs text-emerald-400/80">Recebimento lançado no caixa.</span>
                        </div>
                    </div>
                ';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($e->getMessage());
            $mensagem = '
                <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                    <div><strong class="font-semibold block text-rose-300">Erro ao processar baixa.</strong></div>
                </div>
            ';
        }
    }
}

/*
|--------------------------------------------------------------------------
| 2. QUITAR TODA A DÍVIDA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quitar_tudo_venda'])) {
    if (function_exists('validar_csrf')) {
        validar_csrf();
    }

    $venda_id = (int)($_POST['venda_id'] ?? 0);

    if ($venda_id > 0) {
        try {
            $pdo->beginTransaction();

            $stmtQuitar = $pdo->prepare("
                UPDATE contas_receber 
                SET valor_pago = valor_total, status = 'pago' 
                WHERE venda_id = ? AND empresa_id = ?
            ");
            $stmtQuitar->execute([$venda_id, $empresa_id]);

            recalcularLucroVenda($pdo, $venda_id, $empresa_id);

            $pdo->commit();

            $mensagem = '
                <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="check-check" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                    <div>
                        <strong class="font-semibold block text-emerald-300">Dívida 100% quitada com sucesso!</strong>
                        <span class="text-xs text-emerald-400/80">Todas as parcelas foram liquidadas e o lucro total foi lançado no caixa.</span>
                    </div>
                </div>
            ';
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($e->getMessage());
            $mensagem = '
                <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                    <div><strong class="font-semibold block text-rose-300">Erro ao quitar dívida.</strong></div>
                </div>
            ';
        }
    }
}

/*
|--------------------------------------------------------------------------
| 3. EDITAR / ESTORNAR PARCELA
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_edicao_parcela'])) {
    if (function_exists('validar_csrf')) {
        validar_csrf();
    }

    $conta_id = (int)($_POST['conta_id'] ?? 0);
    $valor_pago_novo = (float)str_replace(['.', ','], ['', '.'], $_POST['valor_pago'] ?? '0');
    $data_vencimento_nova = trim($_POST['data_vencimento'] ?? '');

    if ($conta_id > 0) {
        try {
            $stmtC = $pdo->prepare("SELECT * FROM contas_receber WHERE id = ? AND empresa_id = ? LIMIT 1");
            $stmtC->execute([$conta_id, $empresa_id]);
            $conta = $stmtC->fetch(PDO::FETCH_ASSOC);

            if ($conta) {
                $total_conta = (float)$conta['valor_total'];
                $valor_pago_final = min($valor_pago_novo, $total_conta);
                
                $novo_status = 'pendente';
                if ($valor_pago_final >= $total_conta) {
                    $novo_status = 'pago';
                } elseif ($valor_pago_final > 0) {
                    $novo_status = 'parcial';
                }

                $pdo->beginTransaction();

                $stmtUp = $pdo->prepare("
                    UPDATE contas_receber 
                    SET valor_pago = ?, data_vencimento = ?, status = ? 
                    WHERE id = ? AND empresa_id = ?
                ");
                $stmtUp->execute([$valor_pago_final, $data_vencimento_nova ?: $conta['data_vencimento'], $novo_status, $conta_id, $empresa_id]);

                recalcularLucroVenda($pdo, (int)$conta['venda_id'], $empresa_id);

                $pdo->commit();

                $mensagem = '
                    <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                        <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                        <div>
                            <strong class="font-semibold block text-emerald-300">Parcela atualizada com sucesso!</strong>
                            <span class="text-xs text-emerald-400/80">Status e valores recalculados no sistema.</span>
                        </div>
                    </div>
                ';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            error_log($e->getMessage());
            $mensagem = '
                <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                    <div><strong class="font-semibold block text-rose-300">Erro ao atualizar parcela.</strong></div>
                </div>
            ';
        }
    }
}

/*
|--------------------------------------------------------------------------
| 4. CONSULTA & FILTROS POR ABA
|--------------------------------------------------------------------------
*/

$busca = trim($_GET['busca'] ?? '');
$aba_filtro = trim($_GET['aba'] ?? 'pendentes');

$params = [$empresa_id];
$sql_busca = '';

if (!empty($busca)) {
    $sql_busca .= " AND (cr.cliente_nome LIKE ? OR cr.cliente_telefone LIKE ? OR p.nome LIKE ?) ";
    $term = "%{$busca}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$stmt = $pdo->prepare("
    SELECT 
        cr.*,
        v.data_venda,
        v.valor_total AS venda_valor_total,
        p.nome AS produto_nome
    FROM contas_receber cr
    LEFT JOIN vendas v ON cr.venda_id = v.id
    LEFT JOIN produtos p ON v.produto_id = p.id
    WHERE cr.empresa_id = ? {$sql_busca}
    ORDER BY cr.venda_id DESC, cr.data_vencimento ASC, cr.id ASC
");
$stmt->execute($params);
$todasParcelas = $stmt->fetchAll(PDO::FETCH_ASSOC);

$gruposDividas = [];
$totalGeralPendente = 0.0;
$totalGeralRecebido = 0.0;
$totalGeralEmAtraso = 0.0;
$hojeData = date('Y-m-d');

foreach ($todasParcelas as $p) {
    $chave = !empty($p['venda_id']) ? 'venda_' . $p['venda_id'] : 'conta_' . $p['id'];
    $nomeLimpo = preg_replace('/\s*\(\d+\/\d+\)$/', '', $p['cliente_nome']);

    if (!isset($gruposDividas[$chave])) {
        $gruposDividas[$chave] = [
            'venda_id' => $p['venda_id'],
            'cliente_nome' => $nomeLimpo,
            'cliente_telefone' => $p['cliente_telefone'],
            'produto_nome' => $p['produto_nome'] ?? 'Venda direta',
            'valor_total' => 0.0,
            'valor_pago' => 0.0,
            'valor_restante' => 0.0,
            'proximo_vencimento' => $p['data_vencimento'],
            'tem_atraso' => false,
            'parcelas' => []
        ];
    }

    $valorTotalParcela = (float)$p['valor_total'];
    $valorPagoParcela = (float)$p['valor_pago'];
    $restanteParcela = max(0, $valorTotalParcela - $valorPagoParcela);

    $gruposDividas[$chave]['valor_total'] += $valorTotalParcela;
    $gruposDividas[$chave]['valor_pago'] += $valorPagoParcela;
    $gruposDividas[$chave]['valor_restante'] += $restanteParcela;

    if ($p['status'] !== 'pago' && ($gruposDividas[$chave]['proximo_vencimento'] > $p['data_vencimento'] || $gruposDividas[$chave]['valor_pago'] == 0)) {
        $gruposDividas[$chave]['proximo_vencimento'] = $p['data_vencimento'];
    }

    if ($p['status'] !== 'pago' && $p['data_vencimento'] < $hojeData) {
        $totalGeralEmAtraso += $restanteParcela;
        $gruposDividas[$chave]['tem_atraso'] = true;
    }

    $gruposDividas[$chave]['parcelas'][] = $p;

    $totalGeralPendente += $restanteParcela;
    $totalGeralRecebido += $valorPagoParcela;
}

$gruposExibidos = [];
$qtdPendentes = 0;
$qtdAtrasados = 0;
$qtdQuitados = 0;

foreach ($gruposDividas as $chave => $grupo) {
    $estaQuitado = ($grupo['valor_restante'] <= 0);

    if ($estaQuitado) {
        $qtdQuitados++;
    } else {
        $qtdPendentes++;
        if ($grupo['tem_atraso']) {
            $qtdAtrasados++;
        }
    }

    if ($aba_filtro === 'pendentes' && !$estaQuitado) {
        $gruposExibidos[$chave] = $grupo;
    } elseif ($aba_filtro === 'atrasados' && !$estaQuitado && $grupo['tem_atraso']) {
        $gruposExibidos[$chave] = $grupo;
    } elseif ($aba_filtro === 'quitados' && $estaQuitado) {
        $gruposExibidos[$chave] = $grupo;
    } elseif ($aba_filtro === 'todos') {
        $gruposExibidos[$chave] = $grupo;
    }
}
?>

<script src="https://unpkg.com/lucide@latest"></script>

<style>
.divida-card.aberto .divida-seta-icon {
    transform: rotate(180deg);
}
.divida-detalhes-parcelas {
    display: none;
}
.divida-card.aberto .divida-detalhes-parcelas {
    display: block;
}
/* Scroll suave para abas mobile sem quebras */
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
                <i data-lucide="wallet" class="w-5 h-5 text-emerald-400"></i>
            </div>
            A Receber
        </h2>
        <p class="text-sm text-zinc-400 mt-2 m-0">
            Controle de crediário, parcelamentos e baixas financeiras
        </p>
    </div>
</header>

<?= $mensagem ?>

<!-- CARDS DE RESUMO -->
<div class="grid grid-cols-2 md:grid-cols-3 gap-3 sm:gap-4 mb-6">
    <div class="bg-[#09090b] border border-amber-500/20 rounded-2xl p-4 sm:p-5">
        <span class="text-[11px] font-semibold text-amber-400 uppercase tracking-wider block mb-1">Total Pendente</span>
        <strong class="text-xl sm:text-2xl font-black text-amber-400">R$ <?= number_format($totalGeralPendente, 2, ',', '.') ?></strong>
        <span class="text-[11px] text-zinc-500 block mt-1">Saldo a receber</span>
    </div>

    <div class="bg-[#09090b] border border-emerald-500/20 rounded-2xl p-4 sm:p-5">
        <span class="text-[11px] font-semibold text-emerald-400 uppercase tracking-wider block mb-1">Total Recebido</span>
        <strong class="text-xl sm:text-2xl font-black text-emerald-400">R$ <?= number_format($totalGeralRecebido, 2, ',', '.') ?></strong>
        <span class="text-[11px] text-zinc-500 block mt-1">Parcelas quitadas</span>
    </div>

    <div class="col-span-2 md:col-span-1 bg-[#09090b] border <?= $totalGeralEmAtraso > 0 ? 'border-rose-500/30' : 'border-zinc-800' ?> rounded-2xl p-4 sm:p-5">
        <span class="text-[11px] font-semibold <?= $totalGeralEmAtraso > 0 ? 'text-rose-400' : 'text-zinc-400' ?> uppercase tracking-wider block mb-1">Em Atraso</span>
        <strong class="text-xl sm:text-2xl font-black <?= $totalGeralEmAtraso > 0 ? 'text-rose-400' : 'text-zinc-200' ?>">
            R$ <?= number_format($totalGeralEmAtraso, 2, ',', '.') ?>
        </strong>
        <span class="text-[11px] text-zinc-500 block mt-1">Parcelas vencidas</span>
    </div>
</div>

<!-- LISTAGEM COM ABAS DE STATUS MOBILE-FIRST -->
<div class="bg-[#09090b] border border-zinc-800/80 rounded-2xl p-4 sm:p-6">
    
    <!-- HEADER INTEGRADO -->
    <div class="flex flex-col gap-4 mb-6 pb-5 border-b border-zinc-900">
        
        <div class="flex items-center justify-between gap-2.5">
            <div class="flex items-center gap-2.5">
                <div class="p-1.5 bg-emerald-500/10 rounded-xl border border-emerald-500/20">
                    <i data-lucide="users" class="w-4.5 h-4.5 text-emerald-400"></i>
                </div>
                <h3 class="text-base font-bold text-white m-0">Clientes e Parcelamentos</h3>
            </div>
            
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-zinc-800 text-zinc-400 border border-zinc-700/50">
                <?= count($gruposExibidos) ?>
            </span>
        </div>

        <!-- BARRA DE ABAS COM SCROLL TOUCH NO MOBILE -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-1">
            <div class="inline-flex bg-[#000000] p-1 rounded-xl border border-zinc-800 text-xs shrink-0">
                <a href="index.php?page=a-receber&aba=pendentes<?= !empty($busca) ? '&busca='.urlencode($busca) : '' ?>" 
                   class="px-3 py-1.5 rounded-lg no-underline transition flex items-center gap-1.5 whitespace-nowrap <?= $aba_filtro === 'pendentes' ? 'bg-amber-500 text-black font-bold shadow-md shadow-amber-500/20' : 'text-zinc-400 hover:text-white' ?>">
                    <span>Pendentes</span>
                    <span class="text-[10px] opacity-80">(<?= $qtdPendentes ?>)</span>
                </a>

                <a href="index.php?page=a-receber&aba=atrasados<?= !empty($busca) ? '&busca='.urlencode($busca) : '' ?>" 
                   class="px-3 py-1.5 rounded-lg no-underline transition flex items-center gap-1.5 whitespace-nowrap <?= $aba_filtro === 'atrasados' ? 'bg-rose-500 text-white font-bold shadow-md shadow-rose-500/20' : 'text-zinc-400 hover:text-white' ?>">
                    <span>Em Atraso</span>
                    <span class="text-[10px] opacity-80">(<?= $qtdAtrasados ?>)</span>
                </a>

                <a href="index.php?page=a-receber&aba=quitados<?= !empty($busca) ? '&busca='.urlencode($busca) : '' ?>" 
                   class="px-3 py-1.5 rounded-lg no-underline transition flex items-center gap-1.5 whitespace-nowrap <?= $aba_filtro === 'quitados' ? 'bg-emerald-500 text-black font-bold shadow-md shadow-emerald-500/20' : 'text-zinc-400 hover:text-white' ?>">
                    <span>Quitados</span>
                    <span class="text-[10px] opacity-80">(<?= $qtdQuitados ?>)</span>
                </a>

                <a href="index.php?page=a-receber&aba=todos<?= !empty($busca) ? '&busca='.urlencode($busca) : '' ?>" 
                   class="px-3 py-1.5 rounded-lg no-underline transition whitespace-nowrap <?= $aba_filtro === 'todos' ? 'bg-zinc-800 text-white font-bold' : 'text-zinc-400 hover:text-white' ?>">
                    Todos
                </a>
            </div>
        </div>

        <!-- BUSCA -->
        <form method="GET" action="index.php" class="m-0 relative w-full">
            <input type="hidden" name="page" value="a-receber">
            <input type="hidden" name="aba" value="<?= htmlspecialchars($aba_filtro) ?>">
            <div class="relative">
                <i data-lucide="search" class="w-4 h-4 text-zinc-500 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                <input type="text" name="busca" placeholder="Buscar cliente ou produto..." value="<?= htmlspecialchars($busca) ?>"
                       class="pl-9 pr-4 py-2 bg-[#000000] border border-zinc-800 text-zinc-200 text-xs rounded-xl outline-none focus:border-emerald-500 transition-colors placeholder:text-zinc-600 w-full">
            </div>
        </form>
    </div>

    <!-- LISTA DE CARDS -->
    <?php if (count($gruposExibidos) > 0): ?>
        <div class="space-y-3">
            <?php foreach ($gruposExibidos as $chave => $grupo): 
                $totalParcelasQtd = count($grupo['parcelas']);
                $parcelasPagasQtd = 0;
                $temAtrasoNoGrupo = false;

                foreach ($grupo['parcelas'] as $parc) {
                    if ($parc['status'] === 'pago') {
                        $parcelasPagasQtd++;
                    } elseif ($parc['data_vencimento'] < $hojeData) {
                        $temAtrasoNoGrupo = true;
                    }
                }
                $estaQuitado = ($grupo['valor_restante'] <= 0);
            ?>
                <div class="divida-card bg-[#000000] border <?= $estaQuitado ? 'border-emerald-500/30' : ($temAtrasoNoGrupo ? 'border-rose-500/30' : 'border-zinc-800/80') ?> rounded-2xl overflow-hidden transition hover:border-zinc-700" id="card-<?= $chave ?>">
                    
                    <!-- CABEÇALHO DO ACCORDION -->
                    <div class="w-full flex items-center justify-between gap-3 p-3.5 sm:p-4 transition hover:bg-zinc-900/40">
                        <div class="flex items-center gap-3 min-w-0 flex-1 cursor-pointer" onclick="toggleDivida('<?= $chave ?>')">
                            <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-xl <?= $estaQuitado ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : ($temAtrasoNoGrupo ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-zinc-900 text-zinc-400 border border-zinc-800') ?> flex items-center justify-center shrink-0 font-bold text-sm">
                                <?php if ($estaQuitado): ?>
                                    <i data-lucide="check" class="w-4 h-4 text-emerald-400"></i>
                                <?php elseif ($temAtrasoNoGrupo): ?>
                                    <i data-lucide="alert-triangle" class="w-4 h-4 text-rose-400"></i>
                                <?php else: ?>
                                    <?= strtoupper(substr($grupo['cliente_nome'], 0, 1)) ?>
                                <?php endif; ?>
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5 flex-wrap">
                                    <strong class="text-white text-sm font-semibold truncate"><?= htmlspecialchars($grupo['cliente_nome']) ?></strong>
                                    
                                    <?php if ($totalParcelasQtd > 1): ?>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-zinc-800 text-zinc-300 border border-zinc-700">
                                            <?= $totalParcelasQtd ?>x
                                        </span>
                                    <?php endif; ?>

                                    <?php if ($estaQuitado): ?>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                            Quitado
                                        </span>
                                    <?php elseif ($temAtrasoNoGrupo): ?>
                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-rose-500/10 text-rose-400 border border-rose-500/20">
                                            Atrasado
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-xs text-zinc-500 block truncate mt-0.5">
                                    <?= htmlspecialchars($grupo['produto_nome']) ?>
                                </span>
                            </div>
                        </div>

                        <!-- VALORES E AÇÕES -->
                        <div class="flex items-center gap-3 shrink-0 ml-2">
                            <div class="text-right cursor-pointer" onclick="toggleDivida('<?= $chave ?>')">
                                <span class="text-[10px] text-zinc-500 uppercase tracking-wider block">Restante</span>
                                <strong class="text-sm font-bold <?= $estaQuitado ? 'text-emerald-400' : ($temAtrasoNoGrupo ? 'text-rose-400' : 'text-amber-400') ?> block whitespace-nowrap">
                                    R$ <?= number_format($grupo['valor_restante'], 2, ',', '.') ?>
                                </strong>
                            </div>

                            <?php if (!$estaQuitado && !empty($grupo['venda_id'])): ?>
                                <button type="button" 
                                        onclick="abrirModalQuitarTudo(<?= (int)$grupo['venda_id'] ?>, '<?= htmlspecialchars($grupo['cliente_nome']) ?>', <?= $grupo['valor_restante'] ?>)"
                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-xl bg-emerald-500/10 hover:bg-emerald-500 text-emerald-400 hover:text-black border border-emerald-500/20 text-xs font-bold transition cursor-pointer">
                                    <i data-lucide="check-check" class="w-3.5 h-3.5"></i>
                                    <span class="hidden sm:inline">Quitar Tudo</span>
                                </button>
                            <?php endif; ?>

                            <div class="divida-seta-icon w-7 h-7 rounded-lg bg-zinc-900 border border-zinc-800 flex items-center justify-center text-zinc-400 transition-transform duration-200 cursor-pointer" onclick="toggleDivida('<?= $chave ?>')">
                                <i data-lucide="chevron-down" class="w-4 h-4"></i>
                            </div>
                        </div>
                    </div>

                    <!-- DETALHES DAS PARCELAS RESPONSIVOS (CARDS NO MOBILE / TABELA NO DESKTOP) -->
                    <div class="divida-detalhes-parcelas border-t border-zinc-900 p-3.5 sm:p-4 bg-zinc-950/60">
                        
                        <!-- VERSÃO DESKTOP (TABELA) -->
                        <div class="hidden sm:block overflow-x-auto">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="text-zinc-500 uppercase tracking-wider border-b border-zinc-900 pb-2 font-semibold">
                                        <th class="py-2 px-3">Parcela</th>
                                        <th class="py-2 px-3">Vencimento</th>
                                        <th class="py-2 px-3">Valor</th>
                                        <th class="py-2 px-3">Pago</th>
                                        <th class="py-2 px-3">Restante</th>
                                        <th class="py-2 px-3">Status</th>
                                        <th class="py-2 px-3 text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-900/50">
                                    <?php foreach ($grupo['parcelas'] as $idx => $p): 
                                        $numParc = $idx + 1;
                                        $valTotal = (float)$p['valor_total'];
                                        $valPago = (float)$p['valor_pago'];
                                        $valRest = max(0, $valTotal - $valPago);
                                        $statusP = $p['status'];
                                        $estaAtrasada = ($statusP !== 'pago' && $p['data_vencimento'] < $hojeData);
                                        $telLimpo = preg_replace('/\D/', '', $p['cliente_telefone'] ?? '');
                                        
                                        $msgWhats = urlencode("Olá " . $grupo['cliente_nome'] . "! Passando para lembrar da parcela " . $numParc . "/" . $totalParcelasQtd . " no valor de R$ " . number_format($valRest, 2, ',', '.') . " com vencimento em " . date('d/m/Y', strtotime($p['data_vencimento'])) . ".");
                                    ?>
                                        <tr class="hover:bg-zinc-900/30 transition <?= $statusP === 'pago' ? 'opacity-70' : '' ?>">
                                            <td class="py-3 px-3 font-semibold text-white">Parcela <?= $numParc ?>/<?= $totalParcelasQtd ?></td>
                                            <td class="py-3 px-3 font-medium <?= $estaAtrasada ? 'text-rose-400' : 'text-zinc-400' ?>">
                                                <?= date('d/m/Y', strtotime($p['data_vencimento'])) ?>
                                            </td>
                                            <td class="py-3 px-3 text-zinc-200 font-medium">R$ <?= number_format($valTotal, 2, ',', '.') ?></td>
                                            <td class="py-3 px-3 text-emerald-400 font-medium">R$ <?= number_format($valPago, 2, ',', '.') ?></td>
                                            <td class="py-3 px-3 font-bold <?= $valRest > 0 ? ($estaAtrasada ? 'text-rose-400' : 'text-amber-400') : 'text-zinc-500' ?>">
                                                R$ <?= number_format($valRest, 2, ',', '.') ?>
                                            </td>
                                            <td class="py-3 px-3">
                                                <?php if ($statusP === 'pago'): ?>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Pago</span>
                                                <?php elseif ($estaAtrasada): ?>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">Em Atraso</span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">Pendente</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="py-3 px-3 text-right">
                                                <div class="inline-flex items-center gap-1.5">
                                                    <?php if ($valRest > 0 && !empty($telLimpo)): ?>
                                                        <a href="https://wa.me/55<?= $telLimpo ?>?text=<?= $msgWhats ?>" target="_blank"
                                                           class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-emerald-500/10 hover:bg-emerald-500/20 text-emerald-400 border border-emerald-500/20 font-semibold no-underline transition" title="Cobrar">
                                                            <i data-lucide="message-circle" class="w-3.5 h-3.5"></i>
                                                            <span>Cobrar</span>
                                                        </a>
                                                    <?php endif; ?>

                                                    <?php if ($valRest > 0): ?>
                                                        <button type="button" 
                                                                onclick="abrirModalBaixa(<?= (int)$p['id'] ?>, '<?= htmlspecialchars($grupo['cliente_nome']) ?> (<?= $numParc ?>/<?= $totalParcelasQtd ?>)', <?= $valRest ?>)"
                                                                class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 text-black font-bold transition cursor-pointer">
                                                            <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                                            <span>Baixa</span>
                                                        </button>
                                                    <?php endif; ?>

                                                    <button type="button" 
                                                            onclick="abrirModalEditarParcela(<?= (int)$p['id'] ?>, '<?= htmlspecialchars($grupo['cliente_nome']) ?> (<?= $numParc ?>/<?= $totalParcelasQtd ?>)', <?= $valTotal ?>, <?= $valPago ?>, '<?= $p['data_vencimento'] ?>')"
                                                            class="p-1.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-zinc-400 hover:text-white border border-zinc-800 transition cursor-pointer">
                                                        <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- VERSÃO MOBILE (SUB-CARDS LIMPOS) -->
                        <div class="sm:hidden space-y-2.5">
                            <?php foreach ($grupo['parcelas'] as $idx => $p): 
                                $numParc = $idx + 1;
                                $valTotal = (float)$p['valor_total'];
                                $valPago = (float)$p['valor_pago'];
                                $valRest = max(0, $valTotal - $valPago);
                                $statusP = $p['status'];
                                $estaAtrasada = ($statusP !== 'pago' && $p['data_vencimento'] < $hojeData);
                                $telLimpo = preg_replace('/\D/', '', $p['cliente_telefone'] ?? '');
                                
                                $msgWhats = urlencode("Olá " . $grupo['cliente_nome'] . "! Passando para lembrar da parcela " . $numParc . "/" . $totalParcelasQtd . " no valor de R$ " . number_format($valRest, 2, ',', '.') . " com vencimento em " . date('d/m/Y', strtotime($p['data_vencimento'])) . ".");
                            ?>
                                <div class="bg-[#09090b] border border-zinc-900 rounded-xl p-3 flex flex-col gap-2.5">
                                    <div class="flex items-center justify-between">
                                        <span class="font-bold text-xs text-white">Parcela <?= $numParc ?>/<?= $totalParcelasQtd ?></span>
                                        
                                        <?php if ($statusP === 'pago'): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Pago</span>
                                        <?php elseif ($estaAtrasada): ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/10 text-rose-400 border border-rose-500/20">Em Atraso</span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">Pendente</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="grid grid-cols-2 gap-2 text-xs border-y border-zinc-900/60 py-2">
                                        <div>
                                            <span class="text-zinc-500 block text-[10px]">Vencimento</span>
                                            <span class="font-semibold <?= $estaAtrasada ? 'text-rose-400' : 'text-zinc-300' ?>">
                                                <?= date('d/m/Y', strtotime($p['data_vencimento'])) ?>
                                            </span>
                                        </div>
                                        <div>
                                            <span class="text-zinc-500 block text-[10px]">Valor Restante</span>
                                            <span class="font-bold <?= $valRest > 0 ? ($estaAtrasada ? 'text-rose-400' : 'text-amber-400') : 'text-zinc-500' ?>">
                                                R$ <?= number_format($valRest, 2, ',', '.') ?>
                                            </span>
                                        </div>
                                    </div>

                                    <div class="flex items-center justify-end gap-2 pt-0.5">
                                        <?php if ($valRest > 0 && !empty($telLimpo)): ?>
                                            <a href="https://wa.me/55<?= $telLimpo ?>?text=<?= $msgWhats ?>" target="_blank"
                                               class="px-2.5 py-1.5 rounded-lg bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 text-xs font-semibold no-underline">
                                                Cobrar
                                            </a>
                                        <?php endif; ?>

                                        <?php if ($valRest > 0): ?>
                                            <button type="button" 
                                                    onclick="abrirModalBaixa(<?= (int)$p['id'] ?>, '<?= htmlspecialchars($grupo['cliente_nome']) ?> (<?= $numParc ?>/<?= $totalParcelasQtd ?>)', <?= $valRest ?>)"
                                                    class="px-3 py-1.5 rounded-lg bg-emerald-500 text-black text-xs font-bold">
                                                Dar Baixa
                                            </button>
                                        <?php endif; ?>

                                        <button type="button" 
                                                onclick="abrirModalEditarParcela(<?= (int)$p['id'] ?>, '<?= htmlspecialchars($grupo['cliente_nome']) ?> (<?= $numParc ?>/<?= $totalParcelasQtd ?>)', <?= $valTotal ?>, <?= $valPago ?>, '<?= $p['data_vencimento'] ?>')"
                                                class="p-1.5 rounded-lg bg-zinc-900 text-zinc-400 border border-zinc-800">
                                            <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-zinc-500 text-center py-10 text-sm">
            Nenhum registro encontrado para esta aba.
        </div>
    <?php endif; ?>
</div>

<!-- MODAL DAR BAIXA EM PARCELA -->
<div id="modal-baixa" class="hidden fixed inset-0 bg-black/75 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-[#121215] border border-zinc-800 rounded-2xl p-6 shadow-2xl">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="p-1.5 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-xl">
                <i data-lucide="wallet" class="w-5 h-5"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-white m-0">Receber Pagamento</h3>
                <span class="text-xs text-zinc-400 block" id="modal-cliente-nome"></span>
            </div>
        </div>

        <form method="POST" action="index.php?page=a-receber" class="space-y-4">
            <?php if (function_exists('csrf_token')): ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <?php endif; ?>
            <input type="hidden" name="dar_baixa_parcela" value="1">
            <input type="hidden" name="conta_id" id="modal-conta-id" value="">

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Valor Pago Agora (R$)</label>
                <input type="text" name="valor_pago" id="modal-valor-pago" required
                       class="w-full bg-[#000000] border border-zinc-800 text-zinc-100 text-sm font-bold rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors">
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="button" onclick="fecharModalBaixa()"
                        class="flex-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 text-xs font-semibold py-2.5 rounded-xl transition cursor-pointer">
                    Cancelar
                </button>
                <button type="submit"
                        class="flex-1 bg-emerald-500 hover:bg-emerald-400 text-black text-xs font-bold py-2.5 rounded-xl transition cursor-pointer">
                    Confirmar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL EDITAR / ESTORNAR PARCELA -->
<div id="modal-editar-parcela" class="hidden fixed inset-0 bg-black/75 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-[#121215] border border-zinc-800 rounded-2xl p-6 shadow-2xl">
        <div class="flex items-center gap-2.5 mb-4">
            <div class="p-1.5 bg-zinc-800 text-zinc-300 rounded-xl">
                <i data-lucide="pencil" class="w-5 h-5 text-emerald-400"></i>
            </div>
            <div>
                <h3 class="text-base font-bold text-white m-0">Editar Parcela</h3>
                <span class="text-xs text-zinc-400 block" id="modal-edit-cliente-nome"></span>
            </div>
        </div>

        <form method="POST" action="index.php?page=a-receber" class="space-y-4">
            <?php if (function_exists('csrf_token')): ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <?php endif; ?>
            <input type="hidden" name="salvar_edicao_parcela" value="1">
            <input type="hidden" name="conta_id" id="modal-edit-conta-id" value="">

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Valor Já Pago (R$)</label>
                <input type="text" name="valor_pago" id="modal-edit-valor-pago" required placeholder="0,00"
                       class="w-full bg-[#000000] border border-zinc-800 text-zinc-100 text-sm font-bold rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors">
                <span class="text-[11px] text-zinc-500 mt-1 block">Coloque 0,00 para reabrir a parcela como pendente.</span>
            </div>

            <div>
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Data de Vencimento</label>
                <input type="date" name="data_vencimento" id="modal-edit-vencimento" required
                       class="w-full bg-[#000000] border border-zinc-800 text-zinc-100 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors">
            </div>

            <div class="flex items-center gap-2 pt-2">
                <button type="button" onclick="fecharModalEditarParcela()"
                        class="flex-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 text-xs font-semibold py-2.5 rounded-xl transition cursor-pointer">
                    Cancelar
                </button>
                <button type="submit"
                        class="flex-1 bg-emerald-500 hover:bg-emerald-400 text-black text-xs font-bold py-2.5 rounded-xl transition cursor-pointer">
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL CONFIRMAÇÃO DARK PARA QUITAR TUDO -->
<div id="modal-confirmar-quitar-tudo" class="hidden fixed inset-0 bg-black/75 backdrop-blur-sm z-[9999] flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-[#121215] border border-zinc-800 rounded-2xl p-6 text-center shadow-2xl">
        <div class="w-12 h-12 bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 rounded-2xl flex items-center justify-center mx-auto mb-4">
            <i data-lucide="check-check" class="w-6 h-6"></i>
        </div>
        
        <h3 class="text-base font-bold text-white mb-1.5">Quitar todas as parcelas?</h3>
        <p class="text-xs text-zinc-400 mb-4">
            Deseja liquidar totalmente a dívida de <strong class="text-white" id="quitar-tudo-modal-cliente"></strong> no valor restante de <strong class="text-emerald-400" id="quitar-tudo-modal-valor"></strong>?
        </p>

        <form method="POST" action="index.php?page=a-receber">
            <?php if (function_exists('csrf_token')): ?>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
            <?php endif; ?>
            <input type="hidden" name="quitar_tudo_venda" value="1">
            <input type="hidden" name="venda_id" id="quitar-tudo-modal-venda-id" value="">

            <div class="flex items-center gap-2.5">
                <button type="button" onclick="fecharModalQuitarTudo()"
                        class="flex-1 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 text-xs font-semibold py-2.5 rounded-xl transition cursor-pointer">
                    Cancelar
                </button>
                <button type="submit"
                        class="flex-1 bg-emerald-500 hover:bg-emerald-400 text-black text-xs font-bold py-2.5 rounded-xl transition cursor-pointer">
                    Sim, Quitar Tudo
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
});

function toggleDivida(chave) {
    const card = document.getElementById('card-' + chave);
    if (card) {
        card.classList.toggle('aberto');
    }
}

function abrirModalBaixa(id, nomeCliente, valorRestante) {
    document.getElementById('modal-conta-id').value = id;
    document.getElementById('modal-cliente-nome').textContent = nomeCliente;
    document.getElementById('modal-valor-pago').value = valorRestante.toFixed(2).replace('.', ',');
    
    const modal = document.getElementById('modal-baixa');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function fecharModalBaixa() {
    const modal = document.getElementById('modal-baixa');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}

function abrirModalEditarParcela(id, nomeCliente, valorTotal, valorPago, vencimento) {
    document.getElementById('modal-edit-conta-id').value = id;
    document.getElementById('modal-edit-cliente-nome').textContent = nomeCliente;
    document.getElementById('modal-edit-valor-pago').value = valorPago.toFixed(2).replace('.', ',');
    document.getElementById('modal-edit-vencimento').value = vencimento;

    const modal = document.getElementById('modal-editar-parcela');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function fecharModalEditarParcela() {
    const modal = document.getElementById('modal-editar-parcela');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}

function abrirModalQuitarTudo(vendaId, nomeCliente, valorRestante) {
    document.getElementById('quitar-tudo-modal-venda-id').value = vendaId;
    document.getElementById('quitar-tudo-modal-cliente').textContent = nomeCliente;
    document.getElementById('quitar-tudo-modal-valor').textContent = 'R$ ' + valorRestante.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const modal = document.getElementById('modal-confirmar-quitar-tudo');
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function fecharModalQuitarTudo() {
    const modal = document.getElementById('modal-confirmar-quitar-tudo');
    modal.classList.remove('flex');
    modal.classList.add('hidden');
}
</script>