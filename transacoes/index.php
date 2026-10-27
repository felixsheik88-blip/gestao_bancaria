<?php
// Histórico de todas as operações
require __DIR__.'/../includes/auth.php';
verificarPerfil(PERFIS_STAFF);

$q    = trim($_GET['q'] ?? '');
$tipo = $_GET['tipo'] ?? '';
$de   = data_valida($_GET['de'] ?? '');
$ate  = data_valida($_GET['ate'] ?? '');
$where = []; $params = [];
if($q !== ''){ $where[] = "(t.referencia LIKE ? OR c.numero_conta LIKE ?)"; $like = '%'.$q.'%'; array_push($params, $like, $like); }
if(in_array($tipo, ['deposito','levantamento','transferencia_debito','transferencia_credito'], true)){ $where[] = "t.tipo=?"; $params[] = $tipo; }
if($de){  $where[] = "DATE(t.criada_em)>=?"; $params[] = $de; }
if($ate){ $where[] = "DATE(t.criada_em)<=?"; $params[] = $ate; }
$sqlWhere = $where ? 'WHERE '.implode(' AND ', $where) : '';

$por = 20;
$pagina = max(1, (int)($_GET['p'] ?? 1));
$st = $pdo->prepare("SELECT COUNT(*) FROM transacoes t JOIN contas c ON c.id=t.conta_id $sqlWhere");
$st->execute($params);
$total = (int)$st->fetchColumn();
$offset = ($pagina - 1) * $por;
$st = $pdo->prepare("SELECT t.*, c.numero_conta, c.moeda, u.username
    FROM transacoes t JOIN contas c ON c.id=t.conta_id LEFT JOIN utilizadores u ON u.id=t.utilizador_id
    $sqlWhere ORDER BY t.id DESC LIMIT $por OFFSET $offset");
$st->execute($params);
$movs = $st->fetchAll();

pagina_inicio('Operações');
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0">Operações <small class="text-muted fs-6">(<?=$total?>)</small></h4>
    <?php if(tem_perfil(PERFIS_OPERADORES)): ?>
    <div class="d-flex gap-2">
        <a href="operacao.php?tipo=deposito" class="btn btn-success">+ Depósito</a>
        <a href="operacao.php?tipo=levantamento" class="btn btn-danger">+ Levantamento</a>
        <a href="operacao.php?tipo=transferencia" class="btn btn-banco">+ Transferência</a>
    </div>
    <?php endif; ?>
</div>

<form class="card p-3 shadow-sm mb-3" method="GET">
    <div class="row g-2">
        <div class="col-md-3"><input name="q" value="<?=e($q)?>" class="form-control" placeholder="Referência ou nº da conta"></div>
        <div class="col-md-3">
            <select name="tipo" class="form-select">
                <option value="">Todos os tipos</option>
                <?php foreach(['deposito','levantamento','transferencia_debito','transferencia_credito'] as $t): ?>
                    <option value="<?=$t?>" <?=$tipo===$t?'selected':''?>><?=e(rotulo_tipo($t))?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2"><input type="date" name="de" value="<?=e($de)?>" class="form-control" title="De"></div>
        <div class="col-md-2"><input type="date" name="ate" value="<?=e($ate)?>" class="form-control" title="Até"></div>
        <div class="col-md-2 d-flex gap-2">
            <button class="btn btn-outline-primary w-100">Filtrar</button>
            <a href="index.php" class="btn btn-outline-secondary">Limpar</a>
        </div>
    </div>
</form>

<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th>Data</th><th>Referência</th><th>Conta</th><th>Tipo</th><th class="text-end">Montante</th><th class="text-end">Saldo após</th><th>Operador</th></tr></thead>
    <tbody>
    <?php if(!$movs): ?><tr><td colspan="7" class="text-center text-muted py-4">Sem operações.</td></tr><?php endif; ?>
    <?php foreach($movs as $m): $entrada = in_array($m['tipo'], ['deposito','transferencia_credito'], true); ?>
        <tr>
            <td class="text-nowrap"><?=fmt_data($m['criada_em'])?></td>
            <td><a href="comprovativo.php?ref=<?=urlencode($m['referencia'])?>"><?=e($m['referencia'])?></a></td>
            <td><?=e($m['numero_conta'])?></td>
            <td><?=e(rotulo_tipo($m['tipo']))?></td>
            <td class="text-end <?=$entrada?'valor-pos':'valor-neg'?>"><?=$entrada?'+':'-'?><?=fmt_valor($m['montante'], $m['moeda'])?></td>
            <td class="text-end"><?=fmt_valor($m['saldo_depois'], $m['moeda'])?></td>
            <td><?=e($m['username'] ?? '-')?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php render_paginacao($total, $por, $pagina); ?>
<?php pagina_fim(); ?>
