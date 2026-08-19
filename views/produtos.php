<?php

$mensagem = '';
$empresa_id = $_SESSION['empresa_id'] ?? 0;


/*
|--------------------------------------------------------------------------
| 1. EXCLUIR / ARQUIVAR PRODUTO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deletar_produto'])) {

    validar_csrf();

    $id_deletar = (int)($_POST['id_produto'] ?? 0);

    if ($id_deletar <= 0) {

        $mensagem = '
            <p style="color:#ef4444;margin-bottom:20px;">
                ⚠️ Produto inválido.
            </p>
        ';

    } else {

        try {

            $stmtProduto = $pdo->prepare("
                SELECT id, nome
                FROM produtos
                WHERE id = ?
                AND empresa_id = ?
                LIMIT 1
            ");

            $stmtProduto->execute([
                $id_deletar,
                $empresa_id
            ]);

            $produto = $stmtProduto->fetch();

            if (!$produto) {

                $mensagem = '
                    <p style="color:#ef4444;margin-bottom:20px;">
                        ⚠️ Produto não encontrado.
                    </p>
                ';

            } else {

                /*
                 * Verifica se existem vendas
                 */

                $stmtCheckVendas = $pdo->prepare("
                    SELECT COUNT(*) AS total
                    FROM vendas
                    WHERE produto_id = ?
                    AND empresa_id = ?
                ");

                $stmtCheckVendas->execute([
                    $id_deletar,
                    $empresa_id
                ]);

                $temVendas = ((int)$stmtCheckVendas->fetch()['total']) > 0;


                /*
                 * Verifica se existem compras
                 */

                $stmtCheckCompras = $pdo->prepare("
                    SELECT COUNT(*) AS total
                    FROM compras
                    WHERE produto_id = ?
                    AND empresa_id = ?
                ");

                $stmtCheckCompras->execute([
                    $id_deletar,
                    $empresa_id
                ]);

                $temCompras = ((int)$stmtCheckCompras->fetch()['total']) > 0;


                /*
                 * Se houver histórico, arquiva.
                 * Caso contrário, exclui definitivamente.
                 */

                if ($temVendas || $temCompras) {

                    $stmtInativar = $pdo->prepare("
                        UPDATE produtos
                        SET ativo = FALSE
                        WHERE id = ?
                        AND empresa_id = ?
                    ");

                    $stmtInativar->execute([
                        $id_deletar,
                        $empresa_id
                    ]);

                    if ($stmtInativar->rowCount() > 0) {

                        $mensagem = '
                            <p style="
                                color:#f59e0b;
                                margin-bottom:20px;
                                font-weight:500;
                            ">
                                📦 Produto arquivado com sucesso!
                                O histórico foi preservado.
                            </p>
                        ';

                    } else {

                        $mensagem = '
                            <p style="color:#ef4444;margin-bottom:20px;">
                                ⚠️ Não foi possível arquivar o produto.
                            </p>
                        ';
                    }

                } else {

                    $stmtDel = $pdo->prepare("
                        DELETE FROM produtos
                        WHERE id = ?
                        AND empresa_id = ?
                    ");

                    $stmtDel->execute([
                        $id_deletar,
                        $empresa_id
                    ]);

                    if ($stmtDel->rowCount() > 0) {

                        $mensagem = '
                            <p style="
                                color:#10b981;
                                margin-bottom:20px;
                                font-weight:500;
                            ">
                                🗑️ Produto excluído com sucesso!
                            </p>
                        ';

                    } else {

                        $mensagem = '
                            <p style="color:#ef4444;margin-bottom:20px;">
                                ⚠️ Não foi possível excluir o produto.
                            </p>
                        ';
                    }
                }
            }

        } catch (Throwable $e) {

            error_log($e->getMessage());

            $mensagem = '
                <p style="color:#ef4444;margin-bottom:20px;">
                    ⚠️ Ocorreu um erro ao excluir o produto.
                </p>
            ';
        }
    }
}


