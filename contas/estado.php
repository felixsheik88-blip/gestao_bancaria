<?php
// Bloquear / desbloquear / encerrar conta (só Administrador e Gerente, só via POST)
require __DIR__.'/../includes/auth.php';
verificarPerfil(PERFIS_GESTAO);

if($_SERVER['REQUEST_METHOD'] !== 'POST'){ header('Location: index.php'); exit; }
verificar_csrf();

$id = (int)($_POST['id'] ?? 0);
$acao = $_POST['acao'] ?? '';
if(!in_array($acao, ['bloquear','desbloquear','encerrar'], true)){
    flash_set('danger', 'Pedido inválido.');
    header('Location: index.php'); exit;
}

try {
    $pdo->beginTransaction();
    $st = $pdo->prepare("SELECT * FROM contas WHERE id=? FOR UPDATE");
    $st->execute([$id]);
    $c = $st->fetch();
    if(!$c) throw new RuntimeException('Conta não encontrada.');
    if($c['estado'] === 'encerrada') throw new RuntimeException('Uma conta encerrada não pode ser alterada.');

    if($acao === 'bloquear'){
        if($c['estado'] !== 'activa') throw new RuntimeException('Só contas activas podem ser bloqueadas.');
        $novo = 'bloqueada'; $msg = 'Conta bloqueada.';
    } elseif($acao === 'desbloquear'){
        if($c['estado'] !== 'bloqueada') throw new RuntimeException('A conta não está bloqueada.');
        $novo = 'activa'; $msg = 'Conta desbloqueada.';
    } else {
        if(para_centavos($c['saldo']) !== 0) throw new RuntimeException('Só é possível encerrar uma conta com saldo zero. Levante ou transfira o saldo primeiro.');
        $novo = 'encerrada'; $msg = 'Conta encerrada.';
    }
    $pdo->prepare("UPDATE contas SET estado=? WHERE id=?")->execute([$novo, $id]);
    registarAuditoria($pdo, 'conta_'.$novo, $c['numero_conta']);
    $pdo->commit();
    flash_set('success', $msg);
} catch(RuntimeException $ex){
    if($pdo->inTransaction()) $pdo->rollBack();
    flash_set('danger', $ex->getMessage());
} catch(Throwable $ex){
    if($pdo->inTransaction()) $pdo->rollBack();
    error_log($ex->getMessage());
    flash_set('danger', 'Erro ao alterar o estado da conta.');
}
header('Location: ver.php?id='.$id);
exit;
