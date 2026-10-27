<?php
// Registo de auditoria (só leitura)
require __DIR__.'/includes/auth.php';
verificarPerfil(PERFIS_AUDITORIA);

$q = trim($_GET['q'] ?? '');
$where = []; $params = [];
if($q !== ''){
    $where[] = "(a.accao LIKE ? OR a.detalhes LIKE ? OR u.username LIKE ?)";
    $like = '%'.$q.'%';
    array_push($params, $like, $like, $like);
}
$sqlWhere = $where ? 'WHERE '.implode(' AND ', $where) : '';

$por = 25;
$pagina = max(1, (int)($_GET['p'] ?? 1));
$st = $pdo->prepare("SELECT COUNT(*) FROM auditoria a LEFT JOIN utilizadores u ON u.id=a.utilizador_id $sqlWhere");
$st->execute($params);
$total = (int)$st->fetchColumn();
$offset = ($pagina - 1) * $por;
$st = $pdo->prepare("SELECT a.*, u.username FROM auditoria a LEFT JOIN utilizadores u ON u.id=a.utilizador_id $sqlWhere ORDER BY a.id DESC LIMIT $por OFFSET $offset");
$st->execute($params);
$linhas = $st->fetchAll();

pagina_inicio('Auditoria');
?>
<h4 class="mb-3">Auditoria <small class="text-muted fs-6">(<?=$total?> registos)</small></h4>
<form class="card p-3 shadow-sm mb-3" method="GET">
    <div class="row g-2">
        <div class="col-md-9"><input name="q" value="<?=e($q)?>" class="form-control" placeholder="Pesquisar por acção, detalhes ou utilizador"></div>
        <div class="col-md-3 d-flex gap-2"><button class="btn btn-outline-primary w-100">Filtrar</button><a href="auditoria.php" class="btn btn-outline-secondary">Limpar</a></div>
    </div>
</form>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-sm table-hover align-middle mb-0">
    <thead class="table-light"><tr><th>Data</th><th>Utilizador</th><th>Acção</th><th>Detalhes</th><th>IP</th></tr></thead>
    <tbody>
    <?php if(!$linhas): ?><tr><td colspan="5" class="text-center text-muted py-4">Sem registos.</td></tr><?php endif; ?>
    <?php foreach($linhas as $l): ?>
        <tr><td class="text-nowrap"><?=fmt_data($l['criada_em'])?></td><td><?=e($l['username'] ?? '-')?></td><td><?=e($l['accao'])?></td><td><?=e($l['detalhes'])?></td><td><?=e($l['ip'])?></td></tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php render_paginacao($total, $por, $pagina); ?>
<?php pagina_fim(); ?>
