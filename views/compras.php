<?php

$mensagem = '';
$empresa_id = $_SESSION['empresa_id'] ?? 0;


/*
|--------------------------------------------------------------------------
| 1. EXCLUIR / CANCELAR COMPRA
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'GET' &&
    isset($_GET['acao']) &&
    $_GET['acao'] === 'deletar' &&
    isset($_GET['id'])
) {

    $id_deletar = (int) $_GET['id'];

    if ($id_deletar > 0) {

        try {

            $stmtCompOld = $pdo->prepare("
                SELECT produto_id, quantidade
                FROM compras
                WHERE id = ?
                AND empresa_id = ?
                LIMIT 1
            ");

            $stmtCompOld->execute([
                $id_deletar,
                $empresa_id
            ]);

            $compraAntiga = $stmtCompOld->fetch(PDO::FETCH_ASSOC);


            if ($compraAntiga) {

                $pdo->beginTransaction();


                /*
                |----------------------------------------------------------------------
                | Retira do estoque a quantidade que entrou pela compra
                |----------------------------------------------------------------------
                */

                $stmtEstoque = $pdo->prepare("
                    UPDATE produtos
                    SET estoque = estoque - ?
                    WHERE id = ?
                    AND empresa_id = ?
                ");

                $stmtEstoque->execute([
                    (int) $compraAntiga['quantidade'],
                    (int) $compraAntiga['produto_id'],
                    $empresa_id
                ]);


                /*
                |----------------------------------------------------------------------
                | Exclui a compra
                |----------------------------------------------------------------------
                */

                $stmtDel = $pdo->prepare("
                    DELETE FROM compras
                    WHERE id = ?
                    AND empresa_id = ?
                ");

                $stmtDel->execute([
                    $id_deletar,
                    $empresa_id
                ]);


                if ($stmtDel->rowCount() > 0) {

                    $pdo->commit();

                    $mensagem = '
                        <div class="alerta-sucesso">
                            <span>✅</span>
                            <div>
                                <strong>Compra cancelada com sucesso!</strong>
                                <br>
                                <small>
                                    A quantidade foi removida do estoque.
                                </small>
                            </div>
                        </div>
                    ';

                } else {

                    $pdo->rollBack();

                    $mensagem = '
                        <div class="alerta-erro">
                            ⚠️ Não foi possível cancelar a compra.
                        </div>
                    ';
                }

            } else {

                $mensagem = '
                    <div class="alerta-erro">
                        ⚠️ Compra não encontrada.
                    </div>
                ';
            }

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $mensagem = '
                <div class="alerta-erro">
                    ⚠️ Ocorreu um erro ao cancelar a compra.
                </div>
            ';
        }
    }
}


