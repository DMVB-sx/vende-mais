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
    
    // Se possui vírgula (ex: 10,00 ou 1.250,50)
    if (strpos($v, ',') !== false) {
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    }
    return (float)$v;
}

/*
|--------------------------------------------------------------------------
| 1. EXCLUIR DESPESA
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_despesa'])) {
    try {
        if (function_exists('validar_csrf')) validar_csrf();
        $id_del = (int)($_POST['id_despesa'] ?? 0);

        if ($id_del > 0) {
            $stmtDel = $pdo->prepare("DELETE FROM despesas WHERE id = ? AND empresa_id = ?");
            $stmtDel->execute([$id_del, $empresa_id]);

            $mensagem = '
                <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                    <div><strong class="font-semibold block text-emerald-300">Despesa excluída com sucesso!</strong></div>
                </div>
            ';
        }
    } catch (Throwable $e) {
        $mensagem = '
            <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                <i data-lucide="alert-circle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                <div><strong class="font-semibold block text-rose-300">Erro ao excluir despesa.</strong></div>
            </div>
        ';
    }
}

/*
|--------------------------------------------------------------------------
| 2. CADASTRAR OU EDITAR DESPESA
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_despesa'])) {
    try {
        if (function_exists('validar_csrf')) validar_csrf();

        $id_despesa = (int)($_POST['id_despesa'] ?? 0);
        $descricao = trim($_POST['descricao'] ?? '');
        $categoria = trim($_POST['categoria'] ?? 'Operacional');
        
        $valor = converterMoedaParaFloat($_POST['valor'] ?? '0');
        
        $data_vencimento = trim($_POST['data_vencimento'] ?? '') ?: date('Y-m-d');
        $pago = isset($_POST['pago']) ? 1 : 0;

        if (empty($descricao)) {
            throw new Exception('Informe a descrição da despesa.');
        }

        if ($valor <= 0) {
            throw new Exception('O valor da despesa deve ser maior que zero.');
        }

        if ($id_despesa > 0) {
            $stmtUp = $pdo->prepare("
                UPDATE despesas 
                SET descricao = ?, categoria = ?, valor = ?, data_vencimento = ?, pago = ? 
                WHERE id = ? AND empresa_id = ?
            ");
            $stmtUp->execute([$descricao, $categoria, $valor, $data_vencimento, $pago, $id_despesa, $empresa_id]);

            $mensagem = '
                <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                    <div><strong class="font-semibold block text-emerald-300">Despesa atualizada com sucesso!</strong></div>
                </div>
            ';
        } else {
            $stmtIns = $pdo->prepare("
                INSERT INTO despesas (empresa_id, descricao, categoria, valor, data_vencimento, pago) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmtIns->execute([$empresa_id, $descricao, $categoria, $valor, $data_vencimento, $pago]);

            $mensagem = '
                <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                    <div><strong class="font-semibold block text-emerald-300">Despesa lançada com sucesso!</strong></div>
                </div>
            ';
        }
    } catch (Throwable $e) {
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
| 3. BUSCAR PARA EDIÇÃO
|--------------------------------------------------------------------------
*/
$despesa_editar = null;
if (isset($_GET['acao']) && $_GET['acao'] === 'editar' && isset($_GET['id'])) {
    $id_ed = (int)$_GET['id'];
    $stmtE = $pdo->prepare("SELECT * FROM despesas WHERE id = ? AND empresa_id = ? LIMIT 1");
    $stmtE->execute([$id_ed, $empresa_id]);
    $despesa_editar = $stmtE->fetch(PDO::FETCH_ASSOC);
}

/*
|--------------------------------------------------------------------------
| 4. CONSULTA DE DESPESAS
|--------------------------------------------------------------------------
*/
$categoria_filtro = trim($_GET['categoria_filtro'] ?? '');
$paramsD = [$empresa_id];
$sql_filtro = '';

