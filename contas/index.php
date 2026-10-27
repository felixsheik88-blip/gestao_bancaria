<?php
require __DIR__.'/../includes/auth.php';
verificarPerfil(PERFIS_STAFF);

$q = trim($_GET['q'] ?? '');
$estado = $_GET['estado'] ?? '';
$moeda = $_GET['moeda'] ?? '';
$where = []; $params = [];
if($q !== ''){
    $where[] = "(c.numero_conta LIKE ? OR cl.nome_completo LIKE ? OR cl.nuit LIKE ? OR cl.codigo_cliente LIKE ?)";
    $like = '%'.$q.'%';
    array_push($params, $like, $like, $like, $like);
}
if(in_array($estado, ['activa','bloqueada','encerrada'], true)){ $where[] = "c.estado=?"; $params[] = $estado; }
if(in_array($moeda, ['MZN','USD'], true)){ $where[] = "c.moeda=?"; $params[] = $moeda; }
$sqlWhere = $where ? 'WHERE '.implode(' AND ', $where) : '';
$from = "FROM contas c JOIN clientes cl ON cl.id=c.cliente_id JOIN tipos_conta t ON t.id=c.tipo_conta_id";

$por = 15;
$pagina = max(1, (int)($_GET['p'] ?? 1));
$st = $pdo->prepare("SELECT COUNT(*) $from $sqlWhere");
$st->execute($params);
$total = (int)$st->fetchColumn();
$offset = ($pagina - 1) * $por;

$st = $pdo->prepare("SELECT c.*, cl.nome_completo, cl.codigo_cliente, t.nome AS tipo_nome $from $sqlWhere ORDER BY c.id DESC LIMIT $por OFFSET $offset");
$st->execute($params);
$contas = $st->fetchAll();

pagina_inicio('Contas');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Contas <small class="text-muted fs-6">(<?=$total?>)</small></h4>
    <?php if(tem_perfil(PERFIS_OPERADORES)): ?>
        <a href="nova.php" class="btn btn-banco">+ Abrir conta</a>
    <?php endif; ?>
</div>

<form class="card p-3 shadow-sm mb-3" method="GET">
    <div class="row g-2">
        <div class="col-md-5"><input name="q" value="<?=e($q)?>" class="form-control" placeholder="Nº da conta, nome, NUIT ou código do cliente"></div>
        <div class="col-md-2">
            <select name="estado" class="form-select">
                <option value="">Todos os estados</option>
                <?php foreach(['activa','bloqueada','encerrada'] as $s): ?>
                    <option value="<?=$s?>" <?=$estado===$s?'selected':''?>><?=ucfirst($s)?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="moeda" class="form-select">
                <option value="">Todas as moedas</option>
                <option value="MZN" <?=$moeda==='MZN'?'selected':''?>>MZN</option>
                <option value="USD" <?=$moeda==='USD'?'selected':''?>>USD</option>
            </select>
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-outline-primary w-100">Filtrar</button>
            <a href="index.php" class="btn btn-outline-secondary">Limpar</a>
        </div>
    </div>
</form>

<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th>Nº da conta</th><th>Titular</th><th>Tipo</th><th class="text-end">Saldo</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php if(!$contas): ?><tr><td colspan="6" class="text-center text-muted py-4">Nenhuma conta encontrada.</td></tr><?php endif; ?>
    <?php foreach($contas as $c): ?>
        <tr>
            <td><a href="ver.php?id=<?=$c['id']?>"><?=e($c['numero_conta'])?></a></td>
            <td><a href="<?=BASE_URL?>clientes/ver.php?id=<?=$c['cliente_id']?>"><?=e($c['nome_completo'])?></a></td>
            <td><?=e($c['tipo_nome'])?></td>
            <td class="text-end"><?=fmt_valor($c['saldo'], $c['moeda'])?></td>
            <td><?=badge_estado($c['estado'])?></td>
            <td class="text-end"><a href="ver.php?id=<?=$c['id']?>" class="btn btn-sm btn-outline-primary">Abrir</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php render_paginacao($total, $por, $pagina); ?>
<?php pagina_fim(); ?>
