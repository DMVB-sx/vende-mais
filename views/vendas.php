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

        validar_csrf();

        $id_venda = (int)($_POST['id_venda'] ?? 0);

        if ($id_venda <= 0) {
            throw new Exception('Venda inválida.');
        }


        $stmtVenda = $pdo->prepare("
            SELECT
                id,
                produto_id,
                quantidade
            FROM vendas
            WHERE id = ?
              AND empresa_id = ?
            LIMIT 1
        ");

        $stmtVenda->execute([
            $id_venda,
            $empresa_id
        ]);

        $venda = $stmtVenda->fetch(PDO::FETCH_ASSOC);


        if (!$venda) {
            throw new Exception('Venda não encontrada.');
        }


        $pdo->beginTransaction();


        /*
        | Devolve quantidade ao estoque
        */

        $stmtEstoque = $pdo->prepare("
            UPDATE produtos
            SET estoque = estoque + ?
            WHERE id = ?
              AND empresa_id = ?
        ");

        $stmtEstoque->execute([
            (int)$venda['quantidade'],
            (int)$venda['produto_id'],
            $empresa_id
        ]);


        if ($stmtEstoque->rowCount() === 0) {
            throw new Exception(
                'Não foi possível devolver o produto ao estoque.'
            );
        }


        /*
        | Exclui a venda
        */

        $stmtExcluir = $pdo->prepare("
            DELETE FROM vendas
            WHERE id = ?
              AND empresa_id = ?
        ");

        $stmtExcluir->execute([
            $id_venda,
            $empresa_id
        ]);


        if ($stmtExcluir->rowCount() === 0) {
            throw new Exception(
                'Não foi possível cancelar a venda.'
            );
        }


        $pdo->commit();


        $mensagem = '
            <div class="alerta-sucesso">
                <span>✅</span>
                <div>
                    <strong>Venda cancelada com sucesso!</strong>
                    <br>
                    <small>
                        A quantidade foi devolvida ao estoque.
                    </small>
                </div>
            </div>
        ';


    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log(
            'Erro ao cancelar venda: ' .
            $e->getMessage()
        );


        $mensagem = '
            <div class="alerta-erro">
                ⚠️ Não foi possível cancelar a venda.
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

        validar_csrf();


        $id_venda =
            (int)($_POST['id_venda'] ?? 0);


        $produto_id =
            (int)($_POST['produto_id'] ?? 0);


        $canal =
            trim($_POST['canal'] ?? '');


        $forma_pagamento =
            trim($_POST['forma_pagamento'] ?? '');


        $quantidade =
            (int)($_POST['quantidade'] ?? 0);


        $preco_venda =
            (float)($_POST['preco_venda'] ?? 0);


        $taxas_e_frete =
            (float)($_POST['taxas_e_frete'] ?? 0);


        if ($produto_id <= 0) {
            throw new Exception(
                'Selecione um produto.'
            );
        }


        if ($quantidade <= 0) {
            throw new Exception(
                'A quantidade deve ser maior que zero.'
            );
        }


        if ($preco_venda < 0) {
            throw new Exception(
                'Preço de venda inválido.'
            );
        }


        if ($taxas_e_frete < 0) {
            throw new Exception(
                'Taxas/frete inválidos.'
            );
        }


        /*
        |--------------------------------------------------------------------------
        | EDIÇÃO
        |--------------------------------------------------------------------------
        */

        if ($id_venda > 0) {

            $stmtVendaOld = $pdo->prepare("
                SELECT *
                FROM vendas
                WHERE id = ?
                  AND empresa_id = ?
                LIMIT 1
            ");

            $stmtVendaOld->execute([
                $id_venda,
                $empresa_id
            ]);


            $vendaAntiga =
                $stmtVendaOld->fetch(PDO::FETCH_ASSOC);


            if (!$vendaAntiga) {
                throw new Exception(
                    'Venda não encontrada.'
                );
            }


            $stmtProdutoNovo = $pdo->prepare("
                SELECT
                    id,
                    nome,
                    preco_custo,
                    estoque
                FROM produtos
                WHERE id = ?
                  AND empresa_id = ?
                  AND ativo = TRUE
                LIMIT 1
            ");

            $stmtProdutoNovo->execute([
                $produto_id,
                $empresa_id
            ]);


            $produtoNovo =
                $stmtProdutoNovo->fetch(PDO::FETCH_ASSOC);


            if (!$produtoNovo) {
                throw new Exception(
                    'Produto não encontrado ou inativo.'
                );
            }


            $pdo->beginTransaction();


            /*
            | Devolve a venda antiga
            */

            $stmtDevolver = $pdo->prepare("
                UPDATE produtos
                SET estoque = estoque + ?
                WHERE id = ?
                  AND empresa_id = ?
            ");

            $stmtDevolver->execute([
                (int)$vendaAntiga['quantidade'],
                (int)$vendaAntiga['produto_id'],
                $empresa_id
            ]);


            /*
            | Busca estoque atualizado
            */

            $stmtProdutoAtualizado = $pdo->prepare("
                SELECT
                    id,
                    preco_custo,
                    estoque
                FROM produtos
                WHERE id = ?
                  AND empresa_id = ?
                  AND ativo = TRUE
                LIMIT 1
            ");

            $stmtProdutoAtualizado->execute([
                $produto_id,
                $empresa_id
            ]);


            $produtoAtualizado =
                $stmtProdutoAtualizado->fetch(PDO::FETCH_ASSOC);


            if (!$produtoAtualizado) {
                throw new Exception(
                    'Produto não encontrado.'
                );
            }


            $estoqueDisponivel =
                (int)$produtoAtualizado['estoque'];


            if ($estoqueDisponivel < $quantidade) {

                throw new Exception(
                    'Estoque insuficiente! Disponível: ' .
                    $estoqueDisponivel .
                    ' un.'
                );
            }


            $custo_unitario =
                (float)$produtoAtualizado['preco_custo'];


            $valor_total =
                $preco_venda * $quantidade;


            $custo_total =
                $custo_unitario * $quantidade;


            $lucro_liquido =
                $valor_total
                - $custo_total
                - $taxas_e_frete;


            /*
            | Atualiza venda
            */

            $stmtUpdate = $pdo->prepare("
                UPDATE vendas
                SET
                    produto_id = ?,
                    canal = ?,
                    forma_pagamento = ?,
                    quantidade = ?,
                    preco_venda = ?,
                    taxas_e_frete = ?,
                    custo_produto = ?,
                    lucro_liquido = ?,
                    valor_total = ?
                WHERE id = ?
                  AND empresa_id = ?
            ");

            $stmtUpdate->execute([
                $produto_id,
                $canal,
                $forma_pagamento,
                $quantidade,
                $preco_venda,
                $taxas_e_frete,
                $custo_total,
                $lucro_liquido,
                $valor_total,
                $id_venda,
                $empresa_id
            ]);


            /*
            | Abate nova quantidade
            */

            $stmtAbater = $pdo->prepare("
                UPDATE produtos
                SET estoque = estoque - ?
                WHERE id = ?
                  AND empresa_id = ?
            ");

            $stmtAbater->execute([
                $quantidade,
                $produto_id,
                $empresa_id
            ]);


            $pdo->commit();


            $mensagem = '
                <div class="alerta-sucesso">
                    <span>✏️</span>
                    <div>
                        <strong>
                            Venda atualizada com sucesso!
                        </strong>
                    </div>
                </div>
            ';
        }


        /*
        |--------------------------------------------------------------------------
        | NOVA VENDA
        |--------------------------------------------------------------------------
        */

        else {

            $stmtProduto = $pdo->prepare("
                SELECT
                    id,
                    nome,
                    preco_custo,
                    estoque
                FROM produtos
                WHERE id = ?
                  AND empresa_id = ?
                  AND ativo = TRUE
                LIMIT 1
            ");

            $stmtProduto->execute([
                $produto_id,
                $empresa_id
            ]);


            $produto =
                $stmtProduto->fetch(PDO::FETCH_ASSOC);


            if (!$produto) {
                throw new Exception(
                    'Produto não encontrado ou inativo.'
                );
            }


            $estoqueAtual =
                (int)$produto['estoque'];


            if ($estoqueAtual < $quantidade) {

                throw new Exception(
                    'Estoque insuficiente! Estoque atual: ' .
                    $estoqueAtual .
                    ' un.'
                );
            }


            $custo_unitario =
                (float)$produto['preco_custo'];


            $valor_total =
                $preco_venda * $quantidade;


            $custo_total =
                $custo_unitario * $quantidade;


            $lucro_liquido =
                $valor_total
                - $custo_total
                - $taxas_e_frete;


            $pdo->beginTransaction();


            /*
            | Registra venda
            */

            $stmtVenda = $pdo->prepare("
                INSERT INTO vendas (
                    empresa_id,
                    produto_id,
                    canal,
                    forma_pagamento,
                    quantidade,
                    preco_venda,
                    taxas_e_frete,
                    custo_produto,
                    lucro_liquido,
                    valor_total
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmtVenda->execute([
                $empresa_id,
                $produto_id,
                $canal,
                $forma_pagamento,
                $quantidade,
                $preco_venda,
                $taxas_e_frete,
                $custo_total,
                $lucro_liquido,
                $valor_total
            ]);


            /*
            | Abate estoque
            */

            $stmtEstoque = $pdo->prepare("
                UPDATE produtos
                SET estoque = estoque - ?
                WHERE id = ?
                  AND empresa_id = ?
            ");

            $stmtEstoque->execute([
                $quantidade,
                $produto_id,
                $empresa_id
            ]);


            $pdo->commit();


            $mensagem = '
                <div class="alerta-sucesso">
                    <span>✅</span>
                    <div>
                        <strong>
                            Venda registrada com sucesso!
                        </strong>
                        <br>
                        <small>
                            Lucro gerado: R$ ' .
                            number_format(
                                $lucro_liquido,
                                2,
                                ',',
                                '.'
                            ) .
                        '</small>
                    </div>
                </div>
            ';
        }


    } catch (Throwable $e) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }


        error_log(
            'Erro ao salvar venda: ' .
            $e->getMessage()
        );


        $mensagem = '
            <div class="alerta-erro">
                ⚠️ ' .
                htmlspecialchars(
                    $e->getMessage()
                ) .
            '
            </div>
        ';
    }
}


/*
|--------------------------------------------------------------------------
| 3. BUSCAR VENDA PARA EDIÇÃO
|--------------------------------------------------------------------------
*/

$venda_editar = null;


if (
    isset($_GET['acao']) &&
    $_GET['acao'] === 'editar' &&
    isset($_GET['id'])
) {

    $id_editar =
        (int)$_GET['id'];


    $stmtEd = $pdo->prepare("
        SELECT *
        FROM vendas
        WHERE id = ?
          AND empresa_id = ?
        LIMIT 1
    ");

    $stmtEd->execute([
        $id_editar,
        $empresa_id
    ]);


    $venda_editar =
        $stmtEd->fetch(PDO::FETCH_ASSOC);
}


/*
|--------------------------------------------------------------------------
| 4. PRODUTOS
|--------------------------------------------------------------------------
*/

$stmtProd = $pdo->prepare("
    SELECT
        id,
        nome,
        fornecedor,
        preco_venda,
        estoque
    FROM produtos
    WHERE empresa_id = ?
      AND ativo = TRUE
    ORDER BY nome ASC
");

$stmtProd->execute([
    $empresa_id
]);


$produtos =
    $stmtProd->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| 5. FILTRO POR CANAL
|--------------------------------------------------------------------------
*/

$canal_filtro =
    trim($_GET['canal_filtro'] ?? '');


$paramsVendas = [
    $empresa_id
];


$sql_canal = '';


if (!empty($canal_filtro)) {

    $sql_canal =
        ' AND v.canal = ? ';

    $paramsVendas[] =
        $canal_filtro;
}


/*
|--------------------------------------------------------------------------
| 6. PAGINAÇÃO
|--------------------------------------------------------------------------
*/

$por_pagina = 15;


$pagina_atual =
    max(
        1,
        (int)($_GET['pagina'] ?? 1)
    );


/*
| Conta total
*/

$stmtTotal = $pdo->prepare("
    SELECT COUNT(*)
    FROM vendas v
    WHERE v.empresa_id = ?
    {$sql_canal}
");

$stmtTotal->execute(
    $paramsVendas
);


$total_vendas =
    (int)$stmtTotal->fetchColumn();


$total_paginas =
    max(
        1,
        (int)ceil(
            $total_vendas / $por_pagina
        )
    );


if ($pagina_atual > $total_paginas) {
    $pagina_atual = $total_paginas;
}


$offset =
    ($pagina_atual - 1) * $por_pagina;


/*
|--------------------------------------------------------------------------
| 7. HISTÓRICO
|--------------------------------------------------------------------------
*/

$paramsHistorico =
    $paramsVendas;


$paramsHistorico[] =
    $por_pagina;


$paramsHistorico[] =
    $offset;


$stmtVendas = $pdo->prepare("
    SELECT
        v.*,
        p.nome AS produto_nome,
        p.fornecedor AS produto_fornecedor
    FROM vendas v
    LEFT JOIN produtos p
        ON v.produto_id = p.id
    WHERE v.empresa_id = ?
    {$sql_canal}
    ORDER BY v.id DESC
    LIMIT ? OFFSET ?
");


$stmtVendas->execute(
    $paramsHistorico
);


$historico_vendas =
    $stmtVendas->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| FUNÇÕES AUXILIARES
|--------------------------------------------------------------------------
*/

function formatarPagamentoVenda($pagamento)
{

    $pagamentos = [

        'pix' =>
            'PIX',

        'cartao_credito' =>
            'Cartão de Crédito',

        'cartao_debito' =>
            'Cartão de Débito',

        'dinheiro' =>
            'Dinheiro'

    ];


    return $pagamentos[$pagamento]
        ?? strtoupper($pagamento ?? '');
}

?>

<header class="header">

    <div>

        <h2>
            <?= $venda_editar
                ? 'Editar venda'
                : 'Nova venda'
            ?>
        </h2>

        <p
            style="
                color:#94a3b8;
                font-size:14px;
            "
        >
            Controle de vendas com recálculo automático
            de estoque e margem
        </p>

    </div>

</header>


<?= $mensagem ?>


<!-- ============================================================
     FORMULÁRIO
============================================================ -->

<div
    class="table-container venda-form-container"
>

    <h3>

        <?= $venda_editar
            ? '✏️ Alterar Dados da Venda'
            : '+ Lançar Venda'
        ?>

    </h3>


    <form
        method="POST"
        action="index.php?page=vendas"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                csrf_token()
            ) ?>"
        >


        <?php if ($venda_editar): ?>

            <input
                type="hidden"
                name="id_venda"
                value="<?= (int)$venda_editar['id'] ?>"
            >

        <?php endif; ?>


        <!-- PRODUTO -->

        <div class="campo-venda">

            <label>
                Selecione o Produto
            </label>


            <select
                name="produto_id"
                id="select_produto"
                required
                onchange="atualizarDadosProduto()"
            >

                <option value="">
                    -- Escolha um produto --
                </option>


                <?php foreach ($produtos as $p): ?>

                    <?php

                    $info_forn =
                        !empty($p['fornecedor'])
                            ? ' | Forn: ' .
                                $p['fornecedor']
                            : '';

                    ?>


                    <option
                        value="<?= (int)$p['id'] ?>"
                        data-preco="<?= htmlspecialchars(
                            $p['preco_venda']
                        ) ?>"
                        data-estoque="<?= (int)$p['estoque'] ?>"
                        <?= (
                            $venda_editar &&
                            (int)$venda_editar['produto_id']
                            === (int)$p['id']
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= htmlspecialchars(
                            $p['nome']
                        ) ?>

                        <?= htmlspecialchars(
                            $info_forn
                        ) ?>

                        (Estoque:
                        <?= (int)$p['estoque'] ?>
                        un.)

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <!-- CANAL / PAGAMENTO -->

        <div class="linha-venda">

            <div class="campo-venda">

                <label>
                    Canal de Venda
                </label>


                <select name="canal">

                    <option
                        value="WhatsApp"
                        <?= (
                            $venda_editar &&
                            $venda_editar['canal']
                            === 'WhatsApp'
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >
                        WhatsApp
                    </option>


                    <option
                        value="Instagram"
                        <?= (
                            $venda_editar &&
                            $venda_editar['canal']
                            === 'Instagram'
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Instagram
                    </option>


                    <option
                        value="Loja Física"
                        <?= (
                            $venda_editar &&
                            $venda_editar['canal']
                            === 'Loja Física'
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Loja Física
                    </option>


                    <option
                        value="Site"
                        <?= (
                            $venda_editar &&
                            $venda_editar['canal']
                            === 'Site'
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Site / E-commerce
                    </option>

                </select>

            </div>


            <div class="campo-venda">

                <label>
                    Forma de Pagamento
                </label>


                <select
                    name="forma_pagamento"
                >

                    <option
                        value="pix"
                        <?= (
                            $venda_editar &&
                            $venda_editar['forma_pagamento']
                            === 'pix'
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Pix
                    </option>


                    <option
                        value="cartao_credito"
                        <?= (
                            $venda_editar &&
                            $venda_editar['forma_pagamento']
                            === 'cartao_credito'
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Cartão de Crédito
                    </option>


                    <option
                        value="cartao_debito"
                        <?= (
                            $venda_editar &&
                            $venda_editar['forma_pagamento']
                            === 'cartao_debito'
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Cartão de Débito
                    </option>


                    <option
                        value="dinheiro"
                        <?= (
                            $venda_editar &&
                            $venda_editar['forma_pagamento']
                            === 'dinheiro'
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >
                        Dinheiro
                    </option>

                </select>

            </div>

        </div>


        <!-- QUANTIDADE / PREÇO / TAXAS -->

        <div class="linha-venda">

            <div class="campo-venda">

                <label>
                    Quantidade
                </label>


                <input
                    type="number"
                    name="quantidade"
                    id="venda_qtd"
                    value="<?= $venda_editar
                        ? (int)$venda_editar['quantidade']
                        : '1'
                    ?>"
                    min="1"
                    required
                >

            </div>


            <div class="campo-venda">

                <label>
                    Preço Un. (R$)
                </label>


                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="preco_venda"
                    id="venda_preco"
                    value="<?= $venda_editar
                        ? htmlspecialchars(
                            $venda_editar['preco_venda']
                        )
                        : ''
                    ?>"
                    required
                >

            </div>


            <div class="campo-venda">

                <label>
                    Taxas/Frete (R$)
                </label>


                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="taxas_e_frete"
                    value="<?= $venda_editar
                        ? htmlspecialchars(
                            $venda_editar['taxas_e_frete']
                        )
                        : '0.00'
                    ?>"
                >

            </div>

        </div>


        <!-- BOTÕES -->

        <div class="botoes-venda">

            <button
                type="submit"
                name="salvar_venda"
            >

                <?= $venda_editar
                    ? 'Atualizar Venda'
                    : 'Confirmar Venda'
                ?>

            </button>


            <?php if ($venda_editar): ?>

                <a
                    href="index.php?page=vendas"
                >
                    Cancelar
                </a>

            <?php endif; ?>

        </div>

    </form>

</div>


<!-- ============================================================
     HISTÓRICO
============================================================ -->

<div class="table-container historico-vendas">

    <div class="historico-topo">

        <h3>
            Últimas Vendas
        </h3>


        <form
            method="GET"
            action="index.php"
        >

            <input
                type="hidden"
                name="page"
                value="vendas"
            >


            <select
                name="canal_filtro"
                onchange="this.form.submit()"
            >

                <option value="">
                    Todos os Canais
                </option>


                <option
                    value="WhatsApp"
                    <?= $canal_filtro === 'WhatsApp'
                        ? 'selected'
                        : ''
                    ?>
                >
                    WhatsApp
                </option>


                <option
                    value="Instagram"
                    <?= $canal_filtro === 'Instagram'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Instagram
                </option>


                <option
                    value="Loja Física"
                    <?= $canal_filtro === 'Loja Física'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Loja Física
                </option>


                <option
                    value="Site"
                    <?= $canal_filtro === 'Site'
                        ? 'selected'
                        : ''
                    ?>
                >
                    Site / E-commerce
                </option>

            </select>

        </form>

    </div>


    <!-- ========================================================
         DESKTOP
    ========================================================= -->

    <div class="vendas-desktop">

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

                    <th
                        style="text-align:center;"
                    >
                        Ações
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php if (!empty($historico_vendas)): ?>

                    <?php foreach (
                        $historico_vendas as $v
                    ): ?>

                        <tr>

                            <td>

                                <?= !empty(
                                    $v['data_venda']
                                )
                                    ? date(
                                        'd/m/Y H:i',
                                        strtotime(
                                            $v['data_venda']
                                        )
                                    )
                                    : '—'
                                ?>

                            </td>


                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $v['produto_nome']
                                        ??
                                        'Produto removido'
                                    ) ?>

                                </strong>

                                <span class="qtd-venda">

                                    (<?= (int)$v['quantidade'] ?>x)

                                </span>

                            </td>


                            <td
                                style="color:#a1a1aa;"
                            >

                                <?= !empty(
                                    $v['produto_fornecedor']
                                )
                                    ? htmlspecialchars(
                                        $v['produto_fornecedor']
                                    )
                                    : '—'
                                ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    $v['canal'] ?? ''
                                ) ?>

                            </td>


                            <td>

                                <?= htmlspecialchars(
                                    formatarPagamentoVenda(
                                        $v['forma_pagamento']
                                    )
                                ) ?>

                            </td>


                            <td>

                                R$
                                <?= number_format(
                                    (float)$v['valor_total'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>

                            </td>


                            <td class="text-positive">

                                <strong>

                                    R$
                                    <?= number_format(
                                        (float)$v['lucro_liquido'],
                                        2,
                                        ',',
                                        '.'
                                    ) ?>

                                </strong>

                            </td>


                            <td
                                class="acoes-venda"
                            >

                                <a
                                    href="index.php?page=vendas&acao=editar&id=<?= (int)$v['id'] ?>"
                                    title="Editar Venda"
                                >
                                    ✏️
                                </a>


                                <button
                                    type="button"
                                    title="Cancelar Venda"
                                    onclick="abrirModalCancelamento(<?= (int)$v['id'] ?>)"
                                >
                                    🗑️
                                </button>


                                <form
                                    method="POST"
                                    action="index.php?page=vendas"
                                    id="form-cancelar-<?= (int)$v['id'] ?>"
                                    style="display:none;"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= htmlspecialchars(
                                            csrf_token()
                                        ) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="id_venda"
                                        value="<?= (int)$v['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="cancelar_venda"
                                        value="1"
                                    >

                                </form>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>

                        <td
                            colspan="8"
                            class="sem-vendas"
                        >
                            Nenhuma venda encontrada.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>


    <!-- ========================================================
         MOBILE
    ========================================================= -->

    <div class="vendas-mobile">

        <?php if (!empty($historico_vendas)): ?>

            <?php foreach (
                $historico_vendas as $v
            ): ?>

                <div
                    class="venda-mobile-card"
                    data-venda="<?= (int)$v['id'] ?>"
                >

                    <div class="venda-mobile-principal">

                        <div class="venda-mobile-info">

                            <strong>

                                <?= htmlspecialchars(
                                    $v['produto_nome']
                                    ??
                                    'Produto removido'
                                ) ?>

                            </strong>


                            <span>

                                <?= !empty(
                                    $v['data_venda']
                                )
                                    ? date(
                                        'd/m/Y H:i',
                                        strtotime(
                                            $v['data_venda']
                                        )
                                    )
                                    : '—'
                                ?>

                                ·

                                <?= (int)$v['quantidade'] ?>x

                            </span>

                        </div>


                        <div class="venda-mobile-direita">

                            <strong class="text-positive">

                                R$
                                <?= number_format(
                                    (float)$v['lucro_liquido'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>

                            </strong>


                            <div class="acoes-venda-mobile">

                                <a
                                    href="index.php?page=vendas&acao=editar&id=<?= (int)$v['id'] ?>"
                                    title="Editar"
                                >
                                    ✏️
                                </a>


                                <button
                                    type="button"
                                    title="Cancelar"
                                    onclick="abrirModalCancelamento(<?= (int)$v['id'] ?>)"
                                >
                                    🗑️
                                </button>

                            </div>


                            <button
                                type="button"
                                class="btn-detalhes-venda"
                                onclick="toggleDetalhesVenda(<?= (int)$v['id'] ?>)"
                                aria-label="Mostrar detalhes"
                            >
                                <span>
                                    ›
                                </span>
                            </button>

                        </div>

                    </div>


                    <div
                        class="venda-mobile-detalhes"
                        id="detalhes-venda-<?= (int)$v['id'] ?>"
                    >

                        <div class="detalhe-venda">

                            <span>
                                Fornecedor
                            </span>

                            <strong>

                                <?= !empty(
                                    $v['produto_fornecedor']
                                )
                                    ? htmlspecialchars(
                                        $v['produto_fornecedor']
                                    )
                                    : '—'
                                ?>

                            </strong>

                        </div>


                        <div class="detalhe-venda">

                            <span>
                                Canal
                            </span>

                            <strong>

                                <?= htmlspecialchars(
                                    $v['canal'] ?? '—'
                                ) ?>

                            </strong>

                        </div>


                        <div class="detalhe-venda">

                            <span>
                                Pagamento
                            </span>

                            <strong>

                                <?= htmlspecialchars(
                                    formatarPagamentoVenda(
                                        $v['forma_pagamento']
                                    )
                                ) ?>

                            </strong>

                        </div>


                        <div class="detalhe-venda">

                            <span>
                                Preço unitário
                            </span>

                            <strong>

                                R$
                                <?= number_format(
                                    (float)$v['preco_venda'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>

                            </strong>

                        </div>


                        <div class="detalhe-venda">

                            <span>
                                Taxas/Frete
                            </span>

                            <strong>

                                R$
                                <?= number_format(
                                    (float)$v['taxas_e_frete'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>

                            </strong>

                        </div>


                        <div class="detalhe-venda">

                            <span>
                                Total da venda
                            </span>

                            <strong>

                                R$
                                <?= number_format(
                                    (float)$v['valor_total'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>

                            </strong>

                        </div>


                        <div class="detalhe-venda destaque-lucro">

                            <span>
                                Lucro líquido
                            </span>

                            <strong>

                                R$
                                <?= number_format(
                                    (float)$v['lucro_liquido'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>

                            </strong>

                        </div>

                    </div>


                    <form
                        method="POST"
                        action="index.php?page=vendas"
                        id="form-cancelar-mobile-<?= (int)$v['id'] ?>"
                        style="display:none;"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars(
                                csrf_token()
                            ) ?>"
                        >

                        <input
                            type="hidden"
                            name="id_venda"
                            value="<?= (int)$v['id'] ?>"
                        >

                        <input
                            type="hidden"
                            name="cancelar_venda"
                            value="1"
                        >

                    </form>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <div class="sem-vendas-mobile">

                Nenhuma venda encontrada.

            </div>

        <?php endif; ?>

    </div>


    <!-- ========================================================
         PAGINAÇÃO
    ========================================================= -->

    <?php if ($total_paginas > 1): ?>

        <div class="paginacao-vendas">

            <?php if ($pagina_atual > 1): ?>

                <a
                    href="index.php?page=vendas&pagina=<?= $pagina_atual - 1 ?><?= !empty($canal_filtro) ? '&canal_filtro=' . urlencode($canal_filtro) : '' ?>"
                >
                    ‹
                </a>

            <?php endif; ?>


            <span>

                Página
                <?= $pagina_atual ?>
                de
                <?= $total_paginas ?>

            </span>


            <?php if (
                $pagina_atual < $total_paginas
            ): ?>

                <a
                    href="index.php?page=vendas&pagina=<?= $pagina_atual + 1 ?><?= !empty($canal_filtro) ? '&canal_filtro=' . urlencode($canal_filtro) : '' ?>"
                >
                    ›
                </a>

            <?php endif; ?>

        </div>

    <?php endif; ?>

</div>


<!-- ============================================================
     MODAL DE CANCELAMENTO
============================================================ -->

<div
    id="modal-cancelamento"
    class="modal-confirmacao"
>

    <div class="modal-confirmacao-box">

        <div class="modal-confirmacao-icone">
            ⚠️
        </div>


        <h3>
            Cancelar venda?
        </h3>


        <p>
            Tem certeza que deseja cancelar esta venda?
        </p>


        <p class="modal-aviso">
            A quantidade vendida será devolvida ao estoque.
        </p>


        <div class="modal-confirmacao-botoes">

            <button
                type="button"
                class="btn-modal-cancelar"
                onclick="fecharModalCancelamento()"
            >
                Não, voltar
            </button>


            <button
                type="button"
                class="btn-modal-confirmar"
                onclick="confirmarCancelamento()"
            >
                Sim, cancelar venda
            </button>

        </div>

    </div>

</div>


<style>

/*
|--------------------------------------------------------------------------
| ALERTAS
|--------------------------------------------------------------------------
*/

.alerta-sucesso {

    display: flex;

    align-items: flex-start;

    gap: 12px;

    background:
        rgba(16,185,129,0.10);

    border:
        1px solid #10b981;

    color:
        #34d399;

    padding:
        13px 16px;

    border-radius:
        8px;

    margin-bottom:
        20px;

    font-size:
        14px;
}


.alerta-sucesso span {
    font-size: 18px;
}


.alerta-sucesso strong {
    color: #6ee7b7;
}


.alerta-sucesso small {
    color: #a7f3d0;
}


.alerta-erro {

    background:
        rgba(239,68,68,0.10);

    border:
        1px solid #ef4444;

    color:
        #f87171;

    padding:
        13px 16px;

    border-radius:
        8px;

    margin-bottom:
        20px;

    font-size:
        14px;
}


/*
|--------------------------------------------------------------------------
| FORMULÁRIO
|--------------------------------------------------------------------------
*/

.venda-form-container {

    margin-bottom:
        30px;

    max-width:
        650px;

}


.venda-form-container h3 {

    margin-bottom:
        15px;

}


.campo-venda {

    margin-bottom:
        15px;

    flex:
        1;

}


.campo-venda label {

    display:
        block;

    font-size:
        13px;

    color:
        #94a3b8;

    margin-bottom:
        5px;

}


.campo-venda input,
.campo-venda select {

    width:
        100%;

    padding:
        10px;

    box-sizing:
        border-box;

}


.linha-venda {

    display:
        flex;

    gap:
        15px;

    margin-bottom:
        0;

}


.botoes-venda {

    display:
        flex;

    gap:
        10px;

}


.botoes-venda button {

    padding:
        12px 24px;

    cursor:
        pointer;

    flex:
        1;

}


.botoes-venda a {

    background:
        #18181b;

    color:
        #cbd5e1;

    text-decoration:
        none;

    padding:
        12px 20px;

    border-radius:
        6px;

    font-size:
        14px;

    border:
        1px solid #27272a;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

}


/*
|--------------------------------------------------------------------------
| HISTÓRICO
|--------------------------------------------------------------------------
*/

.historico-topo {

    display:
        flex;

    justify-content:
        space-between;

    align-items:
        center;

    margin-bottom:
        15px;

    flex-wrap:
        wrap;

    gap:
        10px;

}


.historico-topo h3 {

    margin:
        0;

}


.historico-topo form {

    display:
        flex;

}


.historico-topo select {

    padding:
        8px 12px;

    font-size:
        13px;

}


.qtd-venda {

    color:
        #71717a;

    font-size:
        13px;

}


.acoes-venda {

    text-align:
        center;

    white-space:
        nowrap;

}


.acoes-venda a,
.acoes-venda button {

    text-decoration:
        none;

    margin:
        0 4px;

    background:
        none;

    border:
        none;

    cursor:
        pointer;

    padding:
        0;

    font-size:
        16px;

}


.sem-vendas {

    color:
        #94a3b8;

    text-align:
        center;

    padding:
        25px !important;

}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

.vendas-mobile {

    display:
        none;

}


.venda-mobile-card {

    background:
        #09090b;

    border:
        1px solid #27272a;

    border-radius:
        10px;

    margin-bottom:
        10px;

    overflow:
        hidden;

}


.venda-mobile-principal {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        10px;

    padding:
        13px 12px;

}


.venda-mobile-info {

    min-width:
        0;

    display:
        flex;

    flex-direction:
        column;

    gap:
        4px;

}


.venda-mobile-info strong {

    color:
        #f4f4f5;

    font-size:
        14px;

    overflow:
        hidden;

    text-overflow:
        ellipsis;

    white-space:
        nowrap;

}


.venda-mobile-info span {

    color:
        #71717a;

    font-size:
        12px;

}


.venda-mobile-direita {

    display:
        flex;

    align-items:
        center;

    gap:
        8px;

    flex-shrink:
        0;

}


.venda-mobile-direita > strong {

    font-size:
        13px;

    white-space:
        nowrap;

}


.acoes-venda-mobile {

    display:
        flex;

    align-items:
        center;

    gap:
        7px;

}


.acoes-venda-mobile a,
.acoes-venda-mobile button {

    background:
        none;

    border:
        none;

    padding:
        0;

    cursor:
        pointer;

    text-decoration:
        none;

    font-size:
        15px;

}


.btn-detalhes-venda {

    width:
        27px;

    height:
        27px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #18181b;

    border:
        1px solid #27272a;

    color:
        #a1a1aa;

    border-radius:
        6px;

    cursor:
        pointer;

}


.btn-detalhes-venda span {

    font-size:
        20px;

    line-height:
        1;

    transform:
        rotate(90deg);

    transition:
        transform 0.2s ease;

}


.venda-mobile-card.aberta
.btn-detalhes-venda span {

    transform:
        rotate(-90deg);

}


.venda-mobile-detalhes {

    display:
        none;

    border-top:
        1px solid #18181b;

    padding:
        8px 12px 12px;

}


.venda-mobile-card.aberta
.venda-mobile-detalhes {

    display:
        block;

}


.detalhe-venda {

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        10px;

    padding:
        7px 0;

    border-bottom:
        1px solid rgba(39,39,42,0.6);

}


.detalhe-venda:last-child {

    border-bottom:
        none;

}


.detalhe-venda span {

    color:
        #71717a;

    font-size:
        12px;

}


.detalhe-venda strong {

    color:
        #d4d4d8;

    font-size:
        12px;

    text-align:
        right;

}


.destaque-lucro strong {

    color:
        #34d399;

}


.sem-vendas-mobile {

    text-align:
        center;

    color:
        #94a3b8;

    padding:
        30px 15px;

}


/*
|--------------------------------------------------------------------------
| PAGINAÇÃO
|--------------------------------------------------------------------------
*/

.paginacao-vendas {

    display:
        flex;

    justify-content:
        center;

    align-items:
        center;

    gap:
        12px;

    margin-top:
        20px;

}


.paginacao-vendas a {

    width:
        32px;

    height:
        32px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #18181b;

    border:
        1px solid #27272a;

    border-radius:
        6px;

    color:
        #d4d4d8;

    text-decoration:
        none;

    font-size:
        20px;

}


.paginacao-vendas span {

    color:
        #71717a;

    font-size:
        13px;

}


/*
|--------------------------------------------------------------------------
| MODAL
|--------------------------------------------------------------------------
*/

.modal-confirmacao {

    display:
        none;

    position:
        fixed;

    inset:
        0;

    background:
        rgba(0,0,0,0.75);

    z-index:
        9999;

    align-items:
        center;

    justify-content:
        center;

    padding:
        20px;

}


.modal-confirmacao.ativo {

    display:
        flex;

}


.modal-confirmacao-box {

    width:
        100%;

    max-width:
        430px;

    background:
        #18181b;

    border:
        1px solid #27272a;

    border-radius:
        14px;

    padding:
        30px;

    text-align:
        center;

    box-shadow:
        0 20px 50px rgba(0,0,0,0.5);

    animation:
        aparecerModal 0.2s ease;

}


.modal-confirmacao-icone {

    font-size:
        38px;

    margin-bottom:
        12px;

}


.modal-confirmacao-box h3 {

    margin:
        0 0 10px;

    color:
        #f8fafc;

    font-size:
        20px;

}


.modal-confirmacao-box p {

    margin:
        8px 0;

    color:
        #cbd5e1;

    font-size:
        14px;

}


.modal-confirmacao-box
.modal-aviso {

    color:
        #f59e0b;

    font-size:
        13px;

    margin-top:
        14px;

}


.modal-confirmacao-botoes {

    display:
        flex;

    gap:
        10px;

    margin-top:
        25px;

}


.modal-confirmacao-botoes button {

    flex:
        1;

    padding:
        11px 15px;

    border-radius:
        7px;

    cursor:
        pointer;

    font-size:
        14px;

    font-weight:
        600;

}


.btn-modal-cancelar {

    background:
        #27272a;

    color:
        #cbd5e1;

    border:
        1px solid #3f3f46;

}


.btn-modal-cancelar:hover {

    background:
        #3f3f46;

}


.btn-modal-confirmar {

    background:
        #dc2626;

    color:
        white;

    border:
        1px solid #dc2626;

}


.btn-modal-confirmar:hover {

    background:
        #b91c1c;

}


@keyframes aparecerModal {

    from {

        opacity:
            0;

        transform:
            scale(0.95);

    }

    to {

        opacity:
            1;

        transform:
            scale(1);

    }

}


/*
|--------------------------------------------------------------------------
| RESPONSIVIDADE
|--------------------------------------------------------------------------
*/

@media (max-width: 700px) {

    .venda-form-container {

        max-width:
            none;

    }


    .linha-venda {

        flex-direction:
            column;

        gap:
            0;

    }


    .botoes-venda {

        flex-direction:
            column;

    }


    .botoes-venda a {

        min-height:
            42px;

        box-sizing:
            border-box;

    }


    .vendas-desktop {

        display:
            none;

    }


    .vendas-mobile {

        display:
            block;

    }


    .historico-topo {

        align-items:
            stretch;

    }


    .historico-topo form,
    .historico-topo select {

        width:
            100%;

    }


    .modal-confirmacao-box {

        padding:
            24px 20px;

    }


    .modal-confirmacao-botoes {

        flex-direction:
            column;

    }

}

</style>


<script>

/*
|--------------------------------------------------------------------------
| MODAL DE CANCELAMENTO
|--------------------------------------------------------------------------
*/

let vendaParaCancelar = null;


function abrirModalCancelamento(id) {

    vendaParaCancelar = id;


    const modal =
        document.getElementById(
            'modal-cancelamento'
        );


    if (modal) {

        modal.classList.add('ativo');

    }

}


function fecharModalCancelamento() {

    vendaParaCancelar = null;


    const modal =
        document.getElementById(
            'modal-cancelamento'
        );


    if (modal) {

        modal.classList.remove('ativo');

    }

}


function confirmarCancelamento() {

    if (!vendaParaCancelar) {
        return;
    }


    /*
    | Procura primeiro o formulário desktop.
    */

    let form =
        document.getElementById(
            'form-cancelar-' +
            vendaParaCancelar
        );


    /*
    | Se não encontrar,
    | procura o formulário mobile.
    */

    if (!form) {

        form =
            document.getElementById(
                'form-cancelar-mobile-' +
                vendaParaCancelar
            );

    }


    if (form) {

        form.submit();

    }

}


/*
|--------------------------------------------------------------------------
| FECHAR MODAL CLICANDO FORA
|--------------------------------------------------------------------------
*/

const modalCancelamento =
    document.getElementById(
        'modal-cancelamento'
    );


if (modalCancelamento) {

    modalCancelamento.addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                fecharModalCancelamento();

            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| ESC FECHA MODAL
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape' &&
            vendaParaCancelar
        ) {

            fecharModalCancelamento();

        }

    }
);


/*
|--------------------------------------------------------------------------
| DETALHES NO MOBILE
|--------------------------------------------------------------------------
*/

function toggleDetalhesVenda(id) {

    const card =
        document.querySelector(
            '.venda-mobile-card[data-venda="' +
            id +
            '"]'
        );


    if (!card) {
        return;
    }


    card.classList.toggle('aberta');

}


/*
|--------------------------------------------------------------------------
| PREENCHER PREÇO AUTOMATICAMENTE
|--------------------------------------------------------------------------
*/

function atualizarDadosProduto() {

    const select =
        document.getElementById(
            'select_produto'
        );


    const campoPreco =
        document.getElementById(
            'venda_preco'
        );


    if (
        !select ||
        !campoPreco
    ) {

        return;

    }


    const option =
        select.options[
            select.selectedIndex
        ];


    if (!option) {
        return;
    }


    const preco =
        option.getAttribute(
            'data-preco'
        );


    /*
    | Só preenche automaticamente
    | se o campo estiver vazio.
    */

    if (
        preco &&
        !campoPreco.value
    ) {

        campoPreco.value =
            preco;

    }

}


/*
|--------------------------------------------------------------------------
| INICIALIZAÇÃO
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function() {

        const select =
            document.getElementById(
                'select_produto'
            );


        if (
            select &&
            select.value
        ) {

            atualizarDadosProduto();

        }

    }
);

</script>