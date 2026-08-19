<?php
$mensagem = '';
$empresa_id = $_SESSION['empresa_id'];

// 1. Processar Ação de EXCLUIR / CANCELAR COMPRA
if (isset($_GET['acao']) && $_GET['acao'] === 'deletar' && isset($_GET['id'])) {
    $id_deletar = (int)$_GET['id'];

    $stmtCompOld = $pdo->prepare("SELECT produto_id, quantidade FROM compras WHERE id = ? AND empresa_id = ?");
    $stmtCompOld->execute([$id_deletar, $empresa_id]);
    $compraAntiga = $stmtCompOld->fetch();

    if ($compraAntiga) {
        $pdo->beginTransaction();
        try {
            $stmtAbate = $pdo->prepare("UPDATE produtos SET estoque = GREATEST(0, estoque - ?) WHERE id = ? AND empresa_id = ?");
            $stmtAbate->execute([$compraAntiga['quantidade'], $compraAntiga['produto_id'], $empresa_id]);

            $stmtDel = $pdo->prepare("DELETE FROM compras WHERE id = ? AND empresa_id = ?");
            $stmtDel->execute([$id_deletar, $empresa_id]);

            $pdo->commit();
            $mensagem = '<p style="color: #ef4444; margin-bottom: 20px;">🗑️ Compra cancelada e estoque ajustado com sucesso!</p>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem = '<p style="color: #ef4444; margin-bottom: 20px;">⚠️ Erro ao cancelar compra: ' . $e->getMessage() . '</p>';
        }
    }
}

// 2. CADASTRO ou EDIÇÃO de Compra
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_compra'])) {
    $id_compra = isset($_POST['id_compra']) ? (int)$_POST['id_compra'] : 0;
    $produto_id = (int)$_POST['produto_id'];
    $quantidade = (int)$_POST['quantidade'];
    $custo_unitario = (float)$_POST['custo_unitario'];
    $frete = (float)$_POST['frete'];

    if ($quantidade > 0 && $produto_id > 0) {
        $custo_real_unitario_remessa = $custo_unitario + ($frete / $quantidade);

        $pdo->beginTransaction();
        try {
            if ($id_compra > 0) {
                $stmtCOld = $pdo->prepare("SELECT produto_id, quantidade FROM compras WHERE id = ? AND empresa_id = ?");
                $stmtCOld->execute([$id_compra, $empresa_id]);
                $cAntiga = $stmtCOld->fetch();

                if ($cAntiga) {
                    $stmtRevert = $pdo->prepare("UPDATE produtos SET estoque = GREATEST(0, estoque - ?) WHERE id = ? AND empresa_id = ?");
                    $stmtRevert->execute([$cAntiga['quantidade'], $cAntiga['produto_id'], $empresa_id]);
                }

                $stmtP = $pdo->prepare("SELECT estoque, preco_custo FROM produtos WHERE id = ? AND empresa_id = ?");
                $stmtP->execute([$produto_id, $empresa_id]);
                $prodAtual = $stmtP->fetch();

                $qtd_antiga = $prodAtual ? (int)$prodAtual['estoque'] : 0;
                $custo_antigo = $prodAtual ? (float)$prodAtual['preco_custo'] : 0;

                $total_investido_antigo = $qtd_antiga * $custo_antigo;
                $total_investido_remessa = ($quantidade * $custo_unitario) + $frete;
                $nova_qtd_total = $qtd_antiga + $quantidade;

                $novo_custo_medio = $nova_qtd_total > 0 ? ($total_investido_antigo + $total_investido_remessa) / $nova_qtd_total : $custo_real_unitario_remessa;

                $stmtUpC = $pdo->prepare("UPDATE compras SET produto_id = ?, quantidade = ?, custo_unitario = ?, frete = ?, custo_real_unitario = ? WHERE id = ? AND empresa_id = ?");
                $stmtUpC->execute([$produto_id, $quantidade, $custo_unitario, $frete, $custo_real_unitario_remessa, $id_compra, $empresa_id]);

                $stmtUpP = $pdo->prepare("UPDATE produtos SET estoque = ?, preco_custo = ? WHERE id = ? AND empresa_id = ?");
                $stmtUpP->execute([$nova_qtd_total, $novo_custo_medio, $produto_id, $empresa_id]);

                $mensagem = '<p style="color: #10b981; margin-bottom: 20px;">✏️ Compra atualizada! Novo custo médio: R$ ' . number_format($novo_custo_medio, 2, ',', '.') . '</p>';

            } else {
                $stmtAntigo = $pdo->prepare("SELECT estoque, preco_custo FROM produtos WHERE id = ? AND empresa_id = ?");
                $stmtAntigo->execute([$produto_id, $empresa_id]);
                $prodAntigo = $stmtAntigo->fetch();

                $qtd_antiga = $prodAntigo ? (int)$prodAntigo['estoque'] : 0;
                $custo_antigo = $prodAntigo ? (float)$prodAntigo['preco_custo'] : 0;

                $total_investido_antigo = $qtd_antiga * $custo_antigo;
                $total_investido_remessa = ($quantidade * $custo_unitario) + $frete;
                $nova_qtd_total = $qtd_antiga + $quantidade;

                $novo_custo_medio = $nova_qtd_total > 0 ? ($total_investido_antigo + $total_investido_remessa) / $nova_qtd_total : $custo_real_unitario_remessa;

                $stmtC = $pdo->prepare("INSERT INTO compras (empresa_id, produto_id, quantidade, custo_unitario, frete, custo_real_unitario) VALUES (?, ?, ?, ?, ?, ?)");
                $stmtC->execute([$empresa_id, $produto_id, $quantidade, $custo_unitario, $frete, $custo_real_unitario_remessa]);

                $stmtP = $pdo->prepare("UPDATE produtos SET estoque = ?, preco_custo = ? WHERE id = ? AND empresa_id = ?");
                $stmtP->execute([$nova_qtd_total, $novo_custo_medio, $produto_id, $empresa_id]);

                $mensagem = '<p style="color: #10b981; margin-bottom: 20px;">✅ Compra registrada! Novo custo médio: R$ ' . number_format($novo_custo_medio, 2, ',', '.') . '</p>';
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem = '<p style="color: #ef4444; margin-bottom: 20px;">⚠️ Erro ao processar compra: ' . $e->getMessage() . '</p>';
        }
    }
}

