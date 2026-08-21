<?php
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$empresa_id = $_SESSION['empresa_id'] ?? 0;
$mensagem = '';
$abaAtiva = 'aba-geral';

// 1. Processar Atualizações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    validar_csrf();

    // Atualizar Dados Gerais (Perfil + Empresa)
    if (isset($_POST['atualizar_geral'])) {
        $abaAtiva = 'aba-geral';
        $nome_usuario  = trim($_POST['nome_usuario'] ?? '');
        $email_usuario = trim($_POST['email_usuario'] ?? '');
        $nome_empresa  = trim($_POST['nome_empresa'] ?? '');
        $cnpj_cpf      = trim($_POST['cnpj_cpf'] ?? '');
        $telefone      = trim($_POST['telefone'] ?? '');

        if (!empty($nome_usuario) && !empty($email_usuario) && !empty($nome_empresa)) {
            try {
                // Checa e-mail duplicado
                $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmtCheck->execute([$email_usuario, $usuario_id]);
                
                if ($stmtCheck->rowCount() > 0) {
                    $mensagem = '<div class="alert error">⚠️ Este e-mail já está em uso por outro usuário.</div>';
                } else {
                    // 1. Atualiza Usuário
                    $stmtUser = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id = ?");
                    $stmtUser->execute([$nome_usuario, $email_usuario, $usuario_id]);
                    $_SESSION['usuario_nome'] = $nome_usuario;

                    // 2. Atualiza Empresa com detecção dinâmica de colunas
                    $colunasEmpresa = [];
                    $stmtCols = $pdo->query("DESCRIBE empresas");
                    while ($col = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
                        $colunasEmpresa[] = $col['Field'];
                    }

                    $camposUpdate = [];
                    $valoresUpdate = [];

                    // Nome da empresa
                    if (in_array('nome', $colunasEmpresa)) {
                        $camposUpdate[] = "nome = ?";
                        $valoresUpdate[] = $nome_empresa;
                    } elseif (in_array('nome_fantasia', $colunasEmpresa)) {
                        $camposUpdate[] = "nome_fantasia = ?";
                        $valoresUpdate[] = $nome_empresa;
                    }

                    // Documento (CNPJ/CPF)
                    if (in_array('cnpj_cpf', $colunasEmpresa)) {
                        $camposUpdate[] = "cnpj_cpf = ?";
                        $valoresUpdate[] = $cnpj_cpf;
                    } elseif (in_array('cnpj', $colunasEmpresa)) {
                        $camposUpdate[] = "cnpj = ?";
                        $valoresUpdate[] = $cnpj_cpf;
                    }

                    // Telefone
                    if (in_array('telefone', $colunasEmpresa)) {
                        $camposUpdate[] = "telefone = ?";
                        $valoresUpdate[] = $telefone;
                    }

                    if (!empty($camposUpdate)) {
                        $valoresUpdate[] = $empresa_id;
                        $sqlEmp = "UPDATE empresas SET " . implode(", ", $camposUpdate) . " WHERE id = ?";
                        $stmtEmp = $pdo->prepare($sqlEmp);
                        $stmtEmp->execute($valoresUpdate);
                    }

                    $_SESSION['empresa_nome'] = $nome_empresa;
                    $_SESSION['empresa_doc'] = $cnpj_cpf;
                    $mensagem = '<div class="alert success">✅ Dados salvos com sucesso!</div>';
                }
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $mensagem = '<div class="alert error">⚠️ Erro ao atualizar dados. Tente novamente.</div>';
            }
        } else {
            $mensagem = '<div class="alert error">⚠️ Preencha os campos obrigatórios (Nome, E-mail e Nome da Empresa).</div>';
        }
    }

    // Alterar Senha
    if (isset($_POST['alterar_senha'])) {
        $abaAtiva = 'aba-senha';
        $senha_atual    = $_POST['senha_atual'] ?? '';
        $nova_senha     = $_POST['nova_senha'] ?? '';
        $confirma_senha = $_POST['confirma_senha'] ?? '';

        try {
            $stmtUser = $pdo->prepare("SELECT senha FROM usuarios WHERE id = ?");
            $stmtUser->execute([$usuario_id]);
            $userPass = $stmtUser->fetch();

            if ($userPass && password_verify($senha_atual, $userPass['senha'])) {
                if ($nova_senha === $confirma_senha) {
                    if (strlen($nova_senha) >= 6) {
                        $nova_senha_hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                        $stmtPass = $pdo->prepare("UPDATE usuarios SET senha = ? WHERE id = ?");
                        $stmtPass->execute([$nova_senha_hash, $usuario_id]);
                        $mensagem = '<div class="alert success">✅ Senha alterada com sucesso!</div>';
                    } else {
                        $mensagem = '<div class="alert error">⚠️ A nova senha deve ter no mínimo 6 caracteres.</div>';
                    }
                } else {
                    $mensagem = '<div class="alert error">⚠️ A nova senha e a confirmação não coincidem.</div>';
                }
            } else {
                $mensagem = '<div class="alert error">⚠️ A senha atual informada está incorreta.</div>';
            }
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $mensagem = '<div class="alert error">⚠️ Erro ao atualizar senha. Tente novamente.</div>';
        }
    }
}

