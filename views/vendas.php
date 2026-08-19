<?php
$mensagem = '';
$empresa_id = $_SESSION['empresa_id'];

// 1. Processar Ação de EXCLUIR Venda
if (isset($_GET['acao']) && $_GET['acao'] === 'deletar' && isset($_GET['id'])) {
    $id_deletar = (int)$_GET['id'];

    $stmtVendaOld = $pdo->prepare("SELECT produto_id, quantidade FROM vendas WHERE id = ? AND empresa_id = ?");
    $stmtVendaOld->execute([$id_deletar, $empresa_id]);
    $vendaAntiga = $stmtVendaOld->fetch();

    if ($vendaAntiga) {
        $pdo->beginTransaction();
        try {
            $stmtDevolv = $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ? AND empresa_id = ?");
            $stmtDevolv->execute([$vendaAntiga['quantidade'], $vendaAntiga['produto_id'], $empresa_id]);

            $stmtDel = $pdo->prepare("DELETE FROM vendas WHERE id = ? AND empresa_id = ?");
            $stmtDel->execute([$id_deletar, $empresa_id]);

            $pdo->commit();
            $mensagem = '<p style="color: #ef4444; margin-bottom: 20px;">🗑️ Venda cancelada e quantidade devolvida ao estoque!</p>';
        } catch (Exception $e) {
            $pdo->rollBack();
            $mensagem = '<p style="color: #ef4444; margin-bottom: 20px;">⚠️ Erro ao cancelar venda: ' . $e->getMessage() . '</p>';
        }
    }
}

