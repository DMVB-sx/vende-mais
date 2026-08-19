<?php
$mensagem = '';
$empresa_id = $_SESSION['empresa_id'];

// 1. Excluir / Desativar Produto
if (isset($_GET['acao']) && $_GET['acao'] === 'deletar' && isset($_GET['id'])) {
    $id_deletar = (int)$_GET['id'];

    $stmtCheckVendas = $pdo->prepare("SELECT COUNT(*) as total FROM vendas WHERE produto_id = ? AND empresa_id = ?");
    $stmtCheckVendas->execute([$id_deletar, $empresa_id]);
    $temVendas = $stmtCheckVendas->fetch()['total'] > 0;

    $stmtCheckCompras = $pdo->prepare("SELECT COUNT(*) as total FROM compras WHERE produto_id = ? AND empresa_id = ?");
    $stmtCheckCompras->execute([$id_deletar, $empresa_id]);
    $temCompras = $stmtCheckCompras->fetch()['total'] > 0;

    if ($temVendas || $temCompras) {
        $stmtInativar = $pdo->prepare("UPDATE produtos SET ativo = FALSE WHERE id = ? AND empresa_id = ?");
        $stmtInativar->execute([$id_deletar, $empresa_id]);
        $mensagem = '<p style="color: #f59e0b; margin-bottom: 20px;">⚠️ Produto arquivado para preservar os relatórios!</p>';
    } else {
        $stmtDel = $pdo->prepare("DELETE FROM produtos WHERE id = ? AND empresa_id = ?");
        $stmtDel->execute([$id_deletar, $empresa_id]);
        $mensagem = '<p style="color: #ef4444; margin-bottom: 20px;">🗑️ Produto removido com sucesso!</p>';
    }
}

// 2. Salvar Produto
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_produto'])) {
    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $nome = trim($_POST['nome']);
    $fornecedor = trim($_POST['fornecedor'] ?? '');
    $preco_custo = (float)$_POST['preco_custo'];
    $preco_venda = (float)$_POST['preco_venda'];
    $estoque = (int)$_POST['estoque'];
    $alerta_estoque = isset($_POST['alerta_estoque']) ? 1 : 0;

    if (!empty($nome)) {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE produtos SET nome = ?, fornecedor = ?, preco_custo = ?, preco_venda = ?, estoque = ?, alerta_estoque = ? WHERE id = ? AND empresa_id = ?");
            $stmt->execute([$nome, $fornecedor, $preco_custo, $preco_venda, $estoque, $alerta_estoque, $id, $empresa_id]);
            $mensagem = '<p style="color: #10b981; margin-bottom: 20px;">✏️ Produto atualizado com sucesso!</p>';
        } else {
            $stmt = $pdo->prepare("INSERT INTO produtos (empresa_id, nome, fornecedor, preco_custo, preco_venda, estoque, alerta_estoque) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$empresa_id, $nome, $fornecedor, $preco_custo, $preco_venda, $estoque, $alerta_estoque]);
            $mensagem = '<p style="color: #10b981; margin-bottom: 20px;">✅ Produto cadastrado com sucesso!</p>';
        }
    }
}

// 3. Edição
$produto_editar = null;
if (isset($_GET['acao']) && $_GET['acao'] === 'editar' && isset($_GET['id'])) {
    $id_editar = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ? AND empresa_id = ?");
    $stmt->execute([$id_editar, $empresa_id]);
    $produto_editar = $stmt->fetch();
}

// 4. Busca
$busca = trim($_GET['busca'] ?? '');
$params = [$empresa_id];
$sql_busca = "";

if (!empty($busca)) {
    $sql_busca = " AND (nome LIKE ? OR fornecedor LIKE ?) ";
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
}

$stmt = $pdo->prepare("SELECT * FROM produtos WHERE empresa_id = ? AND ativo = TRUE {$sql_busca} ORDER BY id DESC");
$stmt->execute($params);
$produtos = $stmt->fetchAll();
?>

<header class="header">
  <div>
    <h2>Produtos</h2>
    <p style="color: #94a3b8; font-size: 14px;">Catálogo, fornecedores, custos e controle de estoque</p>
  </div>
</header>

<?= $mensagem ?>

