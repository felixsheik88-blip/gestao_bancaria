<div class="bg-white shadow-sm p-2 d-print-none"><div class="container d-flex flex-wrap gap-1">
<a href="<?=BASE_URL?>dashboard.php" class="btn btn-sm btn-outline-primary">Dashboard</a>
<a href="<?=BASE_URL?>clientes/index.php" class="btn btn-sm btn-outline-primary">Clientes</a>
<a href="<?=BASE_URL?>contas/index.php" class="btn btn-sm btn-outline-primary">Contas</a>
<a href="<?=BASE_URL?>transacoes/index.php" class="btn btn-sm btn-outline-primary">Operações</a>
<?php if(tem_perfil(PERFIS_AUDITORIA)): ?>
<a href="<?=BASE_URL?>auditoria.php" class="btn btn-sm btn-outline-primary">Auditoria</a>
<?php endif; ?>
</div></div>
