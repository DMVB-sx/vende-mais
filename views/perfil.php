<?php
$usuario_id = $_SESSION['usuario_id'] ?? 0;
$empresa_id = $_SESSION['empresa_id'] ?? 0;
$mensagem = '';
$abaAtiva = 'aba-geral';

// 1. Processar Atualizações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (function_exists('validar_csrf')) {
        validar_csrf();
    }

    // Atualizar Dados Gerais
    if (isset($_POST['atualizar_geral'])) {
        $abaAtiva = 'aba-geral';
        $nome_usuario  = trim($_POST['nome_usuario'] ?? '');
        $email_usuario = trim($_POST['email_usuario'] ?? '');
        $nome_empresa  = trim($_POST['nome_empresa'] ?? '');

        if (!empty($nome_usuario) && !empty($email_usuario) && !empty($nome_empresa)) {
            try {
                // Checa e-mail duplicado
                $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $stmtCheck->execute([$email_usuario, $usuario_id]);
                
                if ($stmtCheck->rowCount() > 0) {
                    $mensagem = '
                        <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                            <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                            <div><strong class="font-semibold block text-rose-300">Este e-mail já está em uso por outro usuário.</strong></div>
                        </div>
                    ';
                } else {
                    // 1. Atualiza Usuário
                    $stmtUser = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ? WHERE id = ?");
                    $stmtUser->execute([$nome_usuario, $email_usuario, $usuario_id]);
                    $_SESSION['usuario_nome'] = $nome_usuario;

                    // 2. Atualiza Nome da Empresa / Apelido
                    $colunasEmpresa = [];
                    $stmtCols = $pdo->query("DESCRIBE empresas");
                    while ($col = $stmtCols->fetch(PDO::FETCH_ASSOC)) {
                        $colunasEmpresa[] = $col['Field'];
                    }

                    $colunaNome = in_array('nome', $colunasEmpresa) ? 'nome' : (in_array('nome_fantasia', $colunasEmpresa) ? 'nome_fantasia' : 'razao_social');

                    $stmtEmp = $pdo->prepare("UPDATE empresas SET {$colunaNome} = ? WHERE id = ?");
                    $stmtEmp->execute([$nome_empresa, $empresa_id]);

                    $_SESSION['empresa_nome'] = $nome_empresa;
                    
                    $mensagem = '
                        <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                            <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                            <div><strong class="font-semibold block text-emerald-300">Dados salvos com sucesso!</strong></div>
                        </div>
                    ';
                }
            } catch (Throwable $e) {
                error_log($e->getMessage());
                $mensagem = '
                    <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                        <i data-lucide="alert-circle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                        <div><strong class="font-semibold block text-rose-300">Erro ao atualizar dados. Tente novamente.</strong></div>
                    </div>
                ';
            }
        } else {
            $mensagem = '
                <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                    <div><strong class="font-semibold block text-rose-300">Preencha todos os campos obrigatórios.</strong></div>
                </div>
            ';
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
                        
                        $mensagem = '
                            <div class="flex items-start gap-3 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 p-4 rounded-xl mb-6 text-sm">
                                <i data-lucide="check-circle" class="w-5 h-5 text-emerald-400 shrink-0 mt-0.5"></i>
                                <div><strong class="font-semibold block text-emerald-300">Senha alterada com sucesso!</strong></div>
                            </div>
                        ';
                    } else {
                        $mensagem = '
                            <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                                <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                                <div><strong class="font-semibold block text-rose-300">A nova senha deve ter no mínimo 6 caracteres.</strong></div>
                            </div>
                        ';
                    }
                } else {
                    $mensagem = '
                        <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                            <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                            <div><strong class="font-semibold block text-rose-300">A nova senha e a confirmação não coincidem.</strong></div>
                        </div>
                    ';
                }
            } else {
                $mensagem = '
                    <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                        <i data-lucide="alert-triangle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                        <div><strong class="font-semibold block text-rose-300">A senha atual informada está incorreta.</strong></div>
                    </div>
                ';
            }
        } catch (Throwable $e) {
            error_log($e->getMessage());
            $mensagem = '
                <div class="flex items-start gap-3 bg-rose-500/10 border border-rose-500/20 text-rose-400 p-4 rounded-xl mb-6 text-sm">
                    <i data-lucide="alert-circle" class="w-5 h-5 text-rose-400 shrink-0 mt-0.5"></i>
                    <div><strong class="font-semibold block text-rose-300">Erro ao atualizar senha. Tente novamente.</strong></div>
                </div>
            ';
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

// 3. Informações do Plano / Assinatura
$nomePlano = $empresa['plano_nome'] ?? 'Plano Pro';
$dataExpiracao = !empty($empresa['data_expiracao']) ? $empresa['data_expiracao'] : date('Y-m-d', strtotime('+30 days'));
$diasRestantes = max(0, (int)ceil((strtotime($dataExpiracao) - time()) / 86400));
$userIp = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
?>

<script src="https://unpkg.com/lucide@latest"></script>

<style>
.tab-pane {
    display: none;
}
.tab-pane.active {
    display: block;
}
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>

<!-- CABEÇALHO DA PÁGINA -->
<header class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-6">
    <div>
        <h2 class="text-2xl font-black text-white flex items-center gap-2.5 tracking-tight m-0">
            <div class="p-2 bg-emerald-500/10 rounded-xl border border-emerald-500/20">
                <i data-lucide="settings" class="w-5 h-5 text-emerald-400"></i>
            </div>
            Minha Conta
        </h2>
        <p class="text-sm text-zinc-400 mt-2 m-0">
            Gerencie suas informações, preferências e segurança da conta
        </p>
    </div>
</header>

<?= $mensagem ?>

<!-- ABAS DE NAVEGAÇÃO -->
<div class="flex items-center gap-2 overflow-x-auto no-scrollbar border-b border-zinc-800/80 mb-6 pb-2 max-w-3xl">
    <button type="button" 
            class="tab-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer whitespace-nowrap <?= ($abaAtiva === 'aba-geral') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-transparent text-zinc-400 hover:text-white hover:bg-zinc-900' ?>" 
            onclick="abrirAba(event, 'aba-geral')">
        <i data-lucide="user" class="w-4 h-4"></i>
        <span>Dados do Perfil</span>
    </button>

    <button type="button" 
            class="tab-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer whitespace-nowrap <?= ($abaAtiva === 'aba-plano') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-transparent text-zinc-400 hover:text-white hover:bg-zinc-900' ?>" 
            onclick="abrirAba(event, 'aba-plano')">
        <i data-lucide="sparkles" class="w-4 h-4 text-emerald-400"></i>
        <span>Meu Plano</span>
    </button>

    <button type="button" 
            class="tab-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer whitespace-nowrap <?= ($abaAtiva === 'aba-senha') ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-transparent text-zinc-400 hover:text-white hover:bg-zinc-900' ?>" 
            onclick="abrirAba(event, 'aba-senha')">
        <i data-lucide="shield-check" class="w-4 h-4"></i>
        <span>Segurança & Senha</span>
    </button>
</div>

<!-- ABA 1: DADOS DO PERFIL & NOTIFICAÇÕES -->
<div id="aba-geral" class="tab-pane <?= ($abaAtiva === 'aba-geral') ? 'active' : '' ?>">
    <div class="space-y-6 max-w-3xl">
        
        <!-- INFORMAÇÕES DA CONTA -->
        <div class="bg-[#09090b] border border-zinc-800/80 rounded-2xl p-4 sm:p-6 overflow-hidden box-border">
            <div class="flex items-center gap-2.5 mb-6">
                <div class="p-1.5 bg-zinc-800/60 rounded-lg text-zinc-400">
                    <i data-lucide="user-check" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white m-0">Informações da Conta</h3>
                    <p class="text-xs text-zinc-500 m-0 mt-0.5">Seus dados de identificação e preferências no sistema</p>
                </div>
            </div>

            <form method="POST" id="form-geral" class="space-y-4">
                <?php if (function_exists('csrf_token')): ?>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <?php endif; ?>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Nome Completo *</label>
                    <input type="text" name="nome_usuario" class="input-geral w-full box-border bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors" 
                           value="<?= htmlspecialchars($usuario['nome']) ?>" readonly required>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">E-mail de Acesso *</label>
                    <input type="email" name="email_usuario" class="input-geral w-full box-border bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors" 
                           value="<?= htmlspecialchars($usuario['email']) ?>" readonly required>
                </div>

                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">
                        Nome / Apelido de Exibição *
                    </label>
                    <input type="text" name="nome_empresa" class="input-geral w-full box-border bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl px-3.5 py-2.5 outline-none focus:border-emerald-500 transition-colors" 
                           value="<?= htmlspecialchars($nomeEmpresaAtual) ?>" placeholder="Ex: DMVB ou Minha Loja" readonly required>
                </div>

                <div id="acoes-view" class="pt-3">
                    <button type="button" class="w-full inline-flex items-center justify-center gap-2 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 text-sm font-semibold rounded-xl px-5 py-3 transition-colors cursor-pointer" onclick="habilitarEdicao()">
                        <i data-lucide="pencil" class="w-4 h-4"></i> Editar Dados
                    </button>
                </div>

                <div id="acoes-edit" style="display: none;" class="items-center gap-3 pt-3">
                    <button type="submit" name="atualizar_geral" class="flex-1 inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-black text-sm font-bold rounded-xl px-6 py-3 transition-all shadow-[0_0_20px_rgba(16,185,129,0.15)] cursor-pointer">
                        <i data-lucide="check" class="w-4 h-4"></i> Salvar Alterações
                    </button>
                    <button type="button" class="inline-flex items-center justify-center bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 text-zinc-300 text-sm font-semibold rounded-xl px-5 py-3 transition-colors cursor-pointer" onclick="cancelarEdicao()">
                        Cancelar
                    </button>
                </div>
            </form>
        </div>

        <!-- NOTIFICAÇÕES E PREFERÊNCIAS (CHAVE DE ESTOQUE BAIXO) -->
        <div class="bg-[#09090b] border border-zinc-800/80 rounded-2xl p-4 sm:p-6 overflow-hidden box-border">
            <div class="flex items-center gap-2.5 mb-4">
                <div class="p-1.5 bg-zinc-800/60 rounded-lg text-zinc-400">
                    <i data-lucide="bell" class="w-4 h-4 text-amber-400"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white m-0">Notificações e Alertas</h3>
                    <p class="text-xs text-zinc-500 m-0 mt-0.5">Defina quais avisos automáticos deseja ver no painel</p>
                </div>
            </div>

            <div class="bg-[#000000] border border-zinc-800/80 rounded-xl p-4 flex items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="p-2 bg-amber-500/10 rounded-lg text-amber-400 mt-0.5 shrink-0">
                        <i data-lucide="alert-triangle" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <strong class="text-sm font-semibold text-white block">Alerta de Estoque Crítico</strong>
                        <span class="text-xs text-zinc-400 block mt-0.5">
                            Exibir banner no topo da Visão Geral quando houver produtos com 3 ou menos unidades.
                        </span>
                    </div>
                </div>

                <!-- SWITCH INTERATIVO -->
                <label class="relative inline-flex items-center cursor-pointer shrink-0">
                    <input type="checkbox" id="toggle_alerta_estoque" class="sr-only peer" onchange="alternarPreferenciaEstoque(this.checked)">
                    <div class="w-11 h-6 bg-zinc-800 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-zinc-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-500"></div>
                </label>
            </div>
        </div>

    </div>
</div>

<!-- ABA 2: MEU PLANO -->
<div id="aba-plano" class="tab-pane <?= ($abaAtiva === 'aba-plano') ? 'active' : '' ?>">
    <div class="space-y-6 max-w-3xl">
        
        <!-- CARD PRINCIPAL DO PLANO -->
        <div class="bg-[#09090b] border border-zinc-800/80 rounded-2xl p-6 relative overflow-hidden">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-6 border-b border-zinc-900">
                <div class="flex items-center gap-3.5">
                    <div class="p-3 bg-emerald-500/10 rounded-2xl border border-emerald-500/20 text-emerald-400 shrink-0">
                        <i data-lucide="sparkles" class="w-6 h-6"></i>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base sm:text-lg font-bold text-white m-0"><?= htmlspecialchars($nomePlano) ?></h3>
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span> Ativo
                            </span>
                        </div>
                        <p class="text-xs text-zinc-400 m-0 mt-1">
                            Acesso completo a todas as ferramentas operacionais e financeiras.
                        </p>
                    </div>
                </div>

                <div class="sm:text-right bg-zinc-950/60 sm:bg-transparent p-3.5 sm:p-0 rounded-xl border border-zinc-900 sm:border-0">
                    <span class="text-[11px] font-semibold text-zinc-500 uppercase tracking-wider block">Próxima Renovação</span>
                    <strong class="text-sm font-bold text-zinc-200 mt-0.5 block">
                        <?= $diasRestantes > 0 ? "em {$diasRestantes} dias" : 'Vence hoje' ?>
                    </strong>
                    <span class="text-xs text-zinc-500 block mt-0.5"><?= date('d/m/Y', strtotime($dataExpiracao)) ?></span>
                </div>
            </div>

            <!-- RECURSOS DO PLANO -->
            <div class="pt-6">
                <h4 class="text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-3">Recursos inclusos no seu plano:</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs text-zinc-300">
                    <div class="flex items-center gap-2 bg-[#000000] p-2.5 rounded-xl border border-zinc-900">
                        <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                        <span>Produtos e estoque ilimitados</span>
                    </div>
                    <div class="flex items-center gap-2 bg-[#000000] p-2.5 rounded-xl border border-zinc-900">
                        <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                        <span>Controle de crediário e parcelamentos</span>
                    </div>
                    <div class="flex items-center gap-2 bg-[#000000] p-2.5 rounded-xl border border-zinc-900">
                        <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                        <span>Relatórios executivos e exportação</span>
                    </div>
                    <div class="flex items-center gap-2 bg-[#000000] p-2.5 rounded-xl border border-zinc-900">
                        <i data-lucide="check" class="w-4 h-4 text-emerald-400 shrink-0"></i>
                        <span>Acesso multiplataforma (Web e Mobile App)</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- SUPORTE E UPGRADE -->
        <div class="bg-[#09090b] border border-zinc-800/80 rounded-2xl p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <strong class="text-sm font-semibold text-white block">Precisa alterar ou renovar seu plano?</strong>
                <span class="text-xs text-zinc-400 block mt-0.5">Fale diretamente com o suporte para upgrades ou dúvidas financeiras.</span>
            </div>

            <a href="https://wa.me/5575983193550?text=<?= urlencode('Olá! Gostaria de falar sobre o meu plano no Vende+.') ?>" target="_blank"
               class="inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-black text-xs font-bold rounded-xl px-4 py-2.5 transition no-underline shrink-0">
                <i data-lucide="message-circle" class="w-4 h-4"></i>
                <span>Suporte via WhatsApp</span>
            </a>
        </div>

    </div>
</div>

<!-- ABA 3: SEGURANÇA & SENHA -->
<div id="aba-senha" class="tab-pane <?= ($abaAtiva === 'aba-senha') ? 'active' : '' ?>">
    <div class="space-y-6 max-w-3xl">
        
        <!-- TROCA DE SENHA -->
        <div class="bg-[#09090b] border border-zinc-800/80 rounded-2xl p-4 sm:p-6 overflow-hidden box-border">
            <div class="flex items-center gap-2.5 mb-6">
                <div class="p-1.5 bg-zinc-800/60 rounded-lg text-zinc-400">
                    <i data-lucide="key-round" class="w-4 h-4"></i>
                </div>
                <div>
                    <h3 class="text-base font-bold text-white m-0">Alterar Senha</h3>
                    <p class="text-xs text-zinc-500 m-0 mt-0.5">Atualize suas credenciais para manter sua conta protegida</p>
                </div>
            </div>

            <form method="POST" class="space-y-4">
                <?php if (function_exists('csrf_token')): ?>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                <?php endif; ?>

                <!-- SENHA ATUAL -->
                <div>
                    <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Senha Atual *</label>
                    <div class="relative">
                        <input type="password" name="senha_atual" id="senha_atual" required placeholder="Digite sua senha atual"
                               class="w-full box-border bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl pl-3.5 pr-10 py-2.5 outline-none focus:border-emerald-500 transition-colors">
                        <button type="button" onclick="toggleVisibilidadeSenha('senha_atual', this)" 
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-300 bg-transparent border-none p-1 cursor-pointer">
                            <i data-lucide="eye" class="w-4 h-4"></i>
                        </button>
                    </div>
                </div>

                <!-- NOVA SENHA & CONFIRMAÇÃO -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Nova Senha *</label>
                        <div class="relative">
                            <input type="password" name="nova_senha" id="nova_senha" required placeholder="Mínimo 6 caracteres" oninput="analisarForcaSenha(this.value)"
                                   class="w-full box-border bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl pl-3.5 pr-10 py-2.5 outline-none focus:border-emerald-500 transition-colors">
                            <button type="button" onclick="toggleVisibilidadeSenha('nova_senha', this)" 
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-300 bg-transparent border-none p-1 cursor-pointer">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-zinc-400 mb-2">Confirmar Nova Senha *</label>
                        <div class="relative">
                            <input type="password" name="confirma_senha" id="confirma_senha" required placeholder="Repita a nova senha"
                                   class="w-full box-border bg-[#000000] border border-zinc-800 text-zinc-200 text-sm rounded-xl pl-3.5 pr-10 py-2.5 outline-none focus:border-emerald-500 transition-colors">
                            <button type="button" onclick="toggleVisibilidadeSenha('confirma_senha', this)" 
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-300 bg-transparent border-none p-1 cursor-pointer">
                                <i data-lucide="eye" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- MEDIDOR DE FORÇA DA SENHA -->
                <div class="pt-1">
                    <div class="flex items-center justify-between text-[11px] mb-1.5">
                        <span class="text-zinc-500">Força da nova senha:</span>
                        <span id="texto-forca-senha" class="font-bold text-zinc-400">Digite uma senha</span>
                    </div>
                    <div class="w-full bg-zinc-900 rounded-full h-1.5 overflow-hidden">
                        <div id="barra-forca-senha" class="h-full bg-zinc-700 transition-all duration-300" style="width: 0%;"></div>
                    </div>
                </div>

                <!-- BOTÃO -->
                <div class="pt-2">
                    <button type="submit" name="alterar_senha"
                            class="w-full inline-flex items-center justify-center gap-2 bg-emerald-500 hover:bg-emerald-400 text-black text-sm font-bold rounded-xl px-6 py-3 transition-all shadow-[0_0_20px_rgba(16,185,129,0.15)] cursor-pointer">
                        <i data-lucide="shield-check" class="w-4 h-4"></i> Atualizar Senha
                    </button>
                </div>
            </form>
        </div>

        <!-- SESSÃO ATIVA -->
        <div class="bg-[#09090b] border border-zinc-800/80 rounded-2xl p-4 sm:p-5">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="p-2 bg-emerald-500/10 rounded-xl text-emerald-400 border border-emerald-500/20">
                        <i data-lucide="laptop" class="w-4 h-4"></i>
                    </div>
                    <div>
                        <strong class="text-white text-xs sm:text-sm block">Sessão Atual Ativa</strong>
                        <span class="text-[11px] text-zinc-500 block">Navegador Web · IP <?= htmlspecialchars($userIp) ?></span>
                    </div>
                </div>
                <span class="text-[11px] font-semibold px-2.5 py-1 rounded-full bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    Conectado
                </span>
            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }

    // Inicializa o switch de alerta de estoque de acordo com a preferência
    const toggle = document.getElementById("toggle_alerta_estoque");
    if (toggle) {
        const ocultar = localStorage.getItem("vende_ocultar_alerta_estoque") === "true";
        toggle.checked = !ocultar;
    }
});

