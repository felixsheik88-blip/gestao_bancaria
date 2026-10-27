<?php
// Activar / desactivar cliente (só Administrador e Gerente, só via POST)
require __DIR__.'/../includes/auth.php';
verificarPerfil(PERFIS_GESTAO);

if($_SERVER['REQUEST_METHOD'] !== 'POST'){ header('Location: index.php'); exit; }
verificar_csrf();

$id = (int)($_POST['id'] ?? 0);
$acao = $_POST['acao'] ?? '';
$st = $pdo->prepare("SELECT * FROM clientes WHERE id=?");
$st->execute([$id]);
$c = $st->fetch();
if(!$c || !in_array($acao, ['activar','desactivar'], true)){
    flash_set('danger', 'Pedido inválido.');
    header('Location: index.php'); exit;
}

if($acao === 'desactivar'){
    $n = $pdo->prepare("SELECT COUNT(*) FROM contas WHERE cliente_id=? AND estado<>'encerrada'");
    $n->execute([$id]);
    if((int)$n->fetchColumn() > 0){
        flash_set('danger', 'Não é possível desactivar: o cliente ainda tem contas que não estão encerradas.');
        header('Location: ver.php?id='.$id); exit;
    }
    $pdo->prepare("UPDATE clientes SET estado='inactivo' WHERE id=?")->execute([$id]);
    registarAuditoria($pdo, 'cliente_desactivado', $c['codigo_cliente']);
    flash_set('success', 'Cliente desactivado.');
} else {
    $pdo->prepare("UPDATE clientes SET estado='activo' WHERE id=?")->execute([$id]);
    registarAuditoria($pdo, 'cliente_activado', $c['codigo_cliente']);
    flash_set('success', 'Cliente activado.');
}
header('Location: ver.php?id='.$id);
exit;