/*
|--------------------------------------------------------------------------
| 2. SALVAR / EDITAR PRODUTO
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['salvar_produto'])) {

    validar_csrf();

    $id = (int)($_POST['id'] ?? 0);

    $nome = trim($_POST['nome'] ?? '');
    $fornecedor = trim($_POST['fornecedor'] ?? '');

    $preco_custo = (float)($_POST['preco_custo'] ?? 0);
    $preco_venda = (float)($_POST['preco_venda'] ?? 0);

    $estoque = (int)($_POST['estoque'] ?? 0);


    if (empty($nome)) {

        $mensagem = '
            <p style="color:#ef4444;margin-bottom:20px;">
                ⚠️ Informe o nome do produto.
            </p>
        ';

    } elseif ($preco_custo < 0 || $preco_venda < 0 || $estoque < 0) {

        $mensagem = '
            <p style="color:#ef4444;margin-bottom:20px;">
                ⚠️ Os valores não podem ser negativos.
            </p>
        ';

    } else {

        try {

            if ($id > 0) {

                $stmt = $pdo->prepare("
                    UPDATE produtos
                    SET
                        nome = ?,
                        fornecedor = ?,
                        preco_custo = ?,
                        preco_venda = ?,
                        estoque = ?
                    WHERE id = ?
                    AND empresa_id = ?
                ");

                $stmt->execute([
                    $nome,
                    $fornecedor,
                    $preco_custo,
                    $preco_venda,
                    $estoque,
                    $id,
                    $empresa_id
                ]);

                $mensagem = '
                    <p style="color:#10b981;margin-bottom:20px;">
                        ✏️ Produto atualizado com sucesso!
                    </p>
                ';

            } else {

                $stmt = $pdo->prepare("
                    INSERT INTO produtos
                    (
                        empresa_id,
                        nome,
                        fornecedor,
                        preco_custo,
                        preco_venda,
                        estoque
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmt->execute([
                    $empresa_id,
                    $nome,
                    $fornecedor,
                    $preco_custo,
                    $preco_venda,
                    $estoque
                ]);

                $mensagem = '
                    <p style="color:#10b981;margin-bottom:20px;">
                        ✅ Produto cadastrado com sucesso!
                    </p>
                ';
            }

        } catch (Throwable $e) {

            error_log($e->getMessage());

            $mensagem = '
                <p style="color:#ef4444;margin-bottom:20px;">
                    ⚠️ Não foi possível salvar o produto.
                </p>
            ';
        }
    }
}


/*
|--------------------------------------------------------------------------
| 3. PRODUTO PARA EDIÇÃO
|--------------------------------------------------------------------------
*/

$produto_editar = null;

if (
    isset($_GET['acao']) &&
    $_GET['acao'] === 'editar' &&
    isset($_GET['id'])
) {

    $id_editar = (int)$_GET['id'];

    $stmt = $pdo->prepare("
        SELECT *
        FROM produtos
        WHERE id = ?
        AND empresa_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id_editar,
        $empresa_id
    ]);

    $produto_editar = $stmt->fetch();
}


/*
|--------------------------------------------------------------------------
| 4. BUSCA
|--------------------------------------------------------------------------
*/

$busca = trim($_GET['busca'] ?? '');

$pagina = max(
    1,
    (int)($_GET['pagina'] ?? 1)
);

$por_pagina = 15;

$offset = ($pagina - 1) * $por_pagina;


/*
|--------------------------------------------------------------------------
| 5. CONTAGEM TOTAL
|--------------------------------------------------------------------------
*/

$params_count = [$empresa_id];

$sql_count_busca = '';

if (!empty($busca)) {

    $sql_count_busca = "
        AND (
            nome LIKE ?
            OR fornecedor LIKE ?
        )
    ";

    $params_count[] = "%{$busca}%";
    $params_count[] = "%{$busca}%";
}


$stmtCount = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM produtos
    WHERE empresa_id = ?
    AND ativo = TRUE
    {$sql_count_busca}
");

$stmtCount->execute($params_count);

$total_produtos = (int)$stmtCount->fetch()['total'];

$total_paginas = max(
    1,
    (int)ceil($total_produtos / $por_pagina)
);


/*
 * Caso alguém informe uma página maior que a existente
 */