function alternarPreferenciaEstoque(ativo) {
    if (ativo) {
        localStorage.removeItem("vende_ocultar_alerta_estoque");
        sessionStorage.removeItem("vende_fechou_alerta_temp");
    } else {
        localStorage.setItem("vende_ocultar_alerta_estoque", "true");
    }
}

function abrirAba(event, abaId) {
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => {
        el.className = 'tab-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer whitespace-nowrap bg-transparent text-zinc-400 hover:text-white hover:bg-zinc-900';
    });

    document.getElementById(abaId).classList.add('active');
    event.currentTarget.className = 'tab-btn inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs sm:text-sm font-semibold transition cursor-pointer whitespace-nowrap bg-emerald-500/10 text-emerald-400 border border-emerald-500/20';
    
    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function habilitarEdicao() {
    document.querySelectorAll('.input-geral').forEach(input => {
        input.removeAttribute('readonly');
        input.classList.add('border-zinc-700');
    });
    document.querySelector('.input-geral').focus();
    document.getElementById('acoes-view').style.display = 'none';
    document.getElementById('acoes-edit').style.display = 'flex';
}

function cancelarEdicao() {
    document.querySelectorAll('.input-geral').forEach(input => {
        input.setAttribute('readonly', true);
        input.classList.remove('border-zinc-700');
    });
    document.getElementById('acoes-view').style.display = 'block';
    document.getElementById('acoes-edit').style.display = 'none';
}