if (!empty($categoria_filtro)) {
    $sql_filtro .= " AND categoria = ? ";
    $paramsD[] = $categoria_filtro;
}

$stmtDesp = $pdo->prepare("SELECT * FROM despesas WHERE empresa_id = ? {$sql_filtro} ORDER BY data_vencimento DESC, id DESC");
$stmtDesp->execute($paramsD);
$despesas = $stmtDesp->fetchAll(PDO::FETCH_ASSOC);

$totalDespesasPagas = 0.0;
$totalDespesasPendentes = 0.0;

foreach ($despesas as $d) {
    if ((int)$d['pago'] === 1) {
        $totalDespesasPagas += (float)$d['valor'];
    } else {
        $totalDespesasPendentes += (float)$d['valor'];
    }
}
?>

<script src="https://unpkg.com/lucide@latest"></script>

<!-- CABEÇALHO -->
<header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-black text-white flex items-center gap-2.5 tracking-tight m-0">
            <div class="p-2 bg-rose-500/10 rounded-xl border border-rose-500/20">
                <i data-lucide="trending-down" class="w-5 h-5 text-rose-400"></i>
            </div>
            <?= $despesa_editar ? 'Editar Despesa' : 'Despesas' ?>
        </h2>
        <p class="text-sm text-zinc-400 mt-2 m-0">
            Controle custos fixos, operacionais e saídas financeiras
        </p>
    </div>
</header>

<?= $mensagem ?>

<!-- CARDS DE RESUMO -->
<div class="grid grid-cols-2 gap-3 sm:gap-4 mb-6">
    <div class="bg-[#09090b] border border-rose-500/20 rounded-2xl p-4 sm:p-5">
        <span class="text-[11px] font-semibold text-rose-400 uppercase tracking-wider block mb-1">Total Pago</span>
        <strong class="text-xl sm:text-2xl font-black text-rose-400">R$ <?= number_format($totalDespesasPagas, 2, ',', '.') ?></strong>
        <span class="text-[11px] text-zinc-500 block mt-1">Lançadas no caixa</span>
    </div>

    <div class="bg-[#09090b] border border-zinc-800 rounded-2xl p-4 sm:p-5">
        <span class="text-[11px] font-semibold text-zinc-400 uppercase tracking-wider block mb-1">A Pagar</span>
        <strong class="text-xl sm:text-2xl font-black text-zinc-200">R$ <?= number_format($totalDespesasPendentes, 2, ',', '.') ?></strong>
        <span class="text-[11px] text-zinc-500 block mt-1">Despesas pendentes</span>
    </div>
</div>

