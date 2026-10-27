<?php
// Abertura de conta (com depósito inicial opcional)
require __DIR__.'/../includes/auth.php';
verificarPerfil(PERFIS_OPERADORES);

$clientes = $pdo->query("SELECT id, codigo_cliente, nome_completo, nuit FROM clientes WHERE estado='activo' ORDER BY nome_completo")->fetchAll();
$tipos = $pdo->query("SELECT * FROM tipos_conta ORDER BY id")->fetchAll();

if(empty($_SESSION['form_conta'])) $_SESSION['form_conta'] = bin2hex(random_bytes(16));

$d = ['cliente_id' => (int)($_GET['cliente_id'] ?? 0), 'tipo_conta_id' => 1, 'moeda' => 'MZN', 'deposito' => ''];
$erros = [];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    verificar_csrf();
    $d['cliente_id']    = (int)($_POST['cliente_id'] ?? 0);
    $d['tipo_conta_id'] = (int)($_POST['tipo_conta_id'] ?? 0);
    $d['moeda']         = $_POST['moeda'] ?? '';
    $d['deposito']      = trim($_POST['deposito'] ?? '');

    // Token de uso único: evita criar duas contas por duplo clique / reenvio
    if(!hash_equals($_SESSION['form_conta'], $_POST['form_token'] ?? '')){
        $erros[] = 'Este formulário já foi enviado ou expirou. Verifique a lista de contas antes de tentar novamente.';
    }

    $st = $pdo->prepare("SELECT * FROM clientes WHERE id=? AND estado='activo'");
    $st->execute([$d['cliente_id']]);
    $cli = $st->fetch();
    if(!$cli) $erros[] = 'Seleccione um cliente activo.';

    $st = $pdo->prepare("SELECT * FROM tipos_conta WHERE id=?");
    $st->execute([$d['tipo_conta_id']]);
    if(!$st->fetch()) $erros[] = 'Tipo de conta inválido.';
    if(!in_array($d['moeda'], ['MZN','USD'], true)) $erros[] = 'Moeda inválida.';

    $cent = 0;
    if($d['deposito'] !== ''){
        $cent = para_centavos($d['deposito']);
        if($cent === null) $erros[] = 'Depósito inicial inválido. Use números, ex.: 1500 ou 1500,50.';
    }

    if(!$erros){
        try {
            $pdo->beginTransaction();
            $numero = gerarNumeroContaUnico($pdo);
            $pdo->prepare("INSERT INTO contas (numero_conta, cliente_id, tipo_conta_id, moeda, saldo) VALUES (?,?,?,?,0.00)")
                ->execute([$numero, $d['cliente_id'], $d['tipo_conta_id'], $d['moeda']]);
            $contaId = (int)$pdo->lastInsertId();
            if($cent > 0){
                aplicar_movimento($pdo, ['id'=>$contaId, 'numero_conta'=>$numero, 'saldo'=>'0.00'], 'deposito', $cent,
                    gerarReferencia('DEP'), 'Depósito inicial na abertura da conta', nova_idempotency_key());
            }
            registarAuditoria($pdo, 'conta_aberta', $numero.' | cliente '.$cli['codigo_cliente'].' | '.$d['moeda'].' | inicial '.de_centavos($cent));
            $pdo->commit();
            unset($_SESSION['form_conta']);
            flash_set('success', 'Conta '.$numero.' aberta com sucesso'.($cent > 0 ? ' com depósito inicial de '.fmt_valor(de_centavos($cent), $d['moeda']) : '').'.');
            header('Location: ver.php?id='.$contaId); exit;
        } catch(RuntimeException $ex){
            if($pdo->inTransaction()) $pdo->rollBack();
            $erros[] = $ex->getMessage();
        } catch(Throwable $ex){
            if($pdo->inTransaction()) $pdo->rollBack();
            error_log($ex->getMessage());
            $erros[] = 'Erro ao abrir a conta. Nada foi gravado.';
        }
    }
}

pagina_inicio('Abrir conta');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Abrir conta</h4>
    <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
</div>

<?php if($erros): ?>
<div class="alert alert-danger"><ul class="mb-0"><?php foreach($erros as $er) echo '<li>'.e($er).'</li>'; ?></ul></div>
<?php endif; ?>

<?php if(!$clientes): ?>
    <div class="alert alert-warning">Não há clientes activos. <a href="<?=BASE_URL?>clientes/form.php">Registe um cliente</a> primeiro.</div>
<?php else: ?>
<form method="POST" class="card p-4 shadow-sm" data-once>
    <input type="hidden" name="csrf" value="<?=csrf_token()?>">
    <input type="hidden" name="form_token" value="<?=e($_SESSION['form_conta'])?>">
    <div class="row g-3">
        <div class="col-md-12">
            <label class="form-label">Cliente *</label>
            <select name="cliente_id" class="form-select" required>
                <option value="">-- seleccione --</option>
                <?php foreach($clientes as $c): ?>
                    <option value="<?=$c['id']?>" <?=$d['cliente_id']===(int)$c['id']?'selected':''?>><?=e($c['nome_completo'])?> — <?=e($c['codigo_cliente'])?> (NUIT <?=e($c['nuit'])?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Tipo de conta *</label>
            <select name="tipo_conta_id" class="form-select" required>
                <?php foreach($tipos as $t): ?>
                    <option value="<?=$t['id']?>" <?=(int)$d['tipo_conta_id']===(int)$t['id']?'selected':''?>><?=e($t['nome'])?><?=$t['taxa_juro']>0 ? ' ('.e($t['taxa_juro']).'% juro)' : ''?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Moeda *</label>
            <select name="moeda" class="form-select">
                <option value="MZN" <?=$d['moeda']==='MZN'?'selected':''?>>MZN - Metical</option>
                <option value="USD" <?=$d['moeda']==='USD'?'selected':''?>>USD - Dólar</option>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Depósito inicial <small class="text-muted">(opcional)</small></label>
            <input name="deposito" class="form-control" value="<?=e($d['deposito'])?>" placeholder="0,00" inputmode="decimal">
        </div>
    </div>
    <div class="mt-4"><button type="submit" class="btn btn-banco px-4">Abrir conta</button></div>
</form>
<?php endif; ?>
<?php pagina_fim(); ?>