function toggleVisibilidadeSenha(inputId, botao) {
    const input = document.getElementById(inputId);
    if (!input) return;

    const ehPassword = input.type === 'password';
    input.type = ehPassword ? 'text' : 'password';

    botao.innerHTML = ehPassword 
        ? '<i data-lucide="eye-off" class="w-4 h-4"></i>' 
        : '<i data-lucide="eye" class="w-4 h-4"></i>';

    if (typeof lucide !== 'undefined') {
        lucide.createIcons();
    }
}

function analisarForcaSenha(senha) {
    const barra = document.getElementById('barra-forca-senha');
    const texto = document.getElementById('texto-forca-senha');
    if (!barra || !texto) return;

    let pontos = 0;

    if (senha.length >= 6) pontos += 1;
    if (senha.length >= 8) pontos += 1;
    if (/[A-Z]/.test(senha)) pontos += 1;
    if (/[0-9]/.test(senha)) pontos += 1;
    if (/[^A-Za-z0-9]/.test(senha)) pontos += 1;

    if (senha.length === 0) {
        barra.style.width = '0%';
        barra.className = 'h-full bg-zinc-700 transition-all duration-300';
        texto.innerText = 'Digite uma senha';
        texto.className = 'font-bold text-zinc-500';
    } else if (pontos <= 2) {
        barra.style.width = '25%';
        barra.className = 'h-full bg-rose-500 transition-all duration-300';
        texto.innerText = 'Fraca';
        texto.className = 'font-bold text-rose-500';
    } else if (pontos <= 3) {
        barra.style.width = '60%';
        barra.className = 'h-full bg-amber-500 transition-all duration-300';
        texto.innerText = 'Média';
        texto.className = 'font-bold text-amber-400';
    } else {
        barra.style.width = '100%';
        barra.className = 'h-full bg-emerald-500 transition-all duration-300';
        texto.innerText = 'Forte e Segura';
        texto.className = 'font-bold text-emerald-400';
    }
}
</script>