// 2. CADASTRO ou EDIÇÃO de Venda
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_venda'])) {
    $id_venda = isset($_POST['id_venda']) ? (int)$_POST['id_venda'] : 0;
    $produto_id = (int)$_POST['produto_id'];
    $canal = trim($_POST['canal']);
    $forma_pagamento = $_POST['forma_pagamento'];
    $quantidade = (int)$_POST['quantidade'];
    $preco_venda = (float)$_POST['preco_venda'];
    $taxas_e_frete = (float)$_POST['taxas_e_frete'];

    $stmtP = $pdo->prepare("SELECT preco_custo, estoque, nome FROM produtos WHERE id = ? AND empresa_id = ?");
    $stmtP->execute([$produto_id, $empresa_id]);
    $produto = $stmtP->fetch();

    if ($produto) {
        $custo_unitario = (float)$produto['preco_custo'];
        
        if ($id_venda > 0) {
            $stmtVOld = $pdo->prepare("SELECT produto_id, quantidade FROM vendas WHERE id = ? AND empresa_id = ?");
            $stmtVOld->execute([$id_venda, $empresa_id]);
            $vendaAntiga = $stmtVOld->fetch();

            $estoque_disponivel = $produto['estoque'] + ($vendaAntiga ? $vendaAntiga['quantidade'] : 0);

            if ($estoque_disponivel < $quantidade) {
                $mensagem = '<p style="color: #ef4444; margin-bottom: 20px;">⚠️ Estoque insuficiente! Disponível para ajuste: ' . $estoque_disponivel . ' un.</p>';
            } else {
                $valor_total = $preco_venda * $quantidade;
                $custo_total = $custo_unitario * $quantidade;
                $lucro_liquido = $valor_total - $custo_total - $taxas_e_frete;

                $pdo->beginTransaction();
                try {
                    if ($vendaAntiga) {
                        $stmtDevolv = $pdo->prepare("UPDATE produtos SET estoque = estoque + ? WHERE id = ? AND empresa_id = ?");
                        $stmtDevolv->execute([$vendaAntiga['quantidade'], $vendaAntiga['produto_id'], $empresa_id]);
                    }

                    $stmtUp = $pdo->prepare("UPDATE vendas SET produto_id = ?, canal = ?, forma_pagamento = ?, quantidade = ?, preco_venda = ?, taxas_e_frete = ?, custo_produto = ?, lucro_liquido = ?, valor_total = ? WHERE id = ? AND empresa_id = ?");
                    $stmtUp->execute([$produto_id, $canal, $forma_pagamento, $quantidade, $preco_venda, $taxas_e_frete, $custo_total, $lucro_liquido, $valor_total, $id_venda, $empresa_id]);

                    $stmtAbate = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND empresa_id = ?");
                    $stmtAbate->execute([$quantidade, $produto_id, $empresa_id]);

                    $pdo->commit();
                    $mensagem = '<p style="color: #10b981; margin-bottom: 20px;">✏️ Venda atualizada com sucesso!</p>';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $mensagem = '<p style="color: #ef4444; margin-bottom: 20px;">⚠️ Erro ao atualizar venda.</p>';
                }
            }
        } else {
            if ($produto['estoque'] < $quantidade) {
                $mensagem = '<p style="color: #ef4444; margin-bottom: 20px;">⚠️ Estoque insuficiente! Estoque atual: ' . $produto['estoque'] . ' un.</p>';
            } else {
                $valor_total = $preco_venda * $quantidade;
                $custo_total = $custo_unitario * $quantidade;
                $lucro_liquido = $valor_total - $custo_total - $taxas_e_frete;

                $pdo->beginTransaction();
                try {
                    $stmtV = $pdo->prepare("INSERT INTO vendas (empresa_id, produto_id, canal, forma_pagamento, quantidade, preco_venda, taxas_e_frete, custo_produto, lucro_liquido, valor_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtV->execute([$empresa_id, $produto_id, $canal, $forma_pagamento, $quantidade, $preco_venda, $taxas_e_frete, $custo_total, $lucro_liquido, $valor_total]);

                    $stmtE = $pdo->prepare("UPDATE produtos SET estoque = estoque - ? WHERE id = ? AND empresa_id = ?");
                    $stmtE->execute([$quantidade, $produto_id, $empresa_id]);

                    $pdo->commit();
                    $mensagem = '<p style="color: #10b981; margin-bottom: 20px;">✅ Venda registrada com sucesso! Lucro gerado: R$ ' . number_format($lucro_liquido, 2, ',', '.') . '</p>';
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $mensagem = '<p style="color: #ef4444; margin-bottom: 20px;">⚠️ Erro ao registrar venda.</p>';
                }
            }
        }
    }
}

// 3. Edição
$venda_editar = null;
if (isset($_GET['acao']) && $_GET['acao'] === 'editar' && isset($_GET['id'])) {
    $id_editar = (int)$_GET['id'];
    $stmtEd = $pdo->prepare("SELECT * FROM vendas WHERE id = ? AND empresa_id = ?");
    $stmtEd->execute([$id_editar, $empresa_id]);
    $venda_editar = $stmtEd->fetch();
}

$stmtProd = $pdo->prepare("SELECT id, nome, fornecedor, preco_venda, estoque FROM produtos WHERE empresa_id = ? AND ativo = TRUE ORDER BY nome ASC");
$stmtProd->execute([$empresa_id]);
$produtos = $stmtProd->fetchAll();

// Filtro
$canal_filtro = trim($_GET['canal_filtro'] ?? '');
$paramsVendas = [$empresa_id];
$sql_canal = "";

if (!empty($canal_filtro)) {
    $sql_canal = " AND v.canal = ? ";
    $paramsVendas[] = $canal_filtro;
}

