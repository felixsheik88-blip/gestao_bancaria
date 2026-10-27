<?php
// Depósito, levantamento e transferência: ?tipo=deposito|levantamento|transferencia
require __DIR__.'/../includes/auth.php';
verificarPerfil(PERFIS_OPERADORES);

$tipo = $_GET['tipo'] ?? $_POST['tipo'] ?? 'deposito';
if(!in_array($tipo, ['deposito','levantamento','transferencia'], true)) $tipo = 'deposito';
$titulos = ['deposito'=>'Depósito','levantamento'=>'Levantamento','transferencia'=>'Transferência'];

$d = [
    'conta'     => strtoupper(trim($_GET['conta'] ?? '')),
    'destino'   => '',
    'montante'  => '',
    'descricao' => '',
    'idem'      => nova_idempotency_key(),
];
$erros = [];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    verificar_csrf();
    $d['conta']     = strtoupper(trim($_POST['conta'] ?? ''));
    $d['destino']   = strtoupper(trim($_POST['destino'] ?? ''));
    $d['montante']  = trim($_POST['montante'] ?? '');
    $d['descricao'] = mb_substr(trim($_POST['descricao'] ?? ''), 0, 200);
    $d['idem']      = $_POST['idem'] ?? '';

    if(!preg_match('/^[a-f0-9]{32}$/', $d['idem'])) { $erros[] = 'Pedido inválido. Recarregue a página.'; $d['idem'] = nova_idempotency_key(); }
    if(!preg_match('/^[A-Z0-9-]{6,20}$/', $d['conta'])) $erros[] = ($tipo==='transferencia' ? 'Conta de origem' : 'Número da conta').' inválido.';
    if($tipo === 'transferencia' && !preg_match('/^[A-Z0-9-]{6,20}$/', $d['destino'])) $erros[] = 'Conta de destino inválida.';
    $cent = para_centavos($d['montante']);
    if($cent === null || $cent <= 0) $erros[] = 'Montante inválido. Use números maiores que zero, ex.: 1500 ou 1500,50.';

    if(!$erros){
        try {
            if($tipo === 'transferencia') $ref = op_transferencia($pdo, $d['conta'], $d['destino'], $cent, $d['descricao'], $d['idem']);
            else $ref = op_simples($pdo, $tipo, $d['conta'], $cent, $d['descricao'], $d['idem']);
            flash_set('success', $titulos[$tipo].' concluído com sucesso.');
            header('Location: comprovativo.php?ref='.urlencode($ref)); exit;
        } catch(RuntimeException $ex){
            $erros[] = $ex->getMessage();
        } catch(Throwable $ex){
            error_log($ex->getMessage());
            $erros[] = 'Erro interno ao processar a operação. Nenhum valor foi movimentado.';
        }
    }
}

pagina_inicio($titulos[$tipo]);
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Nova operação</h4>
    <a href="index.php" class="btn btn-outline-secondary">Voltar</a>
</div>

<ul class="nav nav-pills mb-3">
    <?php foreach($titulos as $k => $t): ?>
        <li class="nav-item"><a class="nav-link <?=$tipo===$k?'active':''?>" style="<?=$tipo===$k?'background:#0a1931':''?>" href="?tipo=<?=$k?>"><?=$t?></a></li>
    <?php endforeach; ?>
</ul>

<?php if($erros): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach($erros as $er) echo '<li>'.e($er).'</li>'; ?></ul></div>
<?php endif; ?>

<form method="POST" class="card p-4 shadow-sm" data-once style="max-width:640px">
    <input type="hidden" name="csrf" value="<?=csrf_token()?>">
    <input type="hidden" name="tipo" value="<?=e($tipo)?>">
    <input type="hidden" name="idem" value="<?=e($d['idem'])?>">

    <div class="mb-3">
        <label class="form-label"><?=$tipo==='transferencia' ? 'Conta de origem' : 'Número da conta'?> *</label>
        <input name="conta" id="conta" class="form-control" value="<?=e($d['conta'])?>" placeholder="MZ00-0000-000000" autocomplete="off" required>
        <div class="form-text" id="info_conta"></div>
    </div>

    <?php if($tipo === 'transferencia'): ?>
    <div class="mb-3">
        <label class="form-label">Conta de destino *</label>
        <input name="destino" id="destino" class="form-control" value="<?=e($d['destino'])?>" placeholder="MZ00-0000-000000" autocomplete="off" required>
        <div class="form-text" id="info_destino"></div>
    </div>
    <?php endif; ?>

    <div class="mb-3">
        <label class="form-label">Montante *</label>
        <input name="montante" class="form-control" value="<?=e($d['montante'])?>" placeholder="0,00" inputmode="decimal" required>
    </div>
    <div class="mb-3">
        <label class="form-label"><?=$tipo==='transferencia' ? 'Motivo' : 'Descrição'?> <small class="text-muted">(opcional)</small></label>
        <input name="descricao" class="form-control" value="<?=e($d['descricao'])?>" maxlength="200">
    </div>
    <button type="submit" class="btn btn-banco px-4">Confirmar <?=e(mb_strtolower($titulos[$tipo]))?></button>
</form>

<script>
// Mostra o titular (e saldo) ao escrever o número da conta, para confirmar antes de submeter
function ligarConsulta(inputId, infoId, mostrarSaldo){
    var inp = document.getElementById(inputId), box = document.getElementById(infoId), t;
    if(!inp) return;
    function consultar(){
        var n = inp.value.trim().toUpperCase();
        box.className = 'form-text'; box.textContent = '';
        if(n.length < 6) return;
        fetch('conta_info.php?numero=' + encodeURIComponent(n), {credentials:'same-origin'})
            .then(function(r){ return r.json(); })
            .then(function(j){
                if(!j.ok){ box.className = 'form-text text-danger'; box.textContent = 'Conta não encontrada.'; return; }
                var txt = j.titular + ' · ' + j.moeda + ' · ' + j.estado;
                if(mostrarSaldo) txt += ' · saldo ' + j.saldo + ' ' + j.moeda;
                box.className = 'form-text ' + (j.estado === 'activa' ? 'text-success' : 'text-warning');
                box.textContent = txt;
            }).catch(function(){});
    }
    inp.addEventListener('input', function(){ clearTimeout(t); t = setTimeout(consultar, 350); });
    consultar();
}
ligarConsulta('conta', 'info_conta', <?=$tipo==='deposito' ? 'false' : 'true'?>);
ligarConsulta('destino', 'info_destino', false);
</script>
<?php pagina_fim(); ?>