if ($pagina > $total_paginas) {

    $pagina = $total_paginas;

    $offset = ($pagina - 1) * $por_pagina;
}


/*
|--------------------------------------------------------------------------
| 6. LISTAGEM
|--------------------------------------------------------------------------
*/

$params = [$empresa_id];

$sql_busca = '';

if (!empty($busca)) {

    $sql_busca = "
        AND (
            nome LIKE ?
            OR fornecedor LIKE ?
        )
    ";

    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
}

$params[] = $por_pagina;
$params[] = $offset;


$stmt = $pdo->prepare("
    SELECT *
    FROM produtos
    WHERE empresa_id = ?
    AND ativo = TRUE
    {$sql_busca}
    ORDER BY id DESC
    LIMIT ? OFFSET ?
");

$stmt->execute($params);

$produtos = $stmt->fetchAll();

?>


<style>

/*
|--------------------------------------------------------------------------
| CONTAINER DOS PRODUTOS
|--------------------------------------------------------------------------
*/

.produtos-lista {
    display: flex;
    flex-direction: column;
    gap: 10px;
}


/*
|--------------------------------------------------------------------------
| CARD DO PRODUTO
|--------------------------------------------------------------------------
*/

.produto-card {
    border: 1px solid #27272a;
    border-radius: 12px;
    background: #09090b;
    overflow: hidden;
}


/*
|--------------------------------------------------------------------------
| CABEÇALHO COMPACTO
|--------------------------------------------------------------------------
*/

.produto-resumo {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 15px 16px;
    cursor: pointer;
    background: transparent;
    border: none;
    color: #f8fafc;
    text-align: left;
}


.produto-resumo:hover {
    background: #111113;
}


.produto-info-principal {
    min-width: 0;
    flex: 1;
}


.produto-nome {
    font-size: 15px;
    font-weight: 600;
    color: #f8fafc;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}


.produto-resumo-meta {
    display: flex;
    align-items: center;
    gap: 16px;
    flex-shrink: 0;
}


.produto-meta-item {
    font-size: 13px;
    color: #a1a1aa;
    white-space: nowrap;
}


.produto-meta-valor {
    color: #e4e4e7;
    font-weight: 500;
}


.produto-seta {
    width: 30px;
    height: 30px;
    border-radius: 7px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #18181b;
    border: 1px solid #27272a;
    color: #a1a1aa;
    font-size: 14px;
    transition: transform .2s ease;
    flex-shrink: 0;
}


.produto-card.aberto .produto-seta {
    transform: rotate(180deg);
}


/*
|--------------------------------------------------------------------------
| DETALHES
|--------------------------------------------------------------------------
*/

.produto-detalhes {
    display: none;
    padding: 0 16px 16px;
}


.produto-card.aberto .produto-detalhes {
    display: block;
}


.produto-detalhes-linha {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    padding: 10px 0;
    border-top: 1px solid #18181b;
}


.produto-detalhes-label {
    color: #71717a;
    font-size: 13px;
}


.produto-detalhes-valor {
    color: #e4e4e7;
    font-size: 13px;
    text-align: right;
}


.produto-acoes {
    display: flex;
    gap: 8px;
    padding-top: 12px;
    margin-top: 4px;
    border-top: 1px solid #27272a;
}


.produto-btn {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    padding: 10px 12px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 13px;
    cursor: pointer;
}


.produto-btn-editar {
    background: #18181b;
    color: #f8fafc;
    border: 1px solid #27272a;
}


.produto-btn-editar:hover {
    background: #27272a;
}


.produto-btn-excluir {
    background: #18181b;
    color: #fca5a5;
    border: 1px solid #27272a;
}


.produto-btn-excluir:hover {
    background: #27272a;
}


/*
|--------------------------------------------------------------------------
| PAGINAÇÃO
|--------------------------------------------------------------------------
*/

.produtos-paginacao {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
    margin-top: 20px;
}


.produtos-paginacao a,
.produtos-paginacao span {
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 7px;
    text-decoration: none;
    font-size: 13px;
}


.produtos-paginacao a {
    color: #cbd5e1;
    background: #18181b;
    border: 1px solid #27272a;
}


.produtos-paginacao a:hover {
    background: #27272a;
}


.produtos-paginacao .pagina-atual {
    color: #ffffff;
    background: #27272a;
    border: 1px solid #3f3f46;
    font-weight: 600;
}


.produtos-paginacao .pagina-desativada {
    color: #52525b;
    background: #09090b;
    border: 1px solid #18181b;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .produto-resumo {
        padding: 14px;
        gap: 8px;
    }


    .produto-nome {
        font-size: 14px;
    }


    .produto-resumo-meta {
        gap: 8px;
    }


    .produto-meta-item {
        font-size: 12px;
    }


    /*
     * No celular mostramos somente
     * estoque + preço no resumo.
     */

    .produto-meta-custo {
        display: none;
    }


    .produto-seta {
        width: 28px;
        height: 28px;
        font-size: 12px;
    }


    .produto-detalhes {
        padding: 0 14px 14px;
    }


    .produto-acoes {
        gap: 7px;
    }


    .produto-btn {
        padding: 10px 8px;
    }
}


/*
|--------------------------------------------------------------------------
| TELAS MUITO PEQUENAS
|--------------------------------------------------------------------------
*/

@media (max-width: 380px) {

    .produto-resumo-meta {
        gap: 5px;
    }


    .produto-meta-item {
        font-size: 11px;
    }


    .produto-btn {
        font-size: 12px;
    }
}

</style>


<header class="header">

    <div>

        <h2>Produtos</h2>

        <p style="
            color:#94a3b8;
            font-size:14px;
        ">
            Catálogo, fornecedores, custos e controle de estoque
        </p>

    </div>

</header>


<?= $mensagem ?>


<!-- ==========================================================
     FORMULÁRIO
========================================================== -->

<div
    class="table-container"
    style="margin-bottom:30px;"
>

    <h3 style="margin-bottom:15px;">

        <?= $produto_editar
            ? '✏️ Editar Produto'
            : '+ Novo Produto'
        ?>

    </h3>


    <form
        method="POST"
        action="index.php?page=produtos"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(csrf_token()) ?>"
        >


        <?php if ($produto_editar): ?>

            <input
                type="hidden"
                name="id"
                value="<?= (int)$produto_editar['id'] ?>"
            >

        <?php endif; ?>


        <div
            style="
                display:flex;
                gap:15px;
                flex-wrap:wrap;
                margin-bottom:15px;
            "
        >

            <div
                style="
                    flex:2;
                    min-width:200px;
                "
            >

                <label
                    style="
                        display:block;
                        font-size:13px;
                        color:#94a3b8;
                        margin-bottom:5px;
                    "
                >
                    Nome do Produto
                </label>

                <input
                    type="text"
                    name="nome"
                    value="<?= $produto_editar
                        ? htmlspecialchars($produto_editar['nome'])
                        : ''
                    ?>"
                    required
                    style="
                        width:100%;
                        padding:10px;
                    "
                >

            </div>


            <div
                style="
                    flex:1.5;
                    min-width:160px;
                "
            >

                <label
                    style="
                        display:block;
                        font-size:13px;
                        color:#94a3b8;
                        margin-bottom:5px;
                    "
                >
                    Fornecedor

                    <span
                        style="
                            color:#71717a;
                            font-size:11px;
                        "
                    >
                        (Opcional)
                    </span>

                </label>

                <input
                    type="text"
                    name="fornecedor"
                    placeholder="Ex: Mercado Livre, Shopee"
                    value="<?= $produto_editar
                        ? htmlspecialchars($produto_editar['fornecedor'] ?? '')
                        : ''
                    ?>"
                    style="
                        width:100%;
                        padding:10px;
                    "
                >

            </div>


            <div
                style="
                    flex:1;
                    min-width:110px;
                "
            >

                <label
                    style="
                        display:block;
                        font-size:13px;
                        color:#94a3b8;
                        margin-bottom:5px;
                    "
                >
                    Custo (R$)
                </label>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="preco_custo"
                    value="<?= $produto_editar
                        ? htmlspecialchars($produto_editar['preco_custo'])
                        : ''
                    ?>"
                    required
                    style="
                        width:100%;
                        padding:10px;
                    "
                >

            </div>


            <div
                style="
                    flex:1;
                    min-width:110px;
                "
            >

                <label
                    style="
                        display:block;
                        font-size:13px;
                        color:#94a3b8;
                        margin-bottom:5px;
                    "
                >
                    Venda (R$)
                </label>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="preco_venda"
                    value="<?= $produto_editar
                        ? htmlspecialchars($produto_editar['preco_venda'])
                        : ''
                    ?>"
                    required
                    style="
                        width:100%;
                        padding:10px;
                    "
                >

            </div>


            <div
                style="
                    flex:1;
                    min-width:100px;
                "
            >

                <label
                    style="
                        display:block;
                        font-size:13px;
                        color:#94a3b8;
                        margin-bottom:5px;
                    "
                >
                    Estoque
                </label>

                <input
                    type="number"
                    min="0"
                    name="estoque"
                    value="<?= $produto_editar
                        ? htmlspecialchars($produto_editar['estoque'])
                        : '0'
                    ?>"
                    required
                    style="
                        width:100%;
                        padding:10px;
                    "
                >

            </div>

        </div>


        <!-- ==================================================
             BOTÕES
        ================================================== -->

        <div
            style="
                display:flex;
                gap:10px;
                flex-wrap:wrap;
            "
        >

            <button
                type="submit"
                name="salvar_produto"
                style="
                    padding:11px 24px;
                    cursor:pointer;
                "
            >

                <?= $produto_editar
                    ? 'Atualizar Produto'
                    : 'Salvar Produto'
                ?>

            </button>


            <?php if ($produto_editar): ?>

                <a
                    href="index.php?page=produtos"
                    style="
                        background:#18181b;
                        color:#cbd5e1;
                        text-decoration:none;
                        padding:11px 16px;
                        border-radius:6px;
                        font-size:14px;
                        border:1px solid #27272a;
                    "
                >
                    Cancelar
                </a>

            <?php endif; ?>

        </div>

    </form>

</div>


<!-- ==========================================================
     PRODUTOS CADASTRADOS
========================================================== -->

<div class="table-container">

    <div
        style="
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:15px;
            flex-wrap:wrap;
            gap:10px;
        "
    >

        <div>

            <h3 style="margin-bottom:4px;">
                Produtos Cadastrados
            </h3>

            <span
                style="
                    color:#71717a;
                    font-size:12px;
                "
            >
                <?= $total_produtos ?>
                <?= $total_produtos == 1 ? 'produto' : 'produtos' ?>
            </span>

        </div>


        <!-- ==================================================
             BUSCA
        ================================================== -->

        <form
            method="GET"
            action="index.php"
            style="
                display:flex;
                gap:8px;
                max-width:100%;
            "
        >

            <input
                type="hidden"
                name="page"
                value="produtos"
            >

            <input
                type="text"
                name="busca"
                placeholder="🔍 Buscar..."
                value="<?= htmlspecialchars($busca) ?>"
                style="
                    padding:8px 12px;
                    font-size:13px;
                    min-width:180px;
                "
            >

            <button
                type="submit"
                style="
                    padding:8px 12px;
                    cursor:pointer;
                "
            >
                Buscar
            </button>


            <?php if (!empty($busca)): ?>

                <a
                    href="index.php?page=produtos"
                    style="
                        color:#ef4444;
                        text-decoration:none;
                        font-size:13px;
                        align-self:center;
                    "
                >
                    Limpar
                </a>

            <?php endif; ?>

        </form>

    </div>


    <!-- ======================================================
         LISTA DE PRODUTOS
    ======================================================= -->

    <?php if (count($produtos) > 0): ?>

        <div class="produtos-lista">

            <?php foreach ($produtos as $p): ?>

                <?php

                $custo = (float)$p['preco_custo'];

                $venda = (float)$p['preco_venda'];

                $estoque_atual = (int)$p['estoque'];

                $margem = $venda > 0
                    ? ((($venda - $custo) / $venda) * 100)
                    : 0;

                ?>


                <div class="produto-card">


                    <!-- ==================================================
                         RESUMO
                    ================================================== -->

                    <button
                        type="button"
                        class="produto-resumo"
                        onclick="toggleProduto(this)"
                        aria-expanded="false"
                    >

                        <div class="produto-info-principal">

                            <div class="produto-nome">
                                <?= htmlspecialchars($p['nome']) ?>
                            </div>

                        </div>


                        <div class="produto-resumo-meta">

                            <div class="produto-meta-item">

                                Estoque:

                                <span class="produto-meta-valor">
                                    <?= $estoque_atual ?> un.
                                </span>

                            </div>


                            <div class="produto-meta-item">

                                <span class="produto-meta-valor">
                                    R$
                                    <?= number_format(
                                        $venda,
                                        2,
                                        ',',
                                        '.'
                                    ) ?>
                                </span>

                            </div>

                        </div>


                        <span class="produto-seta">
                            ▼
                        </span>

                    </button>


                    <!-- ==================================================
                         DETALHES OCULTOS
                    ================================================== -->

                    <div class="produto-detalhes">


                        <div class="produto-detalhes-linha">

                            <span class="produto-detalhes-label">
                                Fornecedor
                            </span>

                            <span class="produto-detalhes-valor">

                                <?php if (!empty($p['fornecedor'])): ?>

                                    <?= htmlspecialchars($p['fornecedor']) ?>

                                <?php else: ?>

                                    <span style="color:#52525b;">
                                        —
                                    </span>

                                <?php endif; ?>

                            </span>

                        </div>


                        <div class="produto-detalhes-linha">

                            <span class="produto-detalhes-label">
                                Estoque
                            </span>

                            <span class="produto-detalhes-valor">
                                <?= $estoque_atual ?> un.
                            </span>

                        </div>


                        <div class="produto-detalhes-linha">

                            <span class="produto-detalhes-label">
                                Custo médio
                            </span>

                            <span class="produto-detalhes-valor">
                                R$
                                <?= number_format(
                                    $custo,
                                    2,
                                    ',',
                                    '.'
                                ) ?>
                            </span>

                        </div>


                        <div class="produto-detalhes-linha">

                            <span class="produto-detalhes-label">
                                Preço sugerido
                            </span>

                            <span class="produto-detalhes-valor">
                                R$
                                <?= number_format(
                                    $venda,
                                    2,
                                    ',',
                                    '.'
                                ) ?>
                            </span>

                        </div>


                        <div class="produto-detalhes-linha">

                            <span class="produto-detalhes-label">
                                Margem estimada
                            </span>

                            <span
                                class="produto-detalhes-valor"
                                style="
                                    color:#34d399;
                                    font-weight:500;
                                "
                            >
                                <?= number_format(
                                    $margem,
                                    1,
                                    ',',
                                    '.'
                                ) ?>%
                            </span>

                        </div>


                        <!-- ==================================================
                             AÇÕES
                        ================================================== -->

                        <div class="produto-acoes">


                            <a
                                href="index.php?page=produtos&acao=editar&id=<?= (int)$p['id'] ?>"
                                class="produto-btn produto-btn-editar"
                            >
                                ✏️ Editar
                            </a>


                            <form
                                method="POST"
                                action="index.php?page=produtos"
                                style="
                                    flex:0 0 auto;
                                    margin:0;
                                "
                                onsubmit="return confirmarExclusaoProduto(event, this);"
                            >

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= htmlspecialchars(csrf_token()) ?>"
                                >


                                <input
                                    type="hidden"
                                    name="deletar_produto"
                                    value="1"
                                >


                                <input
                                    type="hidden"
                                    name="id_produto"
                                    value="<?= (int)$p['id'] ?>"
                                >


                                <button
                                    type="submit"
                                    class="produto-btn produto-btn-excluir"
                                >
                                    🗑️ Excluir
                                </button>

                            </form>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>


    <?php else: ?>

        <div
            style="
                padding:35px 15px;
                color:#94a3b8;
                text-align:center;
            "
        >

            <?php if (!empty($busca)): ?>

                Nenhum produto encontrado para
                <strong>
                    "<?= htmlspecialchars($busca) ?>"
                </strong>.

            <?php else: ?>

                Nenhum produto cadastrado.

            <?php endif; ?>

        </div>

    <?php endif; ?>


    <!-- ======================================================
         PAGINAÇÃO
    ======================================================= -->

    <?php if ($total_paginas > 1): ?>

        <div class="produtos-paginacao">


            <?php if ($pagina > 1): ?>

                <a
                    href="index.php?page=produtos&pagina=<?= $pagina - 1 ?><?= !empty($busca) ? '&busca=' . urlencode($busca) : '' ?>"
                >
                    ‹
                </a>

            <?php else: ?>

                <span class="pagina-desativada">
                    ‹
                </span>

            <?php endif; ?>


            <?php

            /*
             * Mostra no máximo 5 números de página.
             */

            $inicio = max(1, $pagina - 2);

            $fim = min(
                $total_paginas,
                $pagina + 2
            );

            if ($inicio > 1):

            ?>

                <a
                    href="index.php?page=produtos&pagina=1<?= !empty($busca) ? '&busca=' . urlencode($busca) : '' ?>"
                >
                    1
                </a>

                <?php if ($inicio > 2): ?>

                    <span style="color:#52525b;">
                        ...
                    </span>

                <?php endif; ?>

            <?php endif; ?>


            <?php for ($i = $inicio; $i <= $fim; $i++): ?>

                <?php if ($i == $pagina): ?>

                    <span class="pagina-atual">
                        <?= $i ?>
                    </span>

                <?php else: ?>

                    <a
                        href="index.php?page=produtos&pagina=<?= $i ?><?= !empty($busca) ? '&busca=' . urlencode($busca) : '' ?>"
                    >
                        <?= $i ?>
                    </a>

                <?php endif; ?>

            <?php endfor; ?>


            <?php if ($fim < $total_paginas): ?>

                <?php if ($fim < $total_paginas - 1): ?>

                    <span style="color:#52525b;">
                        ...
                    </span>

                <?php endif; ?>


                <a
                    href="index.php?page=produtos&pagina=<?= $total_paginas ?><?= !empty($busca) ? '&busca=' . urlencode($busca) : '' ?>"
                >
                    <?= $total_paginas ?>
                </a>

            <?php endif; ?>


            <?php if ($pagina < $total_paginas): ?>

                <a
                    href="index.php?page=produtos&pagina=<?= $pagina + 1 ?><?= !empty($busca) ? '&busca=' . urlencode($busca) : '' ?>"
                >
                    ›
                </a>

            <?php else: ?>

                <span class="pagina-desativada">
                    ›
                </span>

            <?php endif; ?>

        </div>


        <div
            style="
                text-align:center;
                margin-top:10px;
                color:#52525b;
                font-size:11px;
            "
        >
            Página <?= $pagina ?> de <?= $total_paginas ?>
        </div>

    <?php endif; ?>

</div>


<!-- ==========================================================
     JAVASCRIPT
========================================================== -->

<script>

function toggleProduto(botao) {

    const card = botao.closest('.produto-card');

    if (!card) {
        return;
    }

    const aberto = card.classList.toggle('aberto');

    botao.setAttribute(
        'aria-expanded',
        aberto ? 'true' : 'false'
    );

}


function confirmarExclusaoProduto(event, form) {

    event.preventDefault();

    Swal.fire({

        title: 'Excluir produto?',

        text: 'Se este produto possuir vendas ou compras, ele será arquivado para preservar o histórico.',

        icon: 'warning',

        showCancelButton: true,

        confirmButtonColor: '#ef4444',

        cancelButtonColor: '#27272a',

        confirmButtonText: 'Sim, excluir',

        cancelButtonText: 'Cancelar',

        background: '#09090b',

        color: '#f8fafc',

        customClass: {
            popup: 'border-modal-dark'
        }

    }).then(function(result) {

        if (result.isConfirmed) {

            form.submit();

        }

    });

    return false;

}

</script>