// 2. Buscar Dados Atuais
try {
    $stmtU = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
    $stmtU->execute([$usuario_id]);
    $usuario = $stmtU->fetch(PDO::FETCH_ASSOC) ?: ['nome' => '', 'email' => ''];
} catch (Throwable $e) {
    $usuario = ['nome' => '', 'email' => ''];
}

try {
    $stmtE = $pdo->prepare("SELECT * FROM empresas WHERE id = ?");
    $stmtE->execute([$empresa_id]);
    $empresa = $stmtE->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $empresa = [];
}

$nomeEmpresaAtual = $empresa['nome'] ?? $empresa['nome_fantasia'] ?? $empresa['razao_social'] ?? '';
$documentoAtual   = $empresa['cnpj_cpf'] ?? $empresa['cnpj'] ?? $empresa['cpf'] ?? '';
$telefoneAtual    = $empresa['telefone'] ?? '';
?>

<style>
.tab-nav {
    display: flex;
    gap: 10px;
    border-bottom: 1px solid #27272a;
    margin-bottom: 24px;
    padding-bottom: 8px;
}

.tab-btn {
    background: transparent;
    border: none;
    color: #71717a;
    font-size: 14px;
    font-weight: 500;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.tab-btn:hover {
    color: #ffffff;
    background: #18181b;
}

.tab-btn.active {
    color: #10b981;
    background: rgba(16, 185, 129, 0.1);
    font-weight: 600;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

.profile-card {
    background: #09090b;
    border: 1px solid #18181b;
    border-radius: 10px;
    padding: 24px;
    max-width: 580px;
}

.form-group {
    margin-bottom: 16px;
}

.form-group label {
    display: block;
    font-size: 13px;
    color: #a1a1aa;
    margin-bottom: 6px;
    font-weight: 500;
}

.form-group input {
    width: 100%;
    padding: 10px 14px;
    background: #000000;
    border: 1px solid #27272a;
    border-radius: 6px;
    color: #ffffff;
    font-size: 14px;
    outline: none;
    box-sizing: border-box;
    transition: border-color 0.2s;
}

.form-group input:focus:not([readonly]) {
    border-color: #10b981;
}

.form-group input[readonly] {
    background: #09090b;
    border-color: #18181b;
    color: #d4d4d8;
    cursor: default;
}

.section-divider {
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #10b981;
    font-weight: 700;
    margin: 20px 0 14px 0;
    border-bottom: 1px solid #18181b;
    padding-bottom: 6px;
}

.btn-primary {
    background: #10b981;
    color: #000000;
    font-weight: 600;
    border: none;
    padding: 10px 18px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13.5px;
    transition: all 0.2s;
}

.btn-primary:hover {
    background: #059669;
}

.btn-secondary {
    background: #18181b;
    color: #ffffff;
    border: 1px solid #27272a;
    padding: 10px 18px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 13.5px;
    font-weight: 500;
}

.btn-secondary:hover {
    background: #27272a;
}

.alert {
    padding: 12px 16px;
    border-radius: 6px;
    font-size: 13.5px;
    margin-bottom: 20px;
    max-width: 580px;
}

.alert.success {
    background: rgba(16, 185, 129, 0.1);
    border: 1px solid #059669;
    color: #34d399;
}

.alert.error {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid #dc2626;
    color: #f87171;
}
</style>

<header class="header">
  <div>
    <h2>⚙️ Minha Conta</h2>
    <p style="color: #a1a1aa; font-size: 13.5px; margin-top: 2px;">Gerencie suas informações e segurança da conta</p>
  </div>
</header>

<?= $mensagem ?>

<div class="tab-nav">
  <button class="tab-btn <?= ($abaAtiva === 'aba-geral') ? 'active' : '' ?>" onclick="abrirAba(event, 'aba-geral')">👤 Dados Gerais</button>
  <button class="tab-btn <?= ($abaAtiva === 'aba-senha') ? 'active' : '' ?>" onclick="abrirAba(event, 'aba-senha')">🔒 Segurança & Senha</button>
</div>

<!-- ABA ÚNICA: DADOS GERAIS -->
<div id="aba-geral" class="tab-pane <?= ($abaAtiva === 'aba-geral') ? 'active' : '' ?>">
  <div class="profile-card">
    <div style="margin-bottom: 15px;">
      <h3 style="font-size: 16px; margin: 0 0 4px 0;">Informações da Conta e Empresa</h3>
      <p style="color: #71717a; font-size: 13px; margin: 0;">Mantenha seus dados e do seu negócio atualizados.</p>
    </div>

    <form method="POST" id="form-geral">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="section-divider">Dados do Usuário</div>

      <div class="form-group">
        <label>Nome Completo *</label>
        <input type="text" name="nome_usuario" class="input-geral" value="<?= htmlspecialchars($usuario['nome']) ?>" readonly required>
      </div>

      <div class="form-group">
        <label>E-mail de Acesso *</label>
        <input type="email" name="email_usuario" class="input-geral" value="<?= htmlspecialchars($usuario['email']) ?>" readonly required>
      </div>

      <div class="section-divider">Dados do Negócio</div>

      <div class="form-group">
        <label>Nome da Empresa / Fantasia *</label>
        <input type="text" name="nome_empresa" class="input-geral" value="<?= htmlspecialchars($nomeEmpresaAtual) ?>" readonly required>
      </div>

      <div class="form-group">
        <label>CNPJ ou CPF (Opcional)</label>
        <input type="text" name="cnpj_cpf" id="campo_cnpj_cpf" class="input-geral" value="<?= htmlspecialchars($documentoAtual) ?>" readonly placeholder="00.000.000/0000-00" maxlength="18" oninput="mascaraCpfCnpj(this)">
      </div>

      <div class="form-group">
        <label>Telefone / WhatsApp (Opcional)</label>
        <input type="text" name="telefone" id="campo_telefone" class="input-geral" value="<?= htmlspecialchars($telefoneAtual) ?>" readonly placeholder="(00) 00000-0000" maxlength="15" oninput="mascaraTelefone(this)">
      </div>

      <div id="acoes-view" style="margin-top: 20px;">
        <button type="button" class="btn-secondary" style="width: 100%;" onclick="habilitarEdicao()">Editar Dados</button>
      </div>

      <div id="acoes-edit" style="display: none; gap: 10px; margin-top: 20px;">
        <button type="submit" name="atualizar_geral" class="btn-primary" style="flex: 1;">Salvar Alterações</button>
        <button type="button" class="btn-secondary" onclick="cancelarEdicao()">Cancelar</button>
      </div>
    </form>
  </div>
</div>

<!-- ABA 2: SEGURANÇA -->
<div id="aba-senha" class="tab-pane <?= ($abaAtiva === 'aba-senha') ? 'active' : '' ?>">
  <div class="profile-card">
    <div style="margin-bottom: 20px;">
      <h3 style="font-size: 16px; margin: 0 0 4px 0;">Segurança</h3>
      <p style="color: #71717a; font-size: 13px; margin: 0;">Mantenha sua conta protegida atualizando sua senha periodicamente.</p>
    </div>

    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
      <div class="form-group">
        <label>Senha Atual</label>
        <input type="password" name="senha_atual" required placeholder="Digite sua senha atual">
      </div>

      <div class="form-group">
        <label>Nova Senha</label>
        <input type="password" name="nova_senha" required placeholder="Mínimo 6 caracteres">
      </div>

      <div class="form-group">
        <label>Confirmar Nova Senha</label>
        <input type="password" name="confirma_senha" required placeholder="Repita a nova senha">
      </div>

      <button type="submit" name="alterar_senha" class="btn-primary" style="width: 100%;">Atualizar Senha</button>
    </form>
  </div>
</div>

<script>
function abrirAba(event, abaId) {
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));

    document.getElementById(abaId).classList.add('active');
    event.currentTarget.classList.add('active');
}

