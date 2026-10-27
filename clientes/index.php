<?php
require __DIR__.'/../includes/auth.php';
verificarPerfil(PERFIS_STAFF);

$q = trim($_GET['q'] ?? '');
$estado = $_GET['estado'] ?? '';
$where = []; $params = [];
if($q !== ''){
    $where[] = "(c.nome_completo LIKE ? OR c.codigo_cliente LIKE ? OR c.nuit LIKE ? OR c.bi_numero LIKE ? OR c.telefone LIKE ?)";
    $like = '%'.$q.'%';
    array_push($params, $like, $like, $like, $like, $like);
}
if(in_array($estado, ['activo','inactivo'], true)){ $where[] = "c.estado=?"; $params[] = $estado; }
$sqlWhere = $where ? 'WHERE '.implode(' AND ', $where) : '';

$por = 15;
$pagina = max(1, (int)($_GET['p'] ?? 1));
$st = $pdo->prepare("SELECT COUNT(*) FROM clientes c $sqlWhere");
$st->execute($params);
$total = (int)$st->fetchColumn();
$offset = ($pagina - 1) * $por;

$st = $pdo->prepare("SELECT c.*,
    (SELECT COUNT(*) FROM contas ct WHERE ct.cliente_id=c.id AND ct.estado<>'encerrada') AS n_contas
    FROM clientes c $sqlWhere ORDER BY c.id DESC LIMIT $por OFFSET $offset");
$st->execute($params);
$clientes = $st->fetchAll();

pagina_inicio('Clientes');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Clientes <small class="text-muted fs-6">(<?=$total?>)</small></h4>
    <?php if(tem_perfil(PERFIS_OPERADORES)): ?>
        <a href="form.php" class="btn btn-banco">+ Novo cliente</a>
    <?php endif; ?>
</div>

<form class="card p-3 shadow-sm mb-3" method="GET">
    <div class="row g-2">
        <div class="col-md-6"><input name="q" value="<?=e($q)?>" class="form-control" placeholder="Pesquisar por nome, código, NUIT, BI ou telefone"></div>
        <div class="col-md-3">
            <select name="estado" class="form-select">
                <option value="">Todos os estados</option>
                <option value="activo" <?=$estado==='activo'?'selected':''?>>Activos</option>
                <option value="inactivo" <?=$estado==='inactivo'?'selected':''?>>Inactivos</option>
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
    <thead class="table-light"><tr><th>Código</th><th>Nome</th><th>NUIT</th><th>Telefone</th><th>Contas</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php if(!$clientes): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum cliente encontrado.</td></tr>
    <?php endif; ?>
    <?php foreach($clientes as $c): ?>
        <tr>
            <td><?=e($c['codigo_cliente'])?></td>
            <td><a href="ver.php?id=<?=$c['id']?>"><?=e($c['nome_completo'])?></a></td>
            <td><?=e($c['nuit'])?></td>
            <td><?=e($c['telefone'])?></td>
            <td><?=$c['n_contas']?></td>
            <td><?=badge_estado($c['estado'])?></td>
            <td class="text-end text-nowrap">
                <a href="ver.php?id=<?=$c['id']?>" class="btn btn-sm btn-outline-primary">Ver</a>
                <?php if(tem_perfil(PERFIS_OPERADORES)): ?>
                    <a href="form.php?id=<?=$c['id']?>" class="btn btn-sm btn-outline-secondary">Editar</a>
                <?php endif; ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php render_paginacao($total, $por, $pagina); ?>
<?php pagina_fim(); ?>
