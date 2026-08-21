<?php

$mensagem = '';
$empresa_id = $_SESSION['empresa_id'] ?? 0;


/*
|--------------------------------------------------------------------------
| 1. AÇÕES
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['acao'])
) {

    validar_csrf();

    $id_despesa = (int)($_POST['id'] ?? 0);


    /*
    |----------------------------------------------------------------------
    | MARCAR COMO PAGO
    |----------------------------------------------------------------------
    */

    if (
        $_POST['acao'] === 'pagar' &&
        $id_despesa > 0
    ) {

        $stmtPagar = $pdo->prepare("
            UPDATE despesas
            SET pago = TRUE
            WHERE id = ?
              AND empresa_id = ?
        ");

        $stmtPagar->execute([
            $id_despesa,
            $empresa_id
        ]);


        $mensagem = '
            <div class="alerta-sucesso">
                <span>✅</span>
                <div>
                    <strong>Despesa marcada como PAGA!</strong>
                </div>
            </div>
        ';
    }


    /*
    |----------------------------------------------------------------------
    | EXCLUIR
    |----------------------------------------------------------------------
    */

    elseif (
        $_POST['acao'] === 'deletar' &&
        $id_despesa > 0
    ) {

        $stmtDel = $pdo->prepare("
            DELETE FROM despesas
            WHERE id = ?
              AND empresa_id = ?
        ");

        $stmtDel->execute([
            $id_despesa,
            $empresa_id
        ]);


        $mensagem = '
            <div class="alerta-sucesso">
                <span>🗑️</span>
                <div>
                    <strong>Despesa removida com sucesso!</strong>
                </div>
            </div>
        ';
    }
}


/*
|--------------------------------------------------------------------------
| 2. CADASTRO
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['cadastrar_despesa'])
) {

    $descricao =
        trim($_POST['descricao'] ?? '');

    $categoria =
        trim($_POST['categoria'] ?? '');

    $valor =
        (float)($_POST['valor'] ?? 0);

    $data_vencimento =
        !empty($_POST['data_vencimento'])
            ? $_POST['data_vencimento']
            : null;

    $pago =
        isset($_POST['pago'])
            ? 1
            : 0;


    if (
        !empty($descricao) &&
        $valor > 0
    ) {

        $stmtInst = $pdo->prepare("
            INSERT INTO despesas (
                empresa_id,
                descricao,
                categoria,
                valor,
                data_vencimento,
                pago
            )
            VALUES (?, ?, ?, ?, ?, ?)
        ");


        $stmtInst->execute([
            $empresa_id,
            $descricao,
            $categoria,
            $valor,
            $data_vencimento,
            $pago
        ]);


        $mensagem = '
            <div class="alerta-sucesso">
                <span>✅</span>
                <div>
                    <strong>Despesa cadastrada com sucesso!</strong>
                </div>
            </div>
        ';

    } else {

        $mensagem = '
            <div class="alerta-erro">
                ⚠️ Preencha a descrição e o valor corretamente.
            </div>
        ';
    }
}


/*
|--------------------------------------------------------------------------
| 3. RESUMO FINANCEIRO
|--------------------------------------------------------------------------
*/

$stmtResumo = $pdo->prepare("
    SELECT

        COALESCE(SUM(valor), 0) AS total_despesas,

        COALESCE(
            SUM(
                CASE
                    WHEN pago = TRUE
                    THEN valor
                    ELSE 0
                END
            ),
            0
        ) AS total_pago,

        COALESCE(
            SUM(
                CASE
                    WHEN pago = FALSE
                    THEN valor
                    ELSE 0
                END
            ),
            0
        ) AS total_pendente

    FROM despesas

    WHERE empresa_id = ?
");


$stmtResumo->execute([
    $empresa_id
]);


$resumo =
    $stmtResumo->fetch(PDO::FETCH_ASSOC);


$total_despesas =
    (float)($resumo['total_despesas'] ?? 0);

$total_pago =
    (float)($resumo['total_pago'] ?? 0);

$total_pendente =
    (float)($resumo['total_pendente'] ?? 0);


/*
|--------------------------------------------------------------------------
| 4. HISTÓRICO
|--------------------------------------------------------------------------
*/

$stmtDespesas = $pdo->prepare("
    SELECT *
    FROM despesas
    WHERE empresa_id = ?
    ORDER BY id DESC
");


$stmtDespesas->execute([
    $empresa_id
]);


$despesas =
    $stmtDespesas->fetchAll(
        PDO::FETCH_ASSOC
    );

?>

<header class="header">

    <div>

        <h2>
            Despesas & Custos Fixos
        </h2>

        <p
            style="
                color:#94a3b8;
                font-size:14px;
            "
        >
            Controle contas a pagar, aluguel,
            marketing e custos operacionais
        </p>

    </div>

</header>


<?= $mensagem ?>


<!-- ============================================================
     RESUMO
============================================================ -->

<div class="resumo-despesas">


    <div class="card-resumo-despesa">

        <span class="resumo-label">
            Total de Despesas
        </span>

        <strong class="resumo-valor vermelho">

            R$
            <?= number_format(
                $total_despesas,
                2,
                ',',
                '.'
            ) ?>

        </strong>

    </div>


    <div class="card-resumo-despesa">

        <span class="resumo-label">
            Total Pago
        </span>

        <strong class="resumo-valor verde">

            R$
            <?= number_format(
                $total_pago,
                2,
                ',',
                '.'
            ) ?>

        </strong>

    </div>


    <div class="card-resumo-despesa">

        <span class="resumo-label">
            Total Pendente
        </span>

        <strong class="resumo-valor vermelho-claro">

            R$
            <?= number_format(
                $total_pendente,
                2,
                ',',
                '.'
            ) ?>

        </strong>

    </div>


</div>


<!-- ============================================================
     NOVA DESPESA
============================================================ -->

<div
    class="table-container despesa-form-container"
>

    <h3>
        + Nova Despesa
    </h3>


    <form
        method="POST"
        action="index.php?page=despesas"
    >

        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

        <div class="linha-despesa">


            <!-- DESCRIÇÃO -->

            <div class="campo-despesa campo-descricao">

                <label>
                    Descrição *
                </label>


                <input
                    type="text"
                    name="descricao"
                    required
                    placeholder="Ex: Embalagens, Luz, Anúncios"
                >

            </div>


            <!-- CATEGORIA -->

            <div class="campo-despesa">

                <label>
                    Categoria
                </label>


                <select
                    name="categoria"
                >

                    <option value="Operacional">
                        Operacional
                    </option>

                    <option value="Marketing">
                        Marketing/Anúncios
                    </option>

                    <option value="Infraestrutura">
                        Infraestrutura/Aluguel
                    </option>

                    <option value="Impostos">
                        Impostos/Taxas
                    </option>

                    <option value="Outros">
                        Outros
                    </option>

                </select>

            </div>


            <!-- VALOR -->

            <div class="campo-despesa">

                <label>
                    Valor (R$) *
                </label>


                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="valor"
                    required
                    placeholder="0,00"
                >

            </div>


            <!-- VENCIMENTO -->

            <div class="campo-despesa">

                <label>
                    Vencimento (Opcional)
                </label>


                <input
                    type="date"
                    name="data_vencimento"
                >

            </div>


        </div>


        <!-- PAGO -->

        <div class="campo-pago">

            <input
                type="checkbox"
                name="pago"
                id="pago"
                value="1"
                checked
            >

            <label for="pago">
                Já foi pago?
            </label>

        </div>


        <button
            type="submit"
            name="cadastrar_despesa"
            class="btn-salvar-despesa"
        >
            Salvar Despesa
        </button>


    </form>

</div>


<!-- ============================================================
     HISTÓRICO
============================================================ -->

<div class="table-container historico-despesas">


    <div class="historico-despesas-topo">

        <h3>
            Histórico de Despesas
        </h3>


        <select
            id="filtroCategoriaDespesa"
            onchange="filtrarDespesas()"
        >

            <option value="">
                — Todas —
            </option>

            <option value="Operacional">
                Operacional
            </option>

            <option value="Marketing">
                Marketing/Anúncios
            </option>

            <option value="Infraestrutura">
                Infraestrutura/Aluguel
            </option>

            <option value="Impostos">
                Impostos/Taxas
            </option>

            <option value="Outros">
                Outros
            </option>

        </select>

    </div>


    <!-- ========================================================
         DESKTOP
    ========================================================= -->

    <div class="despesas-desktop">

        <table>

            <thead>

                <tr>

                    <th>
                        Vencimento
                    </th>

                    <th>
                        Descrição
                    </th>

                    <th>
                        Categoria
                    </th>

                    <th>
                        Valor
                    </th>

                    <th>
                        Status
                    </th>

                    <th
                        style="text-align:center;"
                    >
                        Ações
                    </th>

                </tr>

            </thead>


            <tbody>


                <?php if (!empty($despesas)): ?>


                    <?php foreach ($despesas as $d): ?>


                        <tr
                            class="linha-despesa-historico"
                            data-categoria="<?= htmlspecialchars(
                                $d['categoria'] ?? ''
                            ) ?>"
                        >


                            <!-- VENCIMENTO -->

                            <td>

                                <?php if (
                                    !empty(
                                        $d['data_vencimento']
                                    )
                                ): ?>

                                    <?= date(
                                        'd/m/Y',
                                        strtotime(
                                            $d['data_vencimento']
                                        )
                                    ) ?>

                                <?php else: ?>

                                    <span
                                        style="color:#71717a;"
                                    >
                                        —
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- DESCRIÇÃO -->

                            <td>

                                <strong>

                                    <?= htmlspecialchars(
                                        $d['descricao']
                                    ) ?>

                                </strong>

                            </td>


                            <!-- CATEGORIA -->

                            <td>

                                <?= htmlspecialchars(
                                    $d['categoria']
                                ) ?>

                            </td>


                            <!-- VALOR -->

                            <td
                                style="color:#ef4444;"
                            >

                                <strong>

                                    R$
                                    <?= number_format(
                                        (float)$d['valor'],
                                        2,
                                        ',',
                                        '.'
                                    ) ?>

                                </strong>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <?php if (
                                    $d['pago']
                                ): ?>

                                    <span class="status-pago">
                                        PAGO
                                    </span>

                                <?php else: ?>

                                    <span class="status-pendente">
                                        PENDENTE
                                    </span>

                                <?php endif; ?>

                            </td>


                            <!-- AÇÕES -->

                            <td
                                class="acoes-despesa"
                            >


                                <?php if (
                                    !$d['pago']
                                ): ?>

                                    <form method="POST" action="index.php?page=despesas" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="acao" value="pagar">
                                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                        <button
                                            type="submit"
                                            title="Marcar como Pago"
                                            style="background:none;border:none;cursor:pointer;font-size:inherit;padding:0;"
                                        >
                                            ✅
                                        </button>
                                    </form>

                                <?php endif; ?>


                                <button
                                    type="button"
                                    title="Excluir"
                                    onclick="abrirModalExcluirDespesa(<?= (int)$d['id'] ?>)"
                                >
                                    🗑️
                                </button>


                            </td>


                        </tr>


                    <?php endforeach; ?>


                <?php else: ?>


                    <tr>

                        <td
                            colspan="6"
                            class="sem-despesas"
                        >

                            Nenhuma despesa encontrada.

                        </td>

                    </tr>


                <?php endif; ?>


            </tbody>

        </table>

    </div>


    <!-- ========================================================
         MOBILE
    ========================================================= -->

    <div class="despesas-mobile">


        <?php if (!empty($despesas)): ?>


            <?php foreach ($despesas as $d): ?>


                <div
                    class="despesa-mobile-card"
                    data-categoria="<?= htmlspecialchars(
                        $d['categoria'] ?? ''
                    ) ?>"
                >


                    <div class="despesa-mobile-principal">


                        <div class="despesa-mobile-info">


                            <strong>

                                <?= htmlspecialchars(
                                    $d['descricao']
                                ) ?>

                            </strong>


                            <span>

                                <?= htmlspecialchars(
                                    $d['categoria']
                                ) ?>

                                ·

                                <?php if (
                                    !empty(
                                        $d['data_vencimento']
                                    )
                                ): ?>

                                    <?= date(
                                        'd/m/Y',
                                        strtotime(
                                            $d['data_vencimento']
                                        )
                                    ) ?>

                                <?php else: ?>

                                    Sem vencimento

                                <?php endif; ?>

                            </span>


                        </div>


                        <div class="despesa-mobile-direita">


                            <strong
                                class="despesa-mobile-valor"
                            >

                                R$
                                <?= number_format(
                                    (float)$d['valor'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>

                            </strong>


                            <div
                                class="acoes-despesa-mobile"
                            >


                                <?php if (
                                    !$d['pago']
                                ): ?>

                                    <form method="POST" action="index.php?page=despesas" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                        <input type="hidden" name="acao" value="pagar">
                                        <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                        <button
                                            type="submit"
                                            title="Marcar como Pago"
                                            style="background:none;border:none;cursor:pointer;font-size:inherit;padding:0;"
                                        >
                                            ✅
                                        </button>
                                    </form>

                                <?php endif; ?>


                                <button
                                    type="button"
                                    title="Excluir"
                                    onclick="abrirModalExcluirDespesa(<?= (int)$d['id'] ?>)"
                                >
                                    🗑️
                                </button>


                            </div>


                            <button
                                type="button"
                                class="btn-detalhes-despesa"
                                onclick="toggleDetalhesDespesa(<?= (int)$d['id'] ?>)"
                                aria-label="Mostrar detalhes"
                            >

                                <span>
                                    ›
                                </span>

                            </button>


                        </div>


                    </div>


                    <!-- DETALHES -->

                    <div
                        class="despesa-mobile-detalhes"
                        id="detalhes-despesa-<?= (int)$d['id'] ?>"
                    >


                        <div class="detalhe-despesa">


                            <span>
                                Categoria
                            </span>


                            <strong>

                                <?= htmlspecialchars(
                                    $d['categoria']
                                ) ?>

                            </strong>


                        </div>


                        <div class="detalhe-despesa">


                            <span>
                                Vencimento
                            </span>


                            <strong>

                                <?php if (
                                    !empty(
                                        $d['data_vencimento']
                                    )
                                ): ?>

                                    <?= date(
                                        'd/m/Y',
                                        strtotime(
                                            $d['data_vencimento']
                                        )
                                    ) ?>

                                <?php else: ?>

                                    —

                                <?php endif; ?>


                            </strong>


                        </div>


                        <div class="detalhe-despesa">


                            <span>
                                Status
                            </span>


                            <strong>

                                <?php if (
                                    $d['pago']
                                ): ?>

                                    <span class="status-pago">
                                        PAGO
                                    </span>

                                <?php else: ?>

                                    <span class="status-pendente">
                                        PENDENTE
                                    </span>

                                <?php endif; ?>


                            </strong>


                        </div>


                        <div
                            class="detalhe-despesa destaque-despesa"
                        >


                            <span>
                                Valor
                            </span>


                            <strong>

                                R$
                                <?= number_format(
                                    (float)$d['valor'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>

                            </strong>


                        </div>


                    </div>


                </div>


            <?php endforeach; ?>


        <?php else: ?>


            <div class="sem-despesas-mobile">

                Nenhuma despesa encontrada.

            </div>


        <?php endif; ?>


    </div>


</div>


<!-- ============================================================
     MODAL EXCLUSÃO
============================================================ -->

<form
    id="form-excluir-despesa"
    method="POST"
    action="index.php?page=despesas"
    style="display:none;"
>
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="hidden" name="acao" value="deletar">
    <input type="hidden" name="id" id="input-id-despesa-excluir" value="">
</form>

<div
    id="modal-excluir-despesa"
    class="modal-confirmacao-despesa"
>


    <div class="modal-despesa-box">


        <div class="modal-despesa-icone">
            ⚠️
        </div>


        <h3>
            Excluir despesa?
        </h3>


        <p>
            Tem certeza que deseja excluir esta despesa?
        </p>


        <p class="modal-despesa-aviso">
            Esse registro será removido do sistema.
        </p>


        <div class="modal-despesa-botoes">


            <button
                type="button"
                class="btn-despesa-voltar"
                onclick="fecharModalExcluirDespesa()"
            >
                Não, voltar
            </button>


            <button
                type="button"
                class="btn-despesa-excluir"
                onclick="confirmarExclusaoDespesa()"
            >
                Sim, excluir
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

    display:flex;

    align-items:flex-start;

    gap:12px;

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

}


/*
|--------------------------------------------------------------------------
| RESUMO
|--------------------------------------------------------------------------
*/

.resumo-despesas {

    display:grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap:15px;

    margin-bottom:30px;

}


.card-resumo-despesa {

    background:
        #09090b;

    border:
        1px solid #27272a;

    border-radius:
        10px;

    padding:
        20px;

    min-height:
        90px;

    display:flex;

    flex-direction:column;

    justify-content:center;

}


.resumo-label {

    color:
        #94a3b8;

    font-size:
        14px;

    margin-bottom:
        8px;

}


.resumo-valor {

    font-size:
        25px;

    font-weight:
        700;

}


.resumo-valor.vermelho {

    color:
        #f87171;

}


.resumo-valor.verde {

    color:
        #34d399;

}


.resumo-valor.vermelho-claro {

    color:
        #fb7185;

}


/*
|--------------------------------------------------------------------------
| FORMULÁRIO
|--------------------------------------------------------------------------
*/

.despesa-form-container {

    max-width:
        700px;

    margin-bottom:
        30px;

}


.despesa-form-container h3 {

    margin-bottom:
        18px;

}


.linha-despesa {

    display:flex;

    gap:15px;

    flex-wrap:wrap;

    align-items:flex-end;

}


.campo-despesa {

    flex:1;

    min-width:130px;

}


.campo-descricao {

    flex:2;

    min-width:200px;

}


.campo-despesa label {

    display:block;

    font-size:13px;

    color:#94a3b8;

    margin-bottom:5px;

}


.campo-despesa input,
.campo-despesa select {

    width:100%;

    padding:10px;

    box-sizing:border-box;

}


.campo-pago {

    display:flex;

    align-items:center;

    gap:8px;

    margin-top:15px;

    margin-bottom:15px;

}


.campo-pago input {

    width:16px;

    height:16px;

}


.campo-pago label {

    color:#e2e8f0;

    font-size:13px;

    cursor:pointer;

}


.btn-salvar-despesa {

    width:100%;

    padding:11px 20px;

    cursor:pointer;

}


/*
|--------------------------------------------------------------------------
| HISTÓRICO
|--------------------------------------------------------------------------
*/

.historico-despesas-topo {

    display:flex;

    justify-content:space-between;

    align-items:center;

    gap:10px;

    margin-bottom:15px;

}


.historico-despesas-topo h3 {

    margin:0;

}


.historico-despesas-topo select {

    padding:8px 12px;

    font-size:13px;

}


/*
|--------------------------------------------------------------------------
| DESKTOP
|--------------------------------------------------------------------------
*/

.despesas-mobile {

    display:none;

}


.acoes-despesa {

    text-align:center;

    white-space:nowrap;

}


.acoes-despesa a,
.acoes-despesa button {

    background:none;

    border:none;

    cursor:pointer;

    padding:0;

    margin:0 4px;

    text-decoration:none;

    font-size:16px;

}


.status-pago {

    background:
        #064e3b;

    color:
        #34d399;

    padding:
        4px 8px;

    border-radius:
        4px;

    font-size:
        12px;

    font-weight:
        bold;

}


.status-pendente {

    background:
        #7f1d1d;

    color:
        #fca5a5;

    padding:
        4px 8px;

    border-radius:
        4px;

    font-size:
        12px;

    font-weight:
        bold;

}


.sem-despesas {

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

@media (max-width:700px) {


    /*
    | RESUMO
    */

    .resumo-despesas {

        grid-template-columns:
            1fr;

        gap:10px;

        margin-bottom:25px;

    }


    .card-resumo-despesa {

        min-height:
            auto;

        padding:
            17px;

    }


    .resumo-label {

        font-size:
            13px;

    }


    .resumo-valor {

        font-size:
            22px;

    }


    /*
    | FORMULÁRIO
    */

    .despesa-form-container {

        max-width:
            none;

    }


    .linha-despesa {

        flex-direction:
            column;

        gap:0;

        align-items:stretch;

    }


    .campo-despesa,
    .campo-descricao {

        width:100%;

        min-width:0;

    }


    /*
    | HISTÓRICO
    */

    .despesas-desktop {

        display:none;

    }


    .despesas-mobile {

        display:block;

    }


    .historico-despesas-topo {

        align-items:stretch;

        flex-direction:column;

    }


    .historico-despesas-topo select {

        width:100%;

        box-sizing:border-box;

    }


    /*
    | CARD
    */

    .despesa-mobile-card {

        background:
            #09090b;

        border:
            1px solid #27272a;

        border-radius:
            10px;

        margin-bottom:
            10px;

        overflow:hidden;

    }


    .despesa-mobile-principal {

        display:flex;

        align-items:center;

        justify-content:space-between;

        gap:10px;

        padding:
            13px 12px;

    }


    .despesa-mobile-info {

        min-width:0;

        display:flex;

        flex-direction:column;

        gap:4px;

    }


    .despesa-mobile-info strong {

        color:
            #f4f4f5;

        font-size:
            14px;

        overflow:hidden;

        text-overflow:ellipsis;

        white-space:nowrap;

    }


    .despesa-mobile-info span {

        color:
            #71717a;

        font-size:
            12px;

    }


    .despesa-mobile-direita {

        display:flex;

        align-items:center;

        gap:8px;

        flex-shrink:0;

    }


    .despesa-mobile-valor {

        color:
            #f87171;

        font-size:
            13px;

        white-space:nowrap;

    }


    .acoes-despesa-mobile {

        display:flex;

        align-items:center;

        gap:7px;

    }


    .acoes-despesa-mobile a,
    .acoes-despesa-mobile button {

        background:none;

        border:none;

        padding:0;

        cursor:pointer;

        text-decoration:none;

        font-size:15px;

    }


    /*
    | BOTÃO DETALHES
    */

    .btn-detalhes-despesa {

        width:27px;

        height:27px;

        display:flex;

        align-items:center;

        justify-content:center;

        background:
            #18181b;

        border:
            1px solid #27272a;

        color:
            #a1a1aa;

        border-radius:
            6px;

        cursor:pointer;

    }


    .btn-detalhes-despesa span {

        font-size:
            20px;

        line-height:
            1;

        transform:
            rotate(90deg);

        transition:
            transform .2s ease;

    }


    .despesa-mobile-card.aberta
    .btn-detalhes-despesa span {

        transform:
            rotate(-90deg);

    }


    /*
    | DETALHES
    */

    .despesa-mobile-detalhes {

        display:none;

        padding:
            8px 12px 12px;

        border-top:
            1px solid #18181b;

    }


    .despesa-mobile-card.aberta
    .despesa-mobile-detalhes {

        display:block;

    }


    .detalhe-despesa {

        display:flex;

        align-items:center;

        justify-content:space-between;

        gap:10px;

        padding:
            7px 0;

    }


    /*
    | SEM LINHAS/TRAÇOS
    */

    .detalhe-despesa {

        border-bottom:none;

    }


    .detalhe-despesa span {

        color:
            #71717a;

        font-size:
            12px;

    }


    .detalhe-despesa strong {

        color:
            #d4d4d8;

        font-size:
            12px;

        text-align:right;

    }


    .destaque-despesa strong {

        color:
            #f87171;

    }


    .sem-despesas-mobile {

        text-align:center;

        color:#94a3b8;

        padding:
            30px 15px;

    }


}


/*
|--------------------------------------------------------------------------
| MODAL
|--------------------------------------------------------------------------
*/

.modal-confirmacao-despesa {

    display:none;

    position:fixed;

    inset:0;

    background:
        rgba(0,0,0,.75);

    z-index:9999;

    align-items:center;

    justify-content:center;

    padding:20px;

}


.modal-confirmacao-despesa.ativo {

    display:flex;

}


.modal-despesa-box {

    width:100%;

    max-width:430px;

    background:
        #18181b;

    border:
        1px solid #27272a;

    border-radius:
        14px;

    padding:30px;

    text-align:center;

    box-shadow:
        0 20px 50px rgba(0,0,0,.5);

}


.modal-despesa-icone {

    font-size:38px;

    margin-bottom:12px;

}


.modal-despesa-box h3 {

    margin:0 0 10px;

    color:#f8fafc;

    font-size:20px;

}


.modal-despesa-box p {

    margin:8px 0;

    color:#cbd5e1;

    font-size:14px;

}


.modal-despesa-aviso {

    color:#f59e0b !important;

    font-size:13px !important;

    margin-top:14px !important;

}


.modal-despesa-botoes {

    display:flex;

    gap:10px;

    margin-top:25px;

}


.modal-despesa-botoes button {

    flex:1;

    padding:11px 15px;

    border-radius:7px;

    cursor:pointer;

    font-size:14px;

    font-weight:600;

}


.btn-despesa-voltar {

    background:#27272a;

    color:#cbd5e1;

    border:1px solid #3f3f46;

}


.btn-despesa-excluir {

    background:#dc2626;

    color:white;

    border:1px solid #dc2626;

}


@media (max-width:700px) {

    .modal-despesa-box {

        padding:
            24px 20px;

    }


    .modal-despesa-botoes {

        flex-direction:
            column;

    }

}

</style>


<script>

/*
|--------------------------------------------------------------------------
| FILTRO
|--------------------------------------------------------------------------
*/

function filtrarDespesas() {

    const filtro =
        document.getElementById(
            'filtroCategoriaDespesa'
        ).value;


    const desktop =
        document.querySelectorAll(
            '.linha-despesa-historico'
        );


    const mobile =
        document.querySelectorAll(
            '.despesa-mobile-card'
        );


    desktop.forEach(function(linha) {

        const categoria =
            linha.getAttribute(
                'data-categoria'
            );


        if (
            !filtro ||
            categoria === filtro
        ) {

            linha.style.display = '';

        } else {

            linha.style.display = 'none';

        }

    });


    mobile.forEach(function(card) {

        const categoria =
            card.getAttribute(
                'data-categoria'
            );


        if (
            !filtro ||
            categoria === filtro
        ) {

            card.style.display = '';

        } else {

            card.style.display = 'none';

        }

    });

}


/*
|--------------------------------------------------------------------------
| DETALHES MOBILE
|--------------------------------------------------------------------------
*/

function toggleDetalhesDespesa(id) {

    const card =
        document.querySelector(
            '.despesa-mobile-card[data-categoria]'
        );


    const cards =
        document.querySelectorAll(
            '.despesa-mobile-card'
        );


    cards.forEach(function(item) {

        const botao =
            item.querySelector(
                '.btn-detalhes-despesa'
            );


        if (
            botao &&
            botao.getAttribute(
                'onclick'
            ).includes(
                '(' + id + ')'
            )
        ) {

            item.classList.toggle(
                'aberta'
            );

        }

    });

}


/*
|--------------------------------------------------------------------------
| MODAL EXCLUSÃO
|--------------------------------------------------------------------------
*/

let despesaParaExcluir = null;


function abrirModalExcluirDespesa(id) {

    despesaParaExcluir = id;


    const modal =
        document.getElementById(
            'modal-excluir-despesa'
        );


    if (modal) {

        modal.classList.add(
            'ativo'
        );

    }

}


function fecharModalExcluirDespesa() {

    despesaParaExcluir = null;


    const modal =
        document.getElementById(
            'modal-excluir-despesa'
        );


    if (modal) {

        modal.classList.remove(
            'ativo'
        );

    }

}


function confirmarExclusaoDespesa() {

    if (!despesaParaExcluir) {
        return;
    }

    document.getElementById('input-id-despesa-excluir').value = despesaParaExcluir;
    document.getElementById('form-excluir-despesa').submit();

}


/*
|--------------------------------------------------------------------------
| FECHAR MODAL CLICANDO FORA
|--------------------------------------------------------------------------
*/

const modalDespesa =
    document.getElementById(
        'modal-excluir-despesa'
    );


if (modalDespesa) {

    modalDespesa.addEventListener(
        'click',
        function(event) {

            if (
                event.target === this
            ) {

                fecharModalExcluirDespesa();

            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| ESC
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'keydown',
    function(event) {

        if (
            event.key === 'Escape' &&
            despesaParaExcluir
        ) {

            fecharModalExcluirDespesa();

        }

    }
);

</script>