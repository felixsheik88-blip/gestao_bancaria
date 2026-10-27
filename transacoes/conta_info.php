<?php
// Consulta rápida (JSON) usada nos formulários de operações
require __DIR__.'/../includes/auth.php';
verificarPerfil(PERFIS_OPERADORES);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$numero = strtoupper(trim($_GET['numero'] ?? ''));
if(!preg_match('/^[A-Z0-9-]{6,20}$/', $numero)){ echo json_encode(['ok'=>false]); exit; }

$st = $pdo->prepare("SELECT c.saldo, c.moeda, c.estado, cl.nome_completo FROM contas c JOIN clientes cl ON cl.id=c.cliente_id WHERE c.numero_conta=?");
$st->execute([$numero]);
$c = $st->fetch();
if(!$c){ echo json_encode(['ok'=>false]); exit; }

echo json_encode([
    'ok'      => true,
    'titular' => $c['nome_completo'],
    'moeda'   => $c['moeda'],
    'estado'  => $c['estado'],
    'saldo'   => number_format((float)$c['saldo'], 2, ',', ' '),
]);