<!-- FORMULÁRIO -->
<div class="bg-[#09090b] border <?= $despesa_editar ? 'border-rose-500/40 shadow-[0_0_30px_rgba(244,63,94,0.08)]' : 'border-zinc-800/80' ?> rounded-2xl p-4 sm:p-6 mb-8 max-w-3xl overflow-hidden box-border">
    <div class="flex items-center gap-2.5 mb-5">
        <div class="p-1.5 bg-zinc-800/60 rounded-lg text-zinc-400">
            <i data-lucide="<?= $despesa_editar ? 'pencil' : 'plus' ?>" class="w-4 h-4 <?= $despesa_editar ? 'text-rose-400' : '' ?>"></i>
        </div>
        <h3 class="text-base font-bold text-white m-0">
            <?= $despesa_editar ? 'Alterar Dados da Despesa' : 'Lançar Nova Despesa' ?>
        </h3>
    </div>

    <form method="POST" action="index.php?page=despesas" class="space-y-4">
        <?php if (function_exists('csrf_token')): ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
        <?php endif; ?>
        <?php if ($despesa_editar): ?>
            <input type="hidden" name="id_despesa" value="<?= (int)$despesa_editar['id'] ?>">
        <?php endif; ?>

        <div>
            <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Descrição *</label>
            <input type="text" name="descricao" required placeholder="Ex: Embalagens, Luz, Anúncios, Aluguel"
                   value="<?= htmlspecialchars($despesa_editar['descricao'] ?? '') ?>"
                   class="w-full box-border bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-rose-500 transition-colors">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="min-w-0">
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Categoria</label>
                <div class="relative">
                    <select name="categoria" class="w-full box-border bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-rose-500 transition-colors cursor-pointer appearance-none">
                        <option value="Operacional" <?= ($despesa_editar && $despesa_editar['categoria'] === 'Operacional') ? 'selected' : '' ?>>Operacional</option>
                        <option value="Marketing" <?= ($despesa_editar && $despesa_editar['categoria'] === 'Marketing') ? 'selected' : '' ?>>Marketing / Anúncios</option>
                        <option value="Fixa" <?= ($despesa_editar && $despesa_editar['categoria'] === 'Fixa') ? 'selected' : '' ?>>Despesa Fixa</option>
                        <option value="Impostos" <?= ($despesa_editar && $despesa_editar['categoria'] === 'Impostos') ? 'selected' : '' ?>>Impostos / Taxas</option>
                        <option value="Outros" <?= ($despesa_editar && $despesa_editar['categoria'] === 'Outros') ? 'selected' : '' ?>>Outros</option>
                    </select>
                    <i data-lucide="chevron-down" class="w-4 h-4 text-zinc-500 absolute right-3.5 top-1/2 -translate-y-1/2 pointer-events-none"></i>
                </div>
            </div>

            <div class="min-w-0">
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Valor (R$) *</label>
                <input type="text" name="valor" required placeholder="0,00"
                       value="<?= $despesa_editar ? number_format((float)$despesa_editar['valor'], 2, ',', '.') : '' ?>"
                       class="w-full box-border bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-rose-500 transition-colors">
            </div>

            <!-- CONTAINER DE DATA AJUSTADO PARA EVITAR OVERFLOW NO IOS -->
            <div class="min-w-0">
                <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Data Vencimento</label>
                <input type="date" name="data_vencimento" 
                       value="<?= htmlspecialchars($despesa_editar['data_vencimento'] ?? date('Y-m-d')) ?>"
                       class="w-full max-w-full box-border appearance-none min-w-0 bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-rose-500 transition-colors">
            </div>
        </div>

        <div class="bg-[#000000] border border-zinc-800/80 p-3.5 rounded-xl flex items-center gap-3">
            <input type="checkbox" name="pago" id="despesa_pago" class="w-4 h-4 accent-rose-500 cursor-pointer rounded" 
                   <?= ($despesa_editar ? ((int)$despesa_editar['pago'] === 1) : true) ? 'checked' : '' ?>>
            <label for="despesa_pago" class="text-xs text-zinc-300 cursor-pointer font-medium">
                Esta despesa já foi paga (debitar do caixa)
            </label>
        </div>

        <div class="flex items-center gap-3 pt-2">
            <button type="submit" name="salvar_despesa"
                    class="flex-1 inline-flex items-center justify-center gap-2 bg-rose-600 hover:bg-rose-500 text-white text-sm font-bold rounded-xl px-6 py-3 transition-all shadow-[0_0_20px_rgba(244,63,94,0.15)] cursor-pointer">
                <i data-lucide="check" class="w-4 h-4"></i>
                <?= $despesa_editar ? 'Atualizar Despesa' : 'Salvar Despesa' ?>
            </button>

            <?php if ($despesa_editar): ?>
                <a href="index.php?page=despesas" class="inline-flex items-center justify-center bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 text-sm font-semibold rounded-xl px-5 py-3 transition-colors no-underline">
                    Cancelar
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- LISTAGEM -->
<div class="bg-[#09090b] border border-zinc-800/80 rounded-2xl p-4 sm:p-6 overflow-hidden">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6 pb-5 border-b border-zinc-900">
        <div class="flex items-center gap-2.5">
            <div class="p-1.5 bg-rose-500/10 rounded-xl border border-rose-500/20">
                <i data-lucide="history" class="w-4.5 h-4.5 text-rose-400"></i>
            </div>
            <h3 class="text-base font-bold text-white m-0">Histórico de Despesas</h3>
            <span class="text-xs font-semibold px-2.5 py-1 rounded-full bg-zinc-800 text-zinc-400 border border-zinc-700/50">
                <?= count($despesas) ?>
            </span>
        </div>

        <form method="GET" action="index.php" class="m-0">
            <input type="hidden" name="page" value="despesas">
            <select name="categoria_filtro" onchange="this.form.submit()" 
                    class="w-full sm:w-auto bg-[#000000] border border-zinc-800 text-zinc-200 text-xs rounded-xl px-3 py-2 outline-none focus:border-rose-500">
                <option value="">Todas as Categorias</option>
                <option value="Operacional" <?= $categoria_filtro === 'Operacional' ? 'selected' : '' ?>>Operacional</option>
                <option value="Marketing" <?= $categoria_filtro === 'Marketing' ? 'selected' : '' ?>>Marketing</option>
                <option value="Fixa" <?= $categoria_filtro === 'Fixa' ? 'selected' : '' ?>>Fixa</option>
                <option value="Impostos" <?= $categoria_filtro === 'Impostos' ? 'selected' : '' ?>>Impostos</option>
                <option value="Outros" <?= $categoria_filtro === 'Outros' ? 'selected' : '' ?>>Outros</option>
            </select>
        </form>
    </div>

    <?php if (count($despesas) > 0): ?>
        <div class="space-y-2.5">
            <?php foreach ($despesas as $d): 
                $valD = (float)$d['valor'];
                $isPago = (int)$d['pago'] === 1;
            ?>
                <div class="bg-[#000000] border border-zinc-800/80 hover:border-zinc-700 rounded-xl p-3.5 flex items-center justify-between gap-3 transition">
                    <div class="flex items-center gap-3 min-w-0 flex-1">
                        <div class="p-2 bg-zinc-900 border border-zinc-800 rounded-lg text-rose-400 shrink-0">
                            <i data-lucide="receipt" class="w-4 h-4"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <strong class="text-white text-sm font-semibold truncate block"><?= htmlspecialchars($d['descricao']) ?></strong>
                            <div class="flex items-center gap-2 mt-0.5 text-xs text-zinc-500">
                                <span><?= htmlspecialchars($d['categoria']) ?></span>
                                <span>•</span>
                                <span><?= date('d/m/Y', strtotime($d['data_vencimento'])) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <div class="text-right">
                            <strong class="text-sm font-bold text-rose-400 block whitespace-nowrap">
                                R$ <?= number_format($valD, 2, ',', '.') ?>
                            </strong>
                            <span class="text-[10px] font-semibold <?= $isPago ? 'text-emerald-400' : 'text-amber-400' ?> block">
                                <?= $isPago ? 'Pago' : 'Pendente' ?>
                            </span>
                        </div>

                        <div class="flex items-center gap-1">
                            <a href="index.php?page=despesas&acao=editar&id=<?= (int)$d['id'] ?>" 
                               class="p-1.5 text-zinc-400 hover:text-white hover:bg-zinc-800 rounded-lg transition" title="Editar">
                                <i data-lucide="pencil" class="w-3.5 h-3.5"></i>
                            </a>
                            <form method="POST" action="index.php?page=despesas" onsubmit="return confirm('Excluir esta despesa?');" class="m-0">
                                <?php if (function_exists('csrf_token')): ?>
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <?php endif; ?>
                                <input type="hidden" name="id_despesa" value="<?= (int)$d['id'] ?>">
                                <button type="submit" name="excluir_despesa" class="p-1.5 text-zinc-400 hover:text-rose-400 hover:bg-rose-500/10 rounded-lg transition cursor-pointer bg-transparent border-none">
                                    <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="text-zinc-500 text-center py-8 text-sm">
            Nenhuma despesa cadastrada.
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
});
</script>