// 3. Edição
$compra_editar = null;
if (isset($_GET['acao']) && $_GET['acao'] === 'editar' && isset($_GET['id'])) {
    $id_editar = (int)$_GET['id'];
    $stmtEd = $pdo->prepare("SELECT * FROM compras WHERE id = ? AND empresa_id = ?");
    $stmtEd->execute([$id_editar, $empresa_id]);
    $compra_editar = $stmtEd->fetch();
}

$stmtProd = $pdo->prepare("SELECT id, nome, preco_custo, estoque FROM produtos WHERE empresa_id = ? AND ativo = TRUE ORDER BY nome ASC");
$stmtProd->execute([$empresa_id]);
$produtos = $stmtProd->fetchAll();

// Histórico de Compras
$stmtHist = $pdo->prepare("
    SELECT c.*, p.nome as produto_nome
    FROM compras c
    JOIN produtos p ON c.produto_id = p.id
    WHERE c.empresa_id = ?
    ORDER BY c.id DESC LIMIT 15
");
$stmtHist->execute([$empresa_id]);
$historico_compras = $stmtHist->fetchAll();
?>

<header class="header">
  <div>
    <h2><?= $compra_editar ? 'Editar compra' : 'Nova compra' ?></h2>
    <p style="color: #94a3b8; font-size: 14px;">Entrada de estoque e recálculo do custo médio ponderado</p>
  </div>
</header>

<?= $mensagem ?>

<div class="table-container" style="margin-bottom: 30px; max-width: 650px;">
  <h3 style="margin-bottom: 15px;">
    <?= $compra_editar ? '✏️ Editar Lançamento de Compra' : '+ Registrar Entrada de Estoque' ?>
  </h3>

  <form method="POST" action="index.php?page=compras">
    <?php if ($compra_editar): ?>
      <input type="hidden" name="id_compra" value="<?= $compra_editar['id'] ?>">
    <?php endif; ?>

    <div style="margin-bottom: 15px;">
      <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Produto que está entrando</label>
      <select name="produto_id" id="compra_produto" required onchange="carregarDadosProduto()" style="width: 100%; padding: 10px;">
        <option value="">-- Escolha um produto --</option>
        <?php foreach ($produtos as $p): ?>
          <option value="<?= $p['id'] ?>" 
                  data-custo="<?= $p['preco_custo'] ?>" 
                  data-estoque="<?= $p['estoque'] ?>" 
                  <?= ($compra_editar && $compra_editar['produto_id'] == $p['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['nome']) ?> (Estoque atual: <?= $p['estoque'] ?> un.)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="display: flex; gap: 15px; margin-bottom: 15px;">
      <div style="flex: 1;">
        <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Quantidade</label>
        <input type="number" name="quantidade" id="compra_qtd" value="<?= $compra_editar ? $compra_editar['quantidade'] : '1' ?>" min="1" required oninput="calcularPreviaCustoMedio()" style="width: 100%; padding: 10px;">
      </div>

      <div style="flex: 1;">
        <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Custo por Un. (R$)</label>
        <input type="number" step="0.01" name="custo_unitario" id="compra_custo" value="<?= $compra_editar ? $compra_editar['custo_unitario'] : '' ?>" required oninput="calcularPreviaCustoMedio()" style="width: 100%; padding: 10px;">
      </div>

      <div style="flex: 1;">
        <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Frete Total (R$)</label>
        <input type="number" step="0.01" name="frete" id="compra_frete" value="<?= $compra_editar ? $compra_editar['frete'] : '0.00' ?>" oninput="calcularPreviaCustoMedio()" style="width: 100%; padding: 10px;">
      </div>
    </div>

    <div style="background: #09090b; border: 1px solid #18181b; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; font-weight: 500;">
      Novo custo médio estimado: <span class="text-positive" id="custo_medio_preview">R$ 0,00</span>
    </div>

    <div style="display: flex; gap: 10px;">
      <button type="submit" name="salvar_compra" style="padding: 12px 24px; cursor: pointer; flex: 1;">
        <?= $compra_editar ? 'Atualizar Compra' : 'Confirmar compra' ?>
      </button>

      <?php if ($compra_editar): ?>
        <a href="index.php?page=compras" style="background: #18181b; color: #cbd5e1; text-decoration: none; padding: 12px 20px; border-radius: 6px; font-size: 14px; border: 1px solid #27272a;">Cancelar</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="table-container">
  <h3>Histórico de Compras</h3>
  <table>
    <thead>
      <tr>
        <th>Data</th>
        <th>Produto</th>
        <th>Qtd. Entrada</th>
        <th>Custo Un.</th>
        <th>Frete</th>
        <th>Custo Remessa</th>
        <th style="text-align: center;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($historico_compras) > 0): ?>
        <?php foreach ($historico_compras as $c): ?>
          <tr>
            <td><?= date('d/m/Y H:i', strtotime($c['data_compra'])) ?></td>
            <td><strong><?= htmlspecialchars($c['produto_nome']) ?></strong></td>
            <td>+<?= $c['quantidade'] ?> un.</td>
            <td>R$ <?= number_format($c['custo_unitario'], 2, ',', '.') ?></td>
            <td>R$ <?= number_format($c['frete'], 2, ',', '.') ?></td>
            <td><strong>R$ <?= number_format($c['custo_real_unitario'], 2, ',', '.') ?></strong></td>
            <td style="text-align: center;">
              <a href="index.php?page=compras&acao=editar&id=<?= $c['id'] ?>" title="Editar Compra" style="text-decoration: none; margin-right: 10px;">✏️</a>
              <a href="#" onclick="confirmarAcao(event, 'index.php?page=compras&acao=deletar&id=<?= $c['id'] ?>', 'Cancelar esta compra?', 'O estoque adicionado por ela será removido.')" title="Cancelar Compra" style="text-decoration: none;">🗑️</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" style="color: #94a3b8; text-align: center;">Nenhuma compra registrada ainda.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
let estoqueAtual = 0;
let custoAtual = 0;

function carregarDadosProduto() {
  const select = document.getElementById('compra_produto');
  const option = select.options[select.selectedIndex];
  
  custoAtual = parseFloat(option.getAttribute('data-custo')) || 0;
  estoqueAtual = parseInt(option.getAttribute('data-estoque')) || 0;

  if (custoAtual > 0 && !document.getElementById('compra_custo').value) {
    document.getElementById('compra_custo').value = custoAtual;
  }

  calcularPreviaCustoMedio();
}

function calcularPreviaCustoMedio() {
  const qtdNova = parseInt(document.getElementById('compra_qtd').value) || 0;
  const custoNovo = parseFloat(document.getElementById('compra_custo').value) || 0;
  const frete = parseFloat(document.getElementById('compra_frete').value) || 0;

  const valorTotalAntigo = estoqueAtual * custoAtual;
  const valorTotalNovo = (qtdNova * custoNovo) + frete;
  const qtdTotal = estoqueAtual + qtdNova;

  const novoCustoMedio = qtdTotal > 0 ? (valorTotalAntigo + valorTotalNovo) / qtdTotal : 0;
  
  document.getElementById('custo_medio_preview').innerText = 'R$ ' + novoCustoMedio.toFixed(2).replace('.', ',');
}
</script>