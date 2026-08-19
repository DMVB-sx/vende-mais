<?php
$mensagem = '';
$empresa_id = $_SESSION['empresa_id'];

// 1. Marcar como Pago ou Excluir
if (isset($_GET['acao'])) {
    $id_despesa = (int)($_GET['id'] ?? 0);
    
    if ($_GET['acao'] === 'pagar' && $id_despesa > 0) {
        $stmtPagar = $pdo->prepare("UPDATE despesas SET pago = TRUE WHERE id = ? AND empresa_id = ?");
        $stmtPagar->execute([$id_despesa, $empresa_id]);
        $mensagem = '<p style="color: #10b981; margin-bottom: 20px;">✅ Despesa marcada como PAGA!</p>';
    } elseif ($_GET['acao'] === 'deletar' && $id_despesa > 0) {
        $stmtDel = $pdo->prepare("DELETE FROM despesas WHERE id = ? AND empresa_id = ?");
        $stmtDel->execute([$id_despesa, $empresa_id]);
        $mensagem = '<p style="color: #ef4444; margin-bottom: 20px;">🗑️ Despesa removida com sucesso!</p>';
    }
}

// 2. Cadastro
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cadastrar_despesa'])) {
    $descricao = trim($_POST['descricao']);
    $categoria = trim($_POST['categoria']);
    $valor = (float)$_POST['valor'];
    // Tratamento de vencimento opcional: envia NULL se estiver vazio
    $data_vencimento = !empty($_POST['data_vencimento']) ? $_POST['data_vencimento'] : null;
    $pago = isset($_POST['pago']) ? 1 : 0;

    if (!empty($descricao) && $valor > 0) {
        $stmtInst = $pdo->prepare("INSERT INTO despesas (empresa_id, descricao, categoria, valor, data_vencimento, pago) VALUES (?, ?, ?, ?, ?, ?)");
        $stmtInst->execute([$empresa_id, $descricao, $categoria, $valor, $data_vencimento, $pago]);
        $mensagem = '<p style="color: #10b981; margin-bottom: 20px;">✅ Despesa cadastrada com sucesso!</p>';
    } else {
        $mensagem = '<p style="color: #ef4444; margin-bottom: 20px;">⚠️ Preencha a descrição e o valor corretamente.</p>';
    }
}

// 3. Listagem (Ordena pelos mais recentes)
$stmtDespesas = $pdo->prepare("SELECT * FROM despesas WHERE empresa_id = ? ORDER BY id DESC");
$stmtDespesas->execute([$empresa_id]);
$despesas = $stmtDespesas->fetchAll();
?>

<header class="header">
  <div>
    <h2>Despesas & Custos Fixos</h2>
    <p style="color: #94a3b8; font-size: 14px;">Controle contas a pagar, aluguel, marketing e custos operacionais</p>
  </div>
</header>

<?= $mensagem ?>

<div class="table-container" style="margin-bottom: 30px; max-width: 700px;">
  <h3 style="margin-bottom: 15px;">+ Nova Despesa</h3>
  <form method="POST" action="index.php?page=despesas" style="display: flex; gap: 15px; flex-wrap: wrap; align-items: flex-end;">
    
    <div style="flex: 2; min-width: 200px;">
      <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Descrição *</label>
      <input type="text" name="descricao" required placeholder="Ex: Embalagens, Luz, Anúncios" style="width: 100%; padding: 10px;">
    </div>

    <div style="flex: 1; min-width: 130px;">
      <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Categoria</label>
      <select name="categoria" style="width: 100%; padding: 10px;">
        <option value="Operacional">Operacional</option>
        <option value="Marketing">Marketing/Anúncios</option>
        <option value="Infraestrutura">Infraestrutura/Aluguel</option>
        <option value="Impostos">Impostos/Taxas</option>
        <option value="Outros">Outros</option>
      </select>
    </div>

    <div style="flex: 1; min-width: 120px;">
      <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Valor (R$) *</label>
      <input type="number" step="0.01" name="valor" required placeholder="0,00" style="width: 100%; padding: 10px;">
    </div>

    <div style="flex: 1; min-width: 130px;">
      <label style="display: block; font-size: 13px; color: #94a3b8; margin-bottom: 5px;">Vencimento (Opcional)</label>
      <input type="date" name="data_vencimento" style="width: 100%; padding: 10px;">
    </div>

    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 12px; min-width: 100%;">
      <input type="checkbox" name="pago" id="pago" value="1" checked style="width: 16px; height: 16px;">
      <label for="pago" style="font-size: 13px; color: #e2e8f0; cursor: pointer;">Já foi pago?</label>
    </div>

    <button type="submit" name="cadastrar_despesa" style="padding: 11px 20px; cursor: pointer; width: 100%;">Salvar Despesa</button>
  </form>
</div>

<div class="table-container">
  <h3>Histórico de Despesas</h3>
  <table>
    <thead>
      <tr>
        <th>Vencimento</th>
        <th>Descrição</th>
        <th>Categoria</th>
        <th>Valor</th>
        <th>Status</th>
        <th style="text-align: center;">Ações</th>
      </tr>
    </thead>
    <tbody>
      <?php if (count($despesas) > 0): ?>
        <?php foreach ($despesas as $d): ?>
          <tr>
            <td>
              <?= !empty($d['data_vencimento']) ? date('d/m/Y', strtotime($d['data_vencimento'])) : '<span style="color: #71717a;">—</span>' ?>
            </td>
            <td><strong><?= htmlspecialchars($d['descricao']) ?></strong></td>
            <td><?= htmlspecialchars($d['categoria']) ?></td>
            <td style="color: #ef4444;"><strong>R$ <?= number_format($d['valor'], 2, ',', '.') ?></strong></td>
            <td>
              <?php if ($d['pago']): ?>
                <span style="background: #064e3b; color: #34d399; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">PAGO</span>
              <?php else: ?>
                <span style="background: #7f1d1d; color: #fca5a5; padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold;">PENDENTE</span>
              <?php endif; ?>
            </td>
            <td style="text-align: center;">
              <?php if (!$d['pago']): ?>
                <a href="index.php?page=despesas&acao=pagar&id=<?= $d['id'] ?>" title="Marcar como Pago" style="text-decoration: none; margin-right: 10px;">✅</a>
              <?php endif; ?>
              <a href="#" onclick="confirmarAcao(event, 'index.php?page=despesas&acao=deletar&id=<?= $d['id'] ?>', 'Excluir despesa?', 'O registro dessa despesa será apagado do sistema.')" title="Excluir" style="text-decoration: none;">🗑️</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php else: ?>
        <tr>
          <td colspan="6" style="color: #94a3b8; text-align: center;">Nenhuma despesa registrada ainda.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>