<div class="table-container" style="margin-bottom: 30px;">
  <h3 style="margin-bottom: 15px;">
    <?= $produto_editar ? '✏️ Editar Produto' : '+ Novo Produto' ?>
  </h3>
  
  <form method="POST" action="index.php?page=produtos">
    <?php if ($produto_editar): ?>
      <input type="hidden" name="id" value="<?= $produto_editar['id'] ?>">
    <?php endif; ?>

    <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-bottom: 15px;">
      <div style="flex: 2; min-width: 200px;">
        <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Nome do Produto</label>
        <input type="text" name="nome" value="<?= $produto_editar ? htmlspecialchars($produto_editar['nome']) : '' ?>" required style="width: 100%; padding: 10px;">
      </div>

      <div style="flex: 1.5; min-width: 160px;">
        <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Fornecedor <span style="color: #71717a; font-size: 11px;">(Opcional)</span></label>
        <input type="text" name="fornecedor" placeholder="Ex: Mercado Livre, Shopee" value="<?= $produto_editar ? htmlspecialchars($produto_editar['fornecedor'] ?? '') : '' ?>" style="width: 100%; padding: 10px;">
      </div>

      <div style="flex: 1; min-width: 110px;">
        <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Custo (R$)</label>
        <input type="number" step="0.01" name="preco_custo" value="<?= $produto_editar ? $produto_editar['preco_custo'] : '' ?>" required style="width: 100%; padding: 10px;">
      </div>

      <div style="flex: 1; min-width: 110px;">
        <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Venda (R$)</label>
        <input type="number" step="0.01" name="preco_venda" value="<?= $produto_editar ? $produto_editar['preco_venda'] : '' ?>" required style="width: 100%; padding: 10px;">
      </div>

      <div style="flex: 1; min-width: 100px;">
        <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Estoque</label>
        <input type="number" name="estoque" value="<?= $produto_editar ? $produto_editar['estoque'] : '0' ?>" required style="width: 100%; padding: 10px;">
      </div>
    </div>

    <!-- Checkbox simples de ligar/desligar o aviso -->
    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 18px;">
      <?php 
        $marcado = (!$produto_editar || (isset($produto_editar['alerta_estoque']) && $produto_editar['alerta_estoque'])) ? 'checked' : ''; 
      ?>
      <input type="checkbox" name="alerta_estoque" id="alerta_estoque" value="1" <?= $marcado ?> style="width: 16px; height: 16px; cursor: pointer;">
      <label for="alerta_estoque" style="font-size: 13.5px; color: #cbd5e1; cursor: pointer;">
        Avisar no painel quando este produto estiver com estoque baixo (≤ 3 un.)
      </label>
    </div>

    <div style="display: flex; gap: 10px;">
      <button type="submit" name="salvar_produto" style="padding: 11px 24px; cursor: pointer;">
        <?= $produto_editar ? 'Atualizar Produto' : 'Salvar Produto' ?>
      </button>

      <?php if ($produto_editar): ?>
        <a href="index.php?page=produtos" style="background: #18181b; color: #cbd5e1; text-decoration: none; padding: 11px 16px; border-radius: 6px; font-size: 14px; border: 1px solid #27272a;">Cancelar</a>
      <?php endif; ?>
    </div>
  </form>
</div>

<div class="table-container">
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
    <h3>Produtos Cadastrados</h3>
    
    <form method="GET" action="index.php" style="display: flex; gap: 8px;">
      <input type="hidden" name="page" value="produtos">
      <input type="text" name="busca" placeholder="🔍 Buscar..." value="<?= htmlspecialchars($busca) ?>" style="padding: 8px 12px; font-size: 13px;">
      <button type="submit" style="padding: 8px 12px; cursor: pointer;">Buscar</button>
      <?php if (!empty($busca)): ?>
        <a href="index.php?page=produtos" style="color: #ef4444; text-decoration: none; font-size: 13px; align-self: center;">Limpar</a>
      <?php endif; ?>
    </form>
  </div>

  <table>
    <thead>
      <tr>
        <th>Produto</th>
        <th>Fornecedor</th>
        <th>Estoque</th>
        <th>Custo médio</th>
        <th>Preço sugerido</th>
        <th>Margem Est.</th>
        <th style="text-align: center;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($produtos) > 0): ?>
        <?php foreach ($produtos as $p): ?>
          <?php 
            $custo = (float)$p['preco_custo'];
            $venda = (float)$p['preco_venda'];
            $margem = $venda > 0 ? ((($venda - $custo) / $venda) * 100) : 0;
            $alerta_ativo = !isset($p['alerta_estoque']) || $p['alerta_estoque'];
            $em_alerta = ($alerta_ativo && $p['estoque'] <= 3);
            $alerta_estoque = $em_alerta ? 'color: #f59e0b; font-weight: bold;' : '';
          ?>
          <tr>
            <td><strong><?= htmlspecialchars($p['nome']) ?></strong></td>
            <td style="color: #a1a1aa;"><?= !empty($p['fornecedor']) ? htmlspecialchars($p['fornecedor']) : '<span style="color:#52525b;">—</span>' ?></td>
            <td style="<?= $alerta_estoque ?>">
              <?= $p['estoque'] ?> un. <?= $em_alerta ? '⚠️' : '' ?>
            </td>
            <td>R$ <?= number_format($custo, 2, ',', '.') ?></td>
            <td>R$ <?= number_format($venda, 2, ',', '.') ?></td>
            <td class="text-positive"><?= number_format($margem, 1, ',', '.') ?>%</td>
            <td style="text-align: center;">
              <a href="index.php?page=produtos&acao=editar&id=<?= $p['id'] ?>" title="Editar" style="text-decoration: none; margin-right: 10px;">✏️</a>
              <a href="#" onclick="confirmarAcao(event, 'index.php?page=produtos&acao=deletar&id=<?= $p['id'] ?>', 'Excluir produto?', 'O produto será removido ou arquivado do catálogo.')" title="Excluir" style="text-decoration: none;">🗑️</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="7" style="color: #94a3b8; text-align: center;">Nenhum produto encontrado.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>