$stmtVendas = $pdo->prepare("
    SELECT v.*, p.nome as produto_nome, p.fornecedor as produto_fornecedor
    FROM vendas v 
    JOIN produtos p ON v.produto_id = p.id 
    WHERE v.empresa_id = ? {$sql_canal}
    ORDER BY v.id DESC LIMIT 20
");
$stmtVendas->execute($paramsVendas);
$historico_vendas = $stmtVendas->fetchAll();
?>

<header class="header">
  <div>
    <h2><?= $venda_editar ? 'Editar venda' : 'Nova venda' ?></h2>
    <p style="color: #94a3b8; font-size: 14px;">Controle de vendas com recálculo automático de estoque e margem</p>
  </div>
</header>

<?= $mensagem ?>

<div class="table-container" style="margin-bottom: 30px; max-width: 650px;">
  <h3 style="margin-bottom: 15px;">
    <?= $venda_editar ? '✏️ Alterar Dados da Venda' : '+ Lançar Venda' ?>
  </h3>

  <form method="POST" action="index.php?page=vendas">
    <?php if ($venda_editar): ?>
      <input type="hidden" name="id_venda" value="<?= $venda_editar['id'] ?>">
    <?php endif; ?>

    <div style="margin-bottom: 15px;">
      <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Selecione o Produto</label>
      <select name="produto_id" id="select_produto" required onchange="atualizarDadosProduto()" style="width: 100%; padding: 10px;">
        <option value="">-- Escolha um produto --</option>
        <?php foreach ($produtos as $p): ?>
          <?php $info_forn = !empty($p['fornecedor']) ? " | Forn: {$p['fornecedor']}" : ""; ?>
          <option value="<?= $p['id'] ?>" data-preco="<?= $p['preco_venda'] ?>" <?= ($venda_editar && $venda_editar['produto_id'] == $p['id']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['nome']) ?><?= htmlspecialchars($info_forn) ?> (Estoque: <?= $p['estoque'] ?> un.)
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div style="display: flex; gap: 15px; margin-bottom: 15px;">
      <div style="flex: 1;">
        <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Canal de Venda</label>
        <select name="canal" style="width: 100%; padding: 10px;">
          <option value="WhatsApp" <?= ($venda_editar && $venda_editar['canal'] == 'WhatsApp') ? 'selected' : '' ?>>WhatsApp</option>
          <option value="Instagram" <?= ($venda_editar && $venda_editar['canal'] == 'Instagram') ? 'selected' : '' ?>>Instagram</option>
          <option value="Loja Física" <?= ($venda_editar && $venda_editar['canal'] == 'Loja Física') ? 'selected' : '' ?>>Loja Física</option>
          <option value="Site" <?= ($venda_editar && $venda_editar['canal'] == 'Site') ? 'selected' : '' ?>>Site / E-commerce</option>
        </select>
      </div>

      <div style="flex: 1;">
        <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Forma de Pagamento</label>
        <select name="forma_pagamento" style="width: 100%; padding: 10px;">
          <option value="pix" <?= ($venda_editar && $venda_editar['forma_pagamento'] == 'pix') ? 'selected' : '' ?>>Pix</option>
          <option value="cartao_credito" <?= ($venda_editar && $venda_editar['forma_pagamento'] == 'cartao_credito') ? 'selected' : '' ?>>Cartão de Crédito</option>
          <option value="cartao_debito" <?= ($venda_editar && $venda_editar['forma_pagamento'] == 'cartao_debito') ? 'selected' : '' ?>>Cartão de Débito</option>
          <option value="dinheiro" <?= ($venda_editar && $venda_editar['forma_pagamento'] == 'dinheiro') ? 'selected' : '' ?>>Dinheiro</option>
        </select>
      </div>
    </div>

    <div style="display: flex; gap: 15px; margin-bottom: 20px;">
      <div style="flex: 1;">
        <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Qtd.</label>
        <input type="number" name="quantidade" id="venda_qtd" value="<?= $venda_editar ? $venda_editar['quantidade'] : '1' ?>" min="1" required style="width: 100%; padding: 10px;">
      </div>

      <div style="flex: 1;">
        <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Preço Un. (R$)</label>
        <input type="number" step="0.01" name="preco_venda" id="venda_preco" value="<?= $venda_editar ? $venda_editar['preco_venda'] : '' ?>" required style="width: 100%; padding: 10px;">
      </div>

      <div style="flex: 1;">
        <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Taxas/Frete (R$)</label>
        <input type="number" step="0.01" name="taxas_e_frete" value="<?= $venda_editar ? $venda_editar['taxas_e_frete'] : '0.00' ?>" style="width: 100%; padding: 10px;">
      </div>
    </div>

    <div style="display: flex; gap: 10px;">
      <button type="submit" name="salvar_venda" style="padding: 12px 24px; cursor: pointer; flex: 1;">
        <?= $venda_editar ? 'Atualizar Venda' : 'Confirmar Venda' ?>
      </button>
      
      <?php if ($venda_editar): ?>
        <a href="index.php?page=vendas" style="background: #18181b; color: #cbd5e1; text-decoration: none; padding: 12px 20px; border-radius: 6px; font-size: 14px; border: 1px solid #27272a;">Cancelar</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="table-container">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
    <h3>Últimas Vendas</h3>

    <form method="GET" action="index.php" style="display: flex; gap: 8px;">
      <input type="hidden" name="page" value="vendas">
      <select name="canal_filtro" onchange="this.form.submit()" style="padding: 8px 12px; font-size: 13px;">
        <option value="">-- Todos os Canais --</option>
        <option value="WhatsApp" <?= $canal_filtro == 'WhatsApp' ? 'selected' : '' ?>>WhatsApp</option>
        <option value="Instagram" <?= $canal_filtro == 'Instagram' ? 'selected' : '' ?>>Instagram</option>
        <option value="Loja Física" <?= $canal_filtro == 'Loja Física' ? 'selected' : '' ?>>Loja Física</option>
        <option value="Site" <?= $canal_filtro == 'Site' ? 'selected' : '' ?>>Site / E-commerce</option>
      </select>
    </form>
  </div>

  <table>
    <thead>
      <tr>
        <th>Data</th>
        <th>Produto</th>
        <th>Fornecedor</th>
        <th>Canal</th>
        <th>Pagamento</th>
        <th>Total</th>
        <th>Lucro Líquido</th>
        <th style="text-align: center;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($historico_vendas) > 0): ?>
        <?php foreach ($historico_vendas as $v): ?>
          <tr>
            <td><?= date('d/m/Y H:i', strtotime($v['data_venda'])) ?></td>
            <td><strong><?= htmlspecialchars($v['produto_nome']) ?></strong> (<?= $v['quantidade'] ?>x)</td>
            <td style="color: #a1a1aa;"><?= !empty($v['produto_fornecedor']) ? htmlspecialchars($v['produto_fornecedor']) : '<span style="color:#52525b;">—</span>' ?></td>
            <td><?= htmlspecialchars($v['canal']) ?></td>
            <td><?= strtoupper($v['forma_pagamento']) ?></td>
            <td>R$ <?= number_format($v['valor_total'], 2, ',', '.') ?></td>
            <td class="text-positive"><strong>R$ <?= number_format($v['lucro_liquido'], 2, ',', '.') ?></strong></td>
            <td style="text-align: center;">
              <a href="index.php?page=vendas&acao=editar&id=<?= $v['id'] ?>" title="Editar Venda" style="text-decoration: none; margin-right: 10px;">✏️</a>
              <a href="#" onclick="confirmarAcao(event, 'index.php?page=vendas&acao=deletar&id=<?= $v['id'] ?>', 'Cancelar esta venda?', 'A quantidade vendida retornará ao estoque.')" title="Cancelar Venda" style="text-decoration: none;">🗑️</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="8" style="color: #94a3b8; text-align: center;">Nenhuma venda encontrada para este filtro.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<script>
function atualizarDadosProduto() {
  const select = document.getElementById('select_produto');
  const option = select.options[select.selectedIndex];
  const preco = option.getAttribute('data-preco');
  if (preco && !document.getElementById('venda_preco').value) {
    document.getElementById('venda_preco').value = preco;
  }
}
</script>