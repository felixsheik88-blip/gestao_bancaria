<?php
// Detalhe da conta + extracto
require __DIR__.'/../includes/auth.php';
verificarPerfil(PERFIS_STAFF);

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT c.*, cl.nome_completo, cl.codigo_cliente, cl.nuit, t.nome AS tipo_nome, t.taxa_juro
    FROM contas c JOIN clientes cl ON cl.id=c.cliente_id JOIN tipos_conta t ON t.id=c.tipo_conta_id WHERE c.id=?");
$st->execute([$id]);
$c = $st->fetch();
if(!$c){
    flash_set('danger', 'Conta não encontrada.');
    header('Location: index.php'); exit;
}

// Filtros do extracto
$de   = data_valida($_GET['de'] ?? '');
$ate  = data_valida($_GET['ate'] ?? '');
$tipo = $_GET['tipo'] ?? '';
$where = ["t.conta_id=?"]; $params = [$id];
if($de){  $where[] = "DATE(t.criada_em)>=?"; $params[] = $de; }
if($ate){ $where[] = "DATE(t.criada_em)<=?"; $params[] = $ate; }
if(in_array($tipo, ['deposito','levantamento','transferencia_debito','transferencia_credito'], true)){ $where[] = "t.tipo=?"; $params[] = $tipo; }
$sqlWhere = 'WHERE '.implode(' AND ', $where);

$por = 20;
$pagina = max(1, (int)($_GET['p'] ?? 1));
$st = $pdo->prepare("SELECT COUNT(*) FROM transacoes t $sqlWhere");
$st->execute($params);
$total = (int)$st->fetchColumn();
$offset = ($pagina - 1) * $por;
$st = $pdo->prepare("SELECT t.*, u.username FROM transacoes t LEFT JOIN utilizadores u ON u.id=t.utilizador_id $sqlWhere ORDER BY t.id DESC LIMIT $por OFFSET $offset");
$st->execute($params);
$movs = $st->fetchAll();

pagina_inicio('Conta '.$c['numero_conta']);
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2 d-print-none">
    <h4 class="mb-0">Conta <?=e($c['numero_conta'])?> <?=badge_estado($c['estado'])?></h4>
    <div class="d-flex gap-2 flex-wrap">
        <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
        <button onclick="window.print()" class="btn btn-outline-secondary">Imprimir extracto</button>
        <?php if(tem_perfil(PERFIS_OPERADORES) && $c['estado']==='activa'): ?>
            <a href="<?=BASE_URL?>transacoes/operacao.php?tipo=deposito&conta=<?=urlencode($c['numero_conta'])?>" class="btn btn-success">Depositar</a>
            <a href="<?=BASE_URL?>transacoes/operacao.php?tipo=levantamento&conta=<?=urlencode($c['numero_conta'])?>" class="btn btn-danger">Levantar</a>
            <a href="<?=BASE_URL?>transacoes/operacao.php?tipo=transferencia&conta=<?=urlencode($c['numero_conta'])?>" class="btn btn-banco">Transferir</a>
        <?php endif; ?>
    </div>
</div>

<div class="card p-4 shadow-sm mb-3">
    <div class="row g-3 align-items-center">
        <div class="col-md-4">
            <div class="text-muted small">Saldo disponível</div>
            <div class="saldo-grande"><?=fmt_valor($c['saldo'], $c['moeda'])?></div>
        </div>
        <div class="col-md-3"><div class="text-muted small">Titular</div>
            <a href="<?=BASE_URL?>clientes/ver.php?id=<?=$c['cliente_id']?>"><?=e($c['nome_completo'])?></a><br><small class="text-muted"><?=e($c['codigo_cliente'])?> · NUIT <?=e($c['nuit'])?></small></div>
        <div class="col-md-3"><div class="text-muted small">Tipo / Moeda</div><?=e($c['tipo_nome'])?> · <?=e($c['moeda'])?></div>
        <div class="col-md-2"><div class="text-muted small">Aberta em</div><?=fmt_data($c['criada_em'])?></div>
    </div>
    <?php if(tem_perfil(PERFIS_GESTAO) && $c['estado'] !== 'encerrada'): ?>
    <hr class="d-print-none">
    <div class="d-flex gap-2 d-print-none">
        <?php foreach(($c['estado']==='activa' ? ['bloquear'=>['Bloquear conta','warning']] : ['desbloquear'=>['Desbloquear conta','success']]) + ['encerrar'=>['Encerrar conta','outline-danger']] as $acao => $info): ?>
        <form method="POST" action="estado.php" onsubmit="return confirm('Confirma: <?=e($info[0])?>?')">
            <input type="hidden" name="csrf" value="<?=csrf_token()?>">
            <input type="hidden" name="id" value="<?=$c['id']?>">
            <input type="hidden" name="acao" value="<?=$acao?>">
            <button class="btn btn-sm btn-<?=$info[1]?>"><?=e($info[0])?></button>
        </form>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<form class="card p-3 shadow-sm mb-3 d-print-none" method="GET">
    <input type="hidden" name="id" value="<?=$id?>">
    <div class="row g-2">
        <div class="col-md-3"><label class="form-label small mb-0">De</label><input type="date" name="de" value="<?=e($de)?>" class="form-control"></div>
        <div class="col-md-3"><label class="form-label small mb-0">Até</label><input type="date" name="ate" value="<?=e($ate)?>" class="form-control"></div>
        <div class="col-md-3"><label class="form-label small mb-0">Tipo</label>
            <select name="tipo" class="form-select">
                <option value="">Todos</option>
                <?php foreach(['deposito','levantamento','transferencia_debito','transferencia_credito'] as $t): ?>
                    <option value="<?=$t?>" <?=$tipo===$t?'selected':''?>><?=e(rotulo_tipo($t))?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3 d-flex align-items-end gap-2">
            <button class="btn btn-outline-primary w-100">Filtrar</button>
            <a href="ver.php?id=<?=$id?>" class="btn btn-outline-secondary">Limpar</a>
        </div>
    </div>
</form>

<h5>Extracto <small class="text-muted fs-6">(<?=$total?> movimentos)</small></h5>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th>Data</th><th>Referência</th><th>Tipo</th><th>Descrição</th><th class="text-end">Montante</th><th class="text-end">Saldo após</th></tr></thead>
    <tbody>
    <?php if(!$movs): ?><tr><td colspan="6" class="text-center text-muted py-4">Sem movimentos.</td></tr><?php endif; ?>
    <?php foreach($movs as $m): $entrada = in_array($m['tipo'], ['deposito','transferencia_credito'], true); ?>
        <tr>
            <td class="text-nowrap"><?=fmt_data($m['criada_em'])?></td>
            <td><a href="<?=BASE_URL?>transacoes/comprovativo.php?ref=<?=urlencode($m['referencia'])?>"><?=e($m['referencia'])?></a></td>
            <td><?=e(rotulo_tipo($m['tipo']))?></td>
            <td><?=e($m['descricao'])?></td>
            <td class="text-end <?=$entrada?'valor-pos':'valor-neg'?>"><?=$entrada?'+':'-'?><?=fmt_valor($m['montante'], $c['moeda'])?></td>
            <td class="text-end"><?=fmt_valor($m['saldo_depois'], $c['moeda'])?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php render_paginacao($total, $por, $pagina); ?>
<?php pagina_fim(); ?>