function habilitarEdicao() {
    document.querySelectorAll('.input-geral').forEach(input => input.removeAttribute('readonly'));
    document.querySelector('.input-geral').focus();
    document.getElementById('acoes-view').style.display = 'none';
    document.getElementById('acoes-edit').style.display = 'flex';
}

function cancelarEdicao() {
    document.querySelectorAll('.input-geral').forEach(input => input.setAttribute('readonly', true));
    document.getElementById('acoes-view').style.display = 'block';
    document.getElementById('acoes-edit').style.display = 'none';
}

// Máscara Inteligente para CPF ou CNPJ
function mascaraCpfCnpj(input) {
    let v = input.value.replace(/\D/g, ''); // Remove tudo o que não é dígito

    if (v.length <= 11) {
        // Formato CPF: 000.000.000-00
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d)/, '$1.$2');
        v = v.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
    } else {
        // Formato CNPJ: 00.000.000/0000-00
        v = v.substring(0, 14); // Limita a 14 dígitos
        v = v.replace(/^(\d{2})(\d)/, '$1.$2');
        v = v.replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3');
        v = v.replace(/\.(\d{3})(\d)/, '.$1/$2');
        v = v.replace(/(\d{4})(\d)/, '$1-$2');
    }
    input.value = v;
}

// Máscara de Telefone/WhatsApp: (00) 00000-0000
function mascaraTelefone(input) {
    let v = input.value.replace(/\D/g, '').substring(0, 11);
    if (v.length > 10) {
        v = v.replace(/^(\d{2})(\d{5})(\d{4})$/, '($1) $2-$3');
    } else if (v.length > 5) {
        v = v.replace(/^(\d{2})(\d{4})(\d{0,4})$/, '($1) $2-$3');
    } else if (v.length > 2) {
        v = v.replace(/^(\d{2})(\d{0,5})$/, '($1) $2');
    }
    input.value = v;
}

// Aplica a máscara no carregamento caso já venham dados salvos
document.addEventListener('DOMContentLoaded', () => {
    const docInput = document.getElementById('campo_cnpj_cpf');
    const telInput = document.getElementById('campo_telefone');
    if (docInput && docInput.value) mascaraCpfCnpj(docInput);
    if (telInput && telInput.value) mascaraTelefone(telInput);
});
</script>