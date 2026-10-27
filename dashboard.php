<?php require 'includes/auth.php'; verificarLogin();
$clientes = $pdo->query("SELECT COUNT(*) FROM clientes WHERE estado='activo'")->fetchColumn();
$contas = $pdo->query("SELECT COUNT(*) FROM contas WHERE estado='activa'")->fetchColumn();
$depositos = $pdo->query("SELECT COALESCE(SUM(montante),0) FROM transacoes WHERE tipo='deposito' AND estado='concluida'")->fetchColumn();
$levantamentos = $pdo->query("SELECT COALESCE(SUM(montante),0) FROM transacoes WHERE tipo='levantamento' AND estado='concluida'")->fetchColumn();
$saldoMZN = $pdo->query("SELECT COALESCE(SUM(saldo),0) FROM contas WHERE moeda='MZN' AND estado<>'encerrada'")->fetchColumn();
$saldoUSD = $pdo->query("SELECT COALESCE(SUM(saldo),0) FROM contas WHERE moeda='USD' AND estado<>'encerrada'")->fetchColumn();
$movHoje = $pdo->query("SELECT COUNT(*) FROM transacoes WHERE DATE(criada_em)=CURDATE()")->fetchColumn();
$ultimas = $pdo->query("SELECT t.*, c.numero_conta, c.moeda FROM transacoes t JOIN contas c ON c.id=t.conta_id ORDER BY t.id DESC LIMIT 6")->fetchAll();

// Depósitos vs levantamentos nos últimos 7 dias
$dias = []; $dep = []; $lev = [];
for($i = 6; $i >= 0; $i--){ $k = date('Y-m-d', strtotime("-$i day")); $dias[$k] = date('d/m', strtotime($k)); $dep[$k] = 0; $lev[$k] = 0; }
$rows = $pdo->query("SELECT DATE(criada_em) AS d, tipo, SUM(montante) AS total FROM transacoes
    WHERE tipo IN ('deposito','levantamento') AND estado='concluida' AND criada_em >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(criada_em), tipo")->fetchAll();
foreach($rows as $r){
    if(!isset($dias[$r['d']])) continue;
    if($r['tipo'] === 'deposito') $dep[$r['d']] = (float)$r['total']; else $lev[$r['d']] = (float)$r['total'];
}
?>
<!DOCTYPE html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Dashboard - SGB</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="<?=BASE_URL?>assets/css/style.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script></head>
<body style="background:#f5f7fb">
<?php include 'includes/header.php'; ?>
<div class="container mt-4">
<?php flash_mostrar(); ?>
<div class="row g-3">
<div class="col-md-3"><div class="card p-3 shadow-sm"><h6>Clientes</h6><h3><?=$clientes?></h3></div></div>
<div class="col-md-3"><div class="card p-3 shadow-sm"><h6>Contas Activas</h6><h3><?=$contas?></h3></div></div>
<div class="col-md-3"><div class="card p-3 shadow-sm text-success"><h6>Depósitos (MZN/USD)</h6><h3><?=number_format($depositos,2,',',' ')?></h3></div></div>
<div class="col-md-3"><div class="card p-3 shadow-sm text-danger"><h6>Levantamentos (MZN/USD)</h6><h3><?=number_format($levantamentos,2,',',' ')?></h3></div></div>
</div>
<div class="row g-3 mt-1">
<div class="col-md-4"><div class="card p-3 shadow-sm"><h6>Saldo total em contas (MZN)</h6><h3><?=number_format($saldoMZN,2,',',' ')?></h3></div></div>
<div class="col-md-4"><div class="card p-3 shadow-sm"><h6>Saldo total em contas (USD)</h6><h3><?=number_format($saldoUSD,2,',',' ')?></h3></div></div>
<div class="col-md-4"><div class="card p-3 shadow-sm"><h6>Movimentos hoje</h6><h3><?=$movHoje?></h3></div></div>
</div>
<div class="row mt-4 g-3"><div class="col-md-8"><div class="card p-3"><h6>Depósitos vs Levantamentos (últimos 7 dias)</h6><canvas id="grafico"></canvas></div></div>
<div class="col-md-4"><div class="card p-3"><h6>Últimas Transações</h6>
<?php if(!$ultimas): ?><p class="text-muted small mb-0">Ainda sem transações.</p><?php else: ?>
<table class="table table-sm mb-0"><?php foreach($ultimas as $t): $ent = in_array($t['tipo'], ['deposito','transferencia_credito'], true); ?>
<tr><td><a href="<?=BASE_URL?>transacoes/comprovativo.php?ref=<?=urlencode($t['referencia'])?>"><small><?=e($t['numero_conta'])?></small></a><br><small class="text-muted"><?=e(rotulo_tipo($t['tipo']))?></small></td>
<td class="text-end <?=$ent?'valor-pos':'valor-neg'?>"><?=$ent?'+':'-'?><?=number_format((float)$t['montante'],2,',',' ')?> <?=e($t['moeda'])?></td></tr>
<?php endforeach;?></table>
<?php endif; ?></div></div></div>
</div>
<script>
new Chart(document.getElementById('grafico'), {
    type: 'bar',
    data: {
        labels: <?=json_encode(array_values($dias))?>,
        datasets: [
            {label: 'Depósitos', data: <?=json_encode(array_values($dep))?>, backgroundColor: '#0a1931'},
            {label: 'Levantamentos', data: <?=json_encode(array_values($lev))?>, backgroundColor: '#dc3545'}
        ]
    }
});
</script>
<?php include 'includes/footer.php'; ?>
</body></html>
