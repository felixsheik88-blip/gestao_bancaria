<?php
// Comprovativo imprimível de uma operação (aceita a referência da transferência ou de qualquer das suas linhas)
require __DIR__.'/../includes/auth.php';
verificarPerfil(PERFIS_STAFF);

$ref = trim($_GET['ref'] ?? '');
$base = ref_base($ref);
$transf = null; $mov = null; $operador = null; $quando = null;

if(strpos($base, 'TRF-') === 0){
    $st = $pdo->prepare("SELECT tf.*, co.numero_conta AS num_origem, co.moeda, clo.nome_completo AS nome_origem,
            cd.numero_conta AS num_destino, cld.nome_completo AS nome_destino
        FROM transferencias tf
        JOIN contas co ON co.id=tf.conta_origem_id JOIN clientes clo ON clo.id=co.cliente_id
        JOIN contas cd ON cd.id=tf.conta_destino_id JOIN clientes cld ON cld.id=cd.cliente_id
        WHERE tf.referencia=?");
    $st->execute([$base]);
    $transf = $st->fetch();
    if($transf){
        $st = $pdo->prepare("SELECT u.username FROM transacoes t LEFT JOIN utilizadores u ON u.id=t.utilizador_id WHERE t.referencia=?");
        $st->execute([$base.'-D']);
        $operador = $st->fetchColumn();
        $quando = $transf['criada_em'];
    }
} else {
    $st = $pdo->prepare("SELECT t.*, c.numero_conta, c.moeda, cl.nome_completo, u.username
        FROM transacoes t JOIN contas c ON c.id=t.conta_id JOIN clientes cl ON cl.id=c.cliente_id
        LEFT JOIN utilizadores u ON u.id=t.utilizador_id WHERE t.referencia=?");
    $st->execute([$ref]);
    $mov = $st->fetch();
    if($mov){ $operador = $mov['username']; $quando = $mov['criada_em']; }
}

if(!$transf && !$mov){
    flash_set('danger', 'Comprovativo não encontrado.');
    header('Location: index.php'); exit;
}

pagina_inicio('Comprovativo');
?>
<div class="d-flex justify-content-between mb-3 d-print-none">
    <a href="index.php" class="btn btn-outline-secondary">Voltar às operações</a>
    <button onclick="window.print()" class="btn btn-banco">Imprimir comprovativo</button>
</div>

<div class="comprovativo">
    <div class="text-center mb-4">
        <h5 style="color:#0a1931" class="mb-0">🏦 Banco de Moçambique</h5>
        <small class="text-muted">Comprovativo de operação</small>
    </div>
    <dl class="row mb-0">
    <?php if($transf): ?>
        <dt class="col-5">Tipo</dt><dd class="col-7">Transferência</dd>
        <dt class="col-5">Referência</dt><dd class="col-7"><?=e($transf['referencia'])?></dd>
        <dt class="col-5">Data e hora</dt><dd class="col-7"><?=fmt_data($quando)?></dd>
        <dt class="col-5">Conta de origem</dt><dd class="col-7"><?=e($transf['num_origem'])?><br><small><?=e($transf['nome_origem'])?></small></dd>
        <dt class="col-5">Conta de destino</dt><dd class="col-7"><?=e($transf['num_destino'])?><br><small><?=e($transf['nome_destino'])?></small></dd>
        <dt class="col-5">Montante</dt><dd class="col-7 valor"><?=fmt_valor($transf['montante'], $transf['moeda'])?></dd>
        <?php if($transf['motivo']): ?><dt class="col-5">Motivo</dt><dd class="col-7"><?=e($transf['motivo'])?></dd><?php endif; ?>
        <dt class="col-5">Estado</dt><dd class="col-7"><?=badge_estado($transf['estado'])?></dd>
    <?php else: ?>
        <dt class="col-5">Tipo</dt><dd class="col-7"><?=e(rotulo_tipo($mov['tipo']))?></dd>
        <dt class="col-5">Referência</dt><dd class="col-7"><?=e($mov['referencia'])?></dd>
        <dt class="col-5">Data e hora</dt><dd class="col-7"><?=fmt_data($quando)?></dd>
        <dt class="col-5">Conta</dt><dd class="col-7"><?=e($mov['numero_conta'])?><br><small><?=e($mov['nome_completo'])?></small></dd>
        <dt class="col-5">Montante</dt><dd class="col-7 valor"><?=fmt_valor($mov['montante'], $mov['moeda'])?></dd>
        <dt class="col-5">Saldo anterior</dt><dd class="col-7"><?=fmt_valor($mov['saldo_antes'], $mov['moeda'])?></dd>
        <dt class="col-5">Saldo actual</dt><dd class="col-7"><?=fmt_valor($mov['saldo_depois'], $mov['moeda'])?></dd>
        <?php if($mov['descricao']): ?><dt class="col-5">Descrição</dt><dd class="col-7"><?=e($mov['descricao'])?></dd><?php endif; ?>
        <dt class="col-5">Estado</dt><dd class="col-7"><?=badge_estado($mov['estado'])?></dd>
    <?php endif; ?>
        <dt class="col-5">Processado por</dt><dd class="col-7"><?=e($operador ?: '-')?></dd>
    </dl>
    <hr>
    <p class="text-center text-muted small mb-0">Documento gerado electronicamente em <?=date('d/m/Y H:i')?>.</p>
</div>
<?php pagina_fim(); ?>
