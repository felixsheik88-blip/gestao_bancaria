<?php
require __DIR__.'/../includes/auth.php';
verificarPerfil(PERFIS_STAFF);

$id = (int)($_GET['id'] ?? 0);
$st = $pdo->prepare("SELECT * FROM clientes WHERE id=?");
$st->execute([$id]);
$c = $st->fetch();
if(!$c){
    flash_set('danger', 'Cliente não encontrado.');
    header('Location: index.php'); exit;
}
$st = $pdo->prepare("SELECT ct.*, t.nome AS tipo_nome FROM contas ct JOIN tipos_conta t ON t.id=ct.tipo_conta_id WHERE ct.cliente_id=? ORDER BY ct.id DESC");
$st->execute([$id]);
$contas = $st->fetchAll();

pagina_inicio('Cliente '.$c['codigo_cliente']);
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h4 class="mb-0"><?=e($c['nome_completo'])?> <?=badge_estado($c['estado'])?></h4>
    <div class="d-flex gap-2 flex-wrap">
        <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
        <?php if(tem_perfil(PERFIS_OPERADORES)): ?>
            <a href="form.php?id=<?=$c['id']?>" class="btn btn-outline-primary">Editar</a>
            <?php if($c['estado']==='activo'): ?>
                <a href="<?=BASE_URL?>contas/nova.php?cliente_id=<?=$c['id']?>" class="btn btn-banco">+ Abrir conta</a>
            <?php endif; ?>
        <?php endif; ?>
        <?php if(tem_perfil(PERFIS_GESTAO)): ?>
            <form method="POST" action="estado.php" onsubmit="return confirm('Confirma a alteração do estado do cliente?')">
                <input type="hidden" name="csrf" value="<?=csrf_token()?>">
                <input type="hidden" name="id" value="<?=$c['id']?>">
                <input type="hidden" name="acao" value="<?=$c['estado']==='activo' ? 'desactivar' : 'activar'?>">
                <button class="btn btn-outline-<?=$c['estado']==='activo' ? 'danger' : 'success'?>"><?=$c['estado']==='activo' ? 'Desactivar' : 'Activar'?></button>
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="card p-4 shadow-sm mb-4">
    <div class="row g-3">
        <div class="col-md-3"><div class="text-muted small">Código</div><?=e($c['codigo_cliente'])?></div>
        <div class="col-md-3"><div class="text-muted small">Data de nascimento</div><?=date('d/m/Y', strtotime($c['data_nascimento']))?> (<?=idade_anos($c['data_nascimento'])?> anos)</div>
        <div class="col-md-3"><div class="text-muted small">NUIT</div><?=e($c['nuit'])?></div>
        <div class="col-md-3"><div class="text-muted small">Nº do BI</div><?=e($c['bi_numero'])?></div>
        <div class="col-md-3"><div class="text-muted small">Telefone</div><?=e($c['telefone'])?></div>
        <div class="col-md-3"><div class="text-muted small">Email</div><?=e($c['email'] ?: '-')?></div>
        <div class="col-md-6"><div class="text-muted small">Endereço</div><?=e($c['endereco'] ?: '-')?></div>
        <div class="col-md-3"><div class="text-muted small">Registado em</div><?=fmt_data($c['criado_em'])?></div>
    </div>
</div>

<h5>Contas do cliente</h5>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-hover align-middle mb-0">
    <thead class="table-light"><tr><th>Nº da conta</th><th>Tipo</th><th>Moeda</th><th class="text-end">Saldo</th><th>Estado</th><th></th></tr></thead>
    <tbody>
    <?php if(!$contas): ?><tr><td colspan="6" class="text-center text-muted py-4">Este cliente ainda não tem contas.</td></tr><?php endif; ?>
    <?php foreach($contas as $ct): ?>
        <tr>
            <td><a href="<?=BASE_URL?>contas/ver.php?id=<?=$ct['id']?>"><?=e($ct['numero_conta'])?></a></td>
            <td><?=e($ct['tipo_nome'])?></td>
            <td><?=e($ct['moeda'])?></td>
            <td class="text-end"><?=fmt_valor($ct['saldo'], $ct['moeda'])?></td>
            <td><?=badge_estado($ct['estado'])?></td>
            <td class="text-end"><a href="<?=BASE_URL?>contas/ver.php?id=<?=$ct['id']?>" class="btn btn-sm btn-outline-primary">Abrir</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></div>
<?php pagina_fim(); ?>