/*
|--------------------------------------------------------------------------
| 2. CADASTRO / EDIÇÃO DE COMPRA
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['salvar_compra'])
) {

    validar_csrf();

    $id_compra = isset($_POST['id_compra'])
        ? (int) $_POST['id_compra']
        : 0;

    $produto_id = (int) ($_POST['produto_id'] ?? 0);
    $quantidade = (int) ($_POST['quantidade'] ?? 0);
    $custo_unitario = (float) ($_POST['custo_unitario'] ?? 0);
    $frete = (float) ($_POST['frete'] ?? 0);


    if (
        $quantidade > 0 &&
        $produto_id > 0 &&
        $custo_unitario >= 0 &&
        $frete >= 0
    ) {

        $custo_real_unitario_remessa =
            $custo_unitario + ($frete / $quantidade);


        try {

            $pdo->beginTransaction();


            /*
            |--------------------------------------------------------------------------
            | EDIÇÃO
            |--------------------------------------------------------------------------
            */

            if ($id_compra > 0) {

                $stmtCOld = $pdo->prepare("
                    SELECT produto_id, quantidade
                    FROM compras
                    WHERE id = ?
                    AND empresa_id = ?
                    LIMIT 1
                ");

                $stmtCOld->execute([
                    $id_compra,
                    $empresa_id
                ]);

                $cAntiga =
                    $stmtCOld->fetch(PDO::FETCH_ASSOC);


                if (!$cAntiga) {

                    throw new Exception(
                        'Compra não encontrada.'
                    );
                }


                /*
                |----------------------------------------------------------------------
                | Desfaz estoque da compra antiga
                |----------------------------------------------------------------------
                */

                $stmtRevert = $pdo->prepare("
                    UPDATE produtos
                    SET estoque = estoque - ?
                    WHERE id = ?
                    AND empresa_id = ?
                ");

                $stmtRevert->execute([
                    (int) $cAntiga['quantidade'],
                    (int) $cAntiga['produto_id'],
                    $empresa_id
                ]);


                /*
                |----------------------------------------------------------------------
                | Busca produto atual
                |----------------------------------------------------------------------
                */

                $stmtP = $pdo->prepare("
                    SELECT estoque, preco_custo
                    FROM produtos
                    WHERE id = ?
                    AND empresa_id = ?
                    LIMIT 1
                ");

                $stmtP->execute([
                    $produto_id,
                    $empresa_id
                ]);

                $prodAtual =
                    $stmtP->fetch(PDO::FETCH_ASSOC);


                if (!$prodAtual) {

                    throw new Exception(
                        'Produto não encontrado.'
                    );
                }


                $qtd_antiga =
                    (int) $prodAtual['estoque'];

                $custo_antigo =
                    (float) $prodAtual['preco_custo'];


                $total_investido_antigo =
                    $qtd_antiga * $custo_antigo;


                $total_investido_remessa =
                    ($quantidade * $custo_unitario) + $frete;


                $nova_qtd_total =
                    $qtd_antiga + $quantidade;


                $novo_custo_medio =
                    $nova_qtd_total > 0
                        ? (
                            $total_investido_antigo +
                            $total_investido_remessa
                        ) / $nova_qtd_total
                        : $custo_real_unitario_remessa;


                /*
                |----------------------------------------------------------------------
                | Atualiza compra
                |----------------------------------------------------------------------
                */

                $stmtUpC = $pdo->prepare("
                    UPDATE compras
                    SET
                        produto_id = ?,
                        quantidade = ?,
                        custo_unitario = ?,
                        frete = ?,
                        custo_real_unitario = ?
                    WHERE id = ?
                    AND empresa_id = ?
                ");

                $stmtUpC->execute([
                    $produto_id,
                    $quantidade,
                    $custo_unitario,
                    $frete,
                    $custo_real_unitario_remessa,
                    $id_compra,
                    $empresa_id
                ]);


                /*
                |----------------------------------------------------------------------
                | Atualiza produto
                |----------------------------------------------------------------------
                */

                $stmtUpP = $pdo->prepare("
                    UPDATE produtos
                    SET
                        estoque = ?,
                        preco_custo = ?
                    WHERE id = ?
                    AND empresa_id = ?
                ");

                $stmtUpP->execute([
                    $nova_qtd_total,
                    $novo_custo_medio,
                    $produto_id,
                    $empresa_id
                ]);


                $pdo->commit();


                $mensagem = '
                    <div class="alerta-sucesso">
                        <span>✏️</span>
                        <div>
                            <strong>Compra atualizada com sucesso!</strong>
                            <br>
                            <small>
                                Novo custo médio:
                                R$ ' .
                                number_format(
                                    $novo_custo_medio,
                                    2,
                                    ',',
                                    '.'
                                )
                                . '
                            </small>
                        </div>
                    </div>
                ';


            /*
            |--------------------------------------------------------------------------
            | NOVA COMPRA
            |--------------------------------------------------------------------------
            */

            } else {

                $stmtAntigo = $pdo->prepare("
                    SELECT estoque, preco_custo
                    FROM produtos
                    WHERE id = ?
                    AND empresa_id = ?
                    LIMIT 1
                ");

                $stmtAntigo->execute([
                    $produto_id,
                    $empresa_id
                ]);

                $prodAntigo =
                    $stmtAntigo->fetch(PDO::FETCH_ASSOC);


                if (!$prodAntigo) {

                    throw new Exception(
                        'Produto não encontrado.'
                    );
                }


                $qtd_antiga =
                    (int) $prodAntigo['estoque'];

                $custo_antigo =
                    (float) $prodAntigo['preco_custo'];


                $total_investido_antigo =
                    $qtd_antiga * $custo_antigo;


                $total_investido_remessa =
                    ($quantidade * $custo_unitario) + $frete;


                $nova_qtd_total =
                    $qtd_antiga + $quantidade;


                $novo_custo_medio =
                    $nova_qtd_total > 0
                        ? (
                            $total_investido_antigo +
                            $total_investido_remessa
                        ) / $nova_qtd_total
                        : $custo_real_unitario_remessa;


                /*
                |----------------------------------------------------------------------
                | Registra compra
                |----------------------------------------------------------------------
                */

                $stmtC = $pdo->prepare("
                    INSERT INTO compras
                    (
                        empresa_id,
                        produto_id,
                        quantidade,
                        custo_unitario,
                        frete,
                        custo_real_unitario
                    )
                    VALUES (?, ?, ?, ?, ?, ?)
                ");

                $stmtC->execute([
                    $empresa_id,
                    $produto_id,
                    $quantidade,
                    $custo_unitario,
                    $frete,
                    $custo_real_unitario_remessa
                ]);


                /*
                |----------------------------------------------------------------------
                | Atualiza estoque e custo médio
                |----------------------------------------------------------------------
                */

                $stmtP = $pdo->prepare("
                    UPDATE produtos
                    SET
                        estoque = ?,
                        preco_custo = ?
                    WHERE id = ?
                    AND empresa_id = ?
                ");

                $stmtP->execute([
                    $nova_qtd_total,
                    $novo_custo_medio,
                    $produto_id,
                    $empresa_id
                ]);


                $pdo->commit();


                $mensagem = '
                    <div class="alerta-sucesso">
                        <span>✅</span>
                        <div>
                            <strong>Compra registrada com sucesso!</strong>
                            <br>
                            <small>
                                Novo custo médio:
                                R$ ' .
                                number_format(
                                    $novo_custo_medio,
                                    2,
                                    ',',
                                    '.'
                                )
                                . '
                            </small>
                        </div>
                    </div>
                ';
            }

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log($e->getMessage());

            $mensagem = '
                <div class="alerta-erro">
                    ⚠️ Não foi possível processar a compra.
                </div>
            ';
        }

    } else {

        $mensagem = '
            <div class="alerta-erro">
                ⚠️ Informe os dados da compra corretamente.
            </div>
        ';
    }
}


/*
|--------------------------------------------------------------------------
| 3. EDIÇÃO
|--------------------------------------------------------------------------
*/

$compra_editar = null;

if (
    isset($_GET['acao']) &&
    $_GET['acao'] === 'editar' &&
    isset($_GET['id'])
) {

    $id_editar = (int) $_GET['id'];

    $stmtEd = $pdo->prepare("
        SELECT *
        FROM compras
        WHERE id = ?
        AND empresa_id = ?
        LIMIT 1
    ");

    $stmtEd->execute([
        $id_editar,
        $empresa_id
    ]);

    $compra_editar =
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
        preco_custo,
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
| 5. PAGINAÇÃO DO HISTÓRICO
|--------------------------------------------------------------------------
*/

$por_pagina = 15;

$pagina_atual = isset($_GET['pagina'])
    ? max(1, (int) $_GET['pagina'])
    : 1;


$stmtTotal = $pdo->prepare("
    SELECT COUNT(*)
    FROM compras
    WHERE empresa_id = ?
");

$stmtTotal->execute([
    $empresa_id
]);

$total_compras =
    (int) $stmtTotal->fetchColumn();


$total_paginas =
    max(1, (int) ceil($total_compras / $por_pagina));


if ($pagina_atual > $total_paginas) {
    $pagina_atual = $total_paginas;
}


$offset =
    ($pagina_atual - 1) * $por_pagina;


/*
|--------------------------------------------------------------------------
| 6. HISTÓRICO DE COMPRAS
|--------------------------------------------------------------------------
*/

$stmtHist = $pdo->prepare("
    SELECT
        c.*,
        p.nome AS produto_nome
    FROM compras c
    JOIN produtos p
        ON c.produto_id = p.id
    WHERE c.empresa_id = ?
    ORDER BY c.id DESC
    LIMIT {$por_pagina} OFFSET {$offset}
");

$stmtHist->execute([
    $empresa_id
]);

$historico_compras =
    $stmtHist->fetchAll(PDO::FETCH_ASSOC);

?>


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

    background: rgba(16, 185, 129, 0.10);
    border: 1px solid #10b981;

    color: #34d399;

    padding: 13px 16px;
    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 14px;
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

    background: rgba(239, 68, 68, 0.10);
    border: 1px solid #ef4444;

    color: #f87171;

    padding: 13px 16px;
    border-radius: 8px;

    margin-bottom: 20px;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| HISTÓRICO MOBILE
|--------------------------------------------------------------------------
*/

.historico-mobile {
    display: none;
}


.compra-card {

    border: 1px solid #27272a;
    border-radius: 14px;

    margin-bottom: 12px;

    overflow: hidden;

    background: #09090b;
}


.compra-card-topo {

    display: flex;
    align-items: center;
    justify-content: space-between;

    gap: 12px;

    padding: 16px 18px;

    cursor: pointer;
}


.compra-card-info {
    min-width: 0;
    flex: 1;
}


.compra-card-produto {

    font-size: 16px;
    font-weight: 600;

    color: #f8fafc;

    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;

    margin-bottom: 5px;
}


.compra-card-data {

    color: #71717a;
    font-size: 12px;
}


.compra-card-qtd {

    color: #34d399;
    font-size: 14px;
    font-weight: 600;

    white-space: nowrap;
}


.compra-card-seta {

    color: #71717a;
    font-size: 17px;

    transition: transform 0.2s ease;

    flex-shrink: 0;
}


.compra-card.aberto .compra-card-seta {
    transform: rotate(180deg);
}


.compra-card-acoes {

    display: flex;

    gap: 8px;

    padding: 0 18px 14px;

    border-top: 1px solid #18181b;
}


.compra-card-acoes a {

    flex: 1;

    display: flex;

    justify-content: center;
    align-items: center;

    gap: 6px;

    min-height: 40px;

    border-radius: 8px;

    text-decoration: none;

    font-size: 13px;
}


.compra-editar {

    background: #18181b;

    border: 1px solid #27272a;

    color: #e4e4e7;
}


.compra-excluir {

    background: rgba(239, 68, 68, 0.08);

    border: 1px solid rgba(239, 68, 68, 0.25);

    color: #f87171;

    cursor: pointer;
}


.compra-card-detalhes {

    display: none;

    padding: 0 18px 16px;

    border-top: 1px solid #18181b;
}


.compra-card.aberto .compra-card-detalhes {
    display: block;
}


.compra-detalhe {

    display: flex;

    justify-content: space-between;

    align-items: center;

    gap: 15px;

    padding: 11px 0;

    border-bottom: 1px solid #18181b;

    font-size: 13px;
}


.compra-detalhe:last-child {
    border-bottom: none;
}


.compra-detalhe-label {
    color: #71717a;
}


.compra-detalhe-valor {
    color: #e4e4e7;
    text-align: right;
}


.compra-detalhe-valor strong {
    color: #34d399;
}


/*
|--------------------------------------------------------------------------
| PAGINAÇÃO
|--------------------------------------------------------------------------
*/

.paginacao-compras {

    display: flex;

    justify-content: center;
    align-items: center;

    gap: 6px;

    margin-top: 20px;

    flex-wrap: wrap;
}


.paginacao-compras a,
.paginacao-compras span {

    min-width: 38px;
    height: 38px;

    padding: 0 10px;

    display: inline-flex;

    align-items: center;
    justify-content: center;

    border-radius: 7px;

    text-decoration: none;

    font-size: 13px;

    border: 1px solid #27272a;

    background: #09090b;

    color: #a1a1aa;
}


.paginacao-compras a:hover {

    background: #18181b;

    color: #f8fafc;
}


.paginacao-compras .pagina-atual {

    background: #18181b;

    color: #f8fafc;

    border-color: #3f3f46;

    font-weight: 600;
}


.paginacao-info {

    color: #71717a;

    font-size: 12px;

    margin-left: 8px;
}


/*
|--------------------------------------------------------------------------
| MOBILE
|--------------------------------------------------------------------------
*/

@media (max-width: 768px) {

    /*
    | Esconde tabela
    */

    .historico-desktop {
        display: none !important;
    }


    /*
    | Mostra cards
    */

    .historico-mobile {
        display: block;
    }


    /*
    | Ajusta formulário
    */

    .form-compra-linha {

        display: block !important;

    }


    .form-compra-linha > div {

        width: 100% !important;

        margin-bottom: 15px;

    }


    /*
    | Container do formulário
    */

    .table-container {

        width: 100%;

        box-sizing: border-box;

    }


    /*
    | Paginação
    */

    .paginacao-compras {

        gap: 5px;

    }


    .paginacao-compras a,
    .paginacao-compras span {

        min-width: 34px;

        height: 34px;

        padding: 0 8px;

    }


    .paginacao-info {

        width: 100%;

        text-align: center;

        margin: 4px 0 0;

    }

}

</style>


<header class="header">

    <div>

        <h2>
            <?= $compra_editar
                ? 'Editar compra'
                : 'Nova compra'
            ?>
        </h2>

        <p style="
            color: #94a3b8;
            font-size: 14px;
        ">
            Entrada de estoque e recálculo do custo médio ponderado
        </p>

    </div>

</header>


<?= $mensagem ?>


<!-- ==========================================================
     FORMULÁRIO
========================================================== -->

<div
    class="table-container"
    style="
        margin-bottom: 30px;
        max-width: 650px;
    "
>

    <h3 style="margin-bottom: 15px;">

        <?= $compra_editar
            ? '✏️ Editar Lançamento de Compra'
            : '+ Registrar Entrada de Estoque'
        ?>

    </h3>


    <form
        method="POST"
        action="index.php?page=compras"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(csrf_token()) ?>"
        >


        <?php if ($compra_editar): ?>

            <input
                type="hidden"
                name="id_compra"
                value="<?= (int)$compra_editar['id'] ?>"
            >

        <?php endif; ?>


        <div style="margin-bottom: 15px;">

            <label style="
                display: block;
                font-size: 13px;
                color: #94a3b8;
                margin-bottom: 5px;
            ">
                Produto que está entrando
            </label>


            <select
                name="produto_id"
                id="compra_produto"
                required
                onchange="carregarDadosProduto()"
                style="
                    width: 100%;
                    padding: 10px;
                "
            >

                <option value="">
                    -- Escolha um produto --
                </option>


                <?php foreach ($produtos as $p): ?>

                    <option
                        value="<?= (int)$p['id'] ?>"
                        data-custo="<?= htmlspecialchars($p['preco_custo']) ?>"
                        data-estoque="<?= (int)$p['estoque'] ?>"
                        <?= (
                            $compra_editar &&
                            $compra_editar['produto_id'] == $p['id']
                        )
                            ? 'selected'
                            : ''
                        ?>
                    >

                        <?= htmlspecialchars($p['nome']) ?>

                        (Estoque atual:
                        <?= (int)$p['estoque'] ?>
                        un.)

                    </option>

                <?php endforeach; ?>

            </select>

        </div>


        <div
            class="form-compra-linha"
            style="
                display: flex;
                gap: 15px;
                margin-bottom: 15px;
            "
        >

            <div style="flex: 1;">

                <label style="
                    display: block;
                    font-size: 13px;
                    color: #94a3b8;
                    margin-bottom: 5px;
                ">
                    Quantidade
                </label>

                <input
                    type="number"
                    name="quantidade"
                    id="compra_qtd"
                    value="<?= $compra_editar
                        ? (int)$compra_editar['quantidade']
                        : '1'
                    ?>"
                    min="1"
                    required
                    oninput="calcularPreviaCustoMedio()"
                    style="
                        width: 100%;
                        padding: 10px;
                    "
                >

            </div>


            <div style="flex: 1;">

                <label style="
                    display: block;
                    font-size: 13px;
                    color: #94a3b8;
                    margin-bottom: 5px;
                ">
                    Custo por Un. (R$)
                </label>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="custo_unitario"
                    id="compra_custo"
                    value="<?= $compra_editar
                        ? htmlspecialchars($compra_editar['custo_unitario'])
                        : ''
                    ?>"
                    required
                    oninput="calcularPreviaCustoMedio()"
                    style="
                        width: 100%;
                        padding: 10px;
                    "
                >

            </div>


            <div style="flex: 1;">

                <label style="
                    display: block;
                    font-size: 13px;
                    color: #94a3b8;
                    margin-bottom: 5px;
                ">
                    Frete Total (R$)
                </label>

                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="frete"
                    id="compra_frete"
                    value="<?= $compra_editar
                        ? htmlspecialchars($compra_editar['frete'])
                        : '0.00'
                    ?>"
                    oninput="calcularPreviaCustoMedio()"
                    style="
                        width: 100%;
                        padding: 10px;
                    "
                >

            </div>

        </div>


        <div style="
            background: #09090b;
            border: 1px solid #18181b;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            font-weight: 500;
        ">

            Novo custo médio estimado:

            <span
                class="text-positive"
                id="custo_medio_preview"
            >
                R$ 0,00
            </span>

        </div>


        <div style="
            display: flex;
            gap: 10px;
        ">

            <button
                type="submit"
                name="salvar_compra"
                style="
                    padding: 12px 24px;
                    cursor: pointer;
                    flex: 1;
                "
            >

                <?= $compra_editar
                    ? 'Atualizar Compra'
                    : 'Confirmar compra'
                ?>

            </button>


            <?php if ($compra_editar): ?>

                <a
                    href="index.php?page=compras"
                    style="
                        background: #18181b;
                        color: #cbd5e1;
                        text-decoration: none;
                        padding: 12px 20px;
                        border-radius: 6px;
                        font-size: 14px;
                        border: 1px solid #27272a;
                    "
                >
                    Cancelar
                </a>

            <?php endif; ?>

        </div>

    </form>

</div>


<!-- ==========================================================
     HISTÓRICO
========================================================== -->

<div class="table-container">

    <div style="
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 15px;
    ">

        <h3 style="margin: 0;">
            Histórico de Compras
        </h3>

        <?php if ($total_compras > 0): ?>

            <span style="
                color: #71717a;
                font-size: 13px;
            ">
                <?= $total_compras ?>
                <?= $total_compras === 1
                    ? 'registro'
                    : 'registros'
                ?>
            </span>

        <?php endif; ?>

    </div>


    <!-- ======================================================
         DESKTOP
    ======================================================= -->

    <div class="historico-desktop">

        <table>

            <thead>

                <tr>

                    <th>Data</th>
                    <th>Produto</th>
                    <th>Qtd. Entrada</th>
                    <th>Custo Un.</th>
                    <th>Frete</th>
                    <th>Custo Remessa</th>

                    <th style="text-align: center;">
                        Ações
                    </th>

                </tr>

            </thead>


            <tbody>

                <?php if (count($historico_compras) > 0): ?>

                    <?php foreach ($historico_compras as $c): ?>

                        <tr>

                            <td>
                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime($c['data_compra'])
                                ) ?>
                            </td>


                            <td>

                                <strong>
                                    <?= htmlspecialchars(
                                        $c['produto_nome']
                                    ) ?>
                                </strong>

                            </td>


                            <td>
                                +<?= (int)$c['quantidade'] ?> un.
                            </td>


                            <td>
                                R$
                                <?= number_format(
                                    $c['custo_unitario'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>
                            </td>


                            <td>
                                R$
                                <?= number_format(
                                    $c['frete'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>
                            </td>


                            <td>

                                <strong>
                                    R$
                                    <?= number_format(
                                        $c['custo_real_unitario'],
                                        2,
                                        ',',
                                        '.'
                                    ) ?>
                                </strong>

                            </td>


                            <td style="
                                text-align: center;
                                white-space: nowrap;
                            ">

                                <a
                                    href="index.php?page=compras&acao=editar&id=<?= (int)$c['id'] ?>"
                                    title="Editar Compra"
                                    style="
                                        text-decoration: none;
                                        margin-right: 10px;
                                    "
                                >
                                    ✏️
                                </a>


                                <a
                                    href="index.php?page=compras&acao=deletar&id=<?= (int)$c['id'] ?>"
                                    onclick="confirmarExclusaoCompra(event, this.href)"
                                    title="Cancelar Compra"
                                    style="
                                        text-decoration: none;
                                    "
                                >
                                    🗑️
                                </a>

                            </td>

                        </tr>

                    <?php endforeach; ?>


                <?php else: ?>

                    <tr>

                        <td
                            colspan="7"
                            style="
                                color: #94a3b8;
                                text-align: center;
                            "
                        >
                            Nenhuma compra registrada ainda.
                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>


    <!-- ======================================================
         MOBILE
    ======================================================= -->

    <div class="historico-mobile">

        <?php if (count($historico_compras) > 0): ?>

            <?php foreach ($historico_compras as $c): ?>

                <div
                    class="compra-card"
                    id="compra-card-<?= (int)$c['id'] ?>"
                >


                    <!-- CABEÇALHO / SETA -->

                    <div
                        class="compra-card-topo"
                        onclick="abrirDetalhesCompra(<?= (int)$c['id'] ?>)"
                    >

                        <div class="compra-card-info">

                            <div class="compra-card-produto">

                                <?= htmlspecialchars(
                                    $c['produto_nome']
                                ) ?>

                            </div>


                            <div class="compra-card-data">

                                <?= date(
                                    'd/m/Y H:i',
                                    strtotime($c['data_compra'])
                                ) ?>

                            </div>

                        </div>


                        <div class="compra-card-qtd">

                            +<?= (int)$c['quantidade'] ?> un.

                        </div>


                        <div
                            class="compra-card-seta"
                            id="seta-<?= (int)$c['id'] ?>"
                        >
                            ▼
                        </div>

                    </div>


                    <!-- DETALHES -->

                    <div
                        class="compra-card-detalhes"
                        id="detalhes-<?= (int)$c['id'] ?>"
                    >

                        <div class="compra-detalhe">

                            <span class="compra-detalhe-label">
                                Custo por unidade
                            </span>

                            <span class="compra-detalhe-valor">
                                R$
                                <?= number_format(
                                    $c['custo_unitario'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>
                            </span>

                        </div>


                        <div class="compra-detalhe">

                            <span class="compra-detalhe-label">
                                Frete
                            </span>

                            <span class="compra-detalhe-valor">
                                R$
                                <?= number_format(
                                    $c['frete'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>
                            </span>

                        </div>


                        <div class="compra-detalhe">

                            <span class="compra-detalhe-label">
                                Custo real da remessa
                            </span>

                            <span class="compra-detalhe-valor">

                                <strong>
                                    R$
                                    <?= number_format(
                                        $c['custo_real_unitario'],
                                        2,
                                        ',',
                                        '.'
                                    ) ?>
                                </strong>

                            </span>

                        </div>

                    </div>


                    <!-- ==================================================
                         AÇÕES SEMPRE VISÍVEIS
                    =================================================== -->

                    <div class="compra-card-acoes">

                        <a
                            class="compra-editar"
                            href="index.php?page=compras&acao=editar&id=<?= (int)$c['id'] ?>"
                        >
                            ✏️ Editar
                        </a>


                        <a
                            class="compra-excluir"
                            href="index.php?page=compras&acao=deletar&id=<?= (int)$c['id'] ?>"
                            onclick="confirmarExclusaoCompra(event, this.href)"
                        >
                            🗑️ Excluir
                        </a>

                    </div>

                </div>

            <?php endforeach; ?>


        <?php else: ?>

            <div style="
                color: #94a3b8;
                text-align: center;
                padding: 20px 10px;
            ">
                Nenhuma compra registrada ainda.
            </div>

        <?php endif; ?>

    </div>


    <!-- ======================================================
         PAGINAÇÃO
    ======================================================= -->

    <?php if ($total_paginas > 1): ?>

        <div class="paginacao-compras">

            <?php if ($pagina_atual > 1): ?>

                <a
                    href="index.php?page=compras&pagina=<?= $pagina_atual - 1 ?>"
                    aria-label="Página anterior"
                >
                    ‹
                </a>

            <?php endif; ?>


            <?php

            /*
            |--------------------------------------------------------------------------
            | Define quais páginas serão exibidas
            |--------------------------------------------------------------------------
            */

            $inicio = max(
                1,
                $pagina_atual - 2
            );

            $fim = min(
                $total_paginas,
                $pagina_atual + 2
            );

            ?>


            <?php if ($inicio > 1): ?>

                <a
                    href="index.php?page=compras&pagina=1"
                >
                    1
                </a>


                <?php if ($inicio > 2): ?>

                    <span>
                        ...
                    </span>

                <?php endif; ?>

            <?php endif; ?>


            <?php for (
                $i = $inicio;
                $i <= $fim;
                $i++
            ): ?>

                <?php if ($i === $pagina_atual): ?>

                    <span class="pagina-atual">
                        <?= $i ?>
                    </span>

                <?php else: ?>

                    <a
                        href="index.php?page=compras&pagina=<?= $i ?>"
                    >
                        <?= $i ?>
                    </a>

                <?php endif; ?>

            <?php endfor; ?>


            <?php if ($fim < $total_paginas): ?>

                <?php if ($fim < $total_paginas - 1): ?>

                    <span>
                        ...
                    </span>

                <?php endif; ?>


                <a
                    href="index.php?page=compras&pagina=<?= $total_paginas ?>"
                >
                    <?= $total_paginas ?>
                </a>

            <?php endif; ?>


            <?php if ($pagina_atual < $total_paginas): ?>

                <a
                    href="index.php?page=compras&pagina=<?= $pagina_atual + 1 ?>"
                    aria-label="Próxima página"
                >
                    ›
                </a>

            <?php endif; ?>


            <span class="paginacao-info">

                Página <?= $pagina_atual ?>
                de <?= $total_paginas ?>

            </span>

        </div>

    <?php endif; ?>

</div>


<script>

/*
|--------------------------------------------------------------------------
| CONFIRMAÇÃO DE CANCELAMENTO
|--------------------------------------------------------------------------
*/

function confirmarExclusaoCompra(event, url) {

    event.preventDefault();


    if (typeof Swal === 'undefined') {

        if (
            confirm(
                'Cancelar esta compra?\n\n' +
                'A quantidade adicionada por ela será removida do estoque.'
            )
        ) {

            window.location.href = url;

        }

        return false;
    }


    Swal.fire({

        title: 'Cancelar esta compra?',

        text:
            'A quantidade adicionada por ela será removida do estoque.',

        icon: 'warning',

        showCancelButton: true,

        confirmButtonColor: '#ef4444',

        cancelButtonColor: '#27272a',

        confirmButtonText: 'Sim, cancelar',

        cancelButtonText: 'Voltar',

        background: '#09090b',

        color: '#f8fafc',

        customClass: {

            popup: 'border-modal-dark'

        }

    }).then(function(result) {

        if (result.isConfirmed) {

            window.location.href = url;

        }

    });


    return false;
}


/*
|--------------------------------------------------------------------------
| ABRIR / FECHAR DETALHES NO MOBILE
|--------------------------------------------------------------------------
*/

function abrirDetalhesCompra(id) {

    const card =
        document.getElementById(
            'compra-card-' + id
        );


    if (!card) {
        return;
    }


    card.classList.toggle('aberto');

}


/*
|--------------------------------------------------------------------------
| PRÉVIA DO CUSTO MÉDIO
|--------------------------------------------------------------------------
*/

let estoqueAtual = 0;

let custoAtual = 0;


function carregarDadosProduto() {

    const select =
        document.getElementById(
            'compra_produto'
        );


    if (!select) {
        return;
    }


    const option =
        select.options[
            select.selectedIndex
        ];


    if (!option) {
        return;
    }


    custoAtual =
        parseFloat(
            option.getAttribute(
                'data-custo'
            )
        ) || 0;


    estoqueAtual =
        parseInt(
            option.getAttribute(
                'data-estoque'
            )
        ) || 0;


    const campoCusto =
        document.getElementById(
            'compra_custo'
        );


    if (
        custoAtual > 0 &&
        campoCusto &&
        !campoCusto.value
    ) {

        campoCusto.value =
            custoAtual.toFixed(2);

    }


    calcularPreviaCustoMedio();

}


function calcularPreviaCustoMedio() {

    const campoQtd =
        document.getElementById(
            'compra_qtd'
        );


    const campoCusto =
        document.getElementById(
            'compra_custo'
        );


    const campoFrete =
        document.getElementById(
            'compra_frete'
        );


    const campoPreview =
        document.getElementById(
            'custo_medio_preview'
        );


    if (
        !campoQtd ||
        !campoCusto ||
        !campoFrete ||
        !campoPreview
    ) {

        return;

    }


    const qtdNova =
        parseInt(
            campoQtd.value
        ) || 0;


    const custoNovo =
        parseFloat(
            campoCusto.value
        ) || 0;


    const frete =
        parseFloat(
            campoFrete.value
        ) || 0;


    const valorTotalAntigo =
        estoqueAtual * custoAtual;


    const valorTotalNovo =
        (qtdNova * custoNovo) + frete;


    const qtdTotal =
        estoqueAtual + qtdNova;


    const novoCustoMedio =
        qtdTotal > 0
            ? (
                valorTotalAntigo +
                valorTotalNovo
            ) / qtdTotal
            : 0;


    campoPreview.innerText =
        'R$ ' +
        novoCustoMedio
            .toFixed(2)
            .replace('.', ',');

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
                'compra_produto'
            );


        if (
            select &&
            select.value
        ) {

            carregarDadosProduto();

        }

    }
);

</script>