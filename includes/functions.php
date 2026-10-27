<?php
// Perfis com acesso a cada tipo de funcionalidade
const PERFIS_STAFF       = ['Administrador','Gerente','Caixa','Auditor'];
const PERFIS_OPERADORES  = ['Administrador','Gerente','Caixa'];
const PERFIS_GESTAO      = ['Administrador','Gerente'];
const PERFIS_AUDITORIA   = ['Administrador','Gerente','Auditor'];

function csrf_token(){
    if(empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf'];
}
function verificar_csrf(){
    if(!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')){
        die('Token CSRF inválido');
    }
}
function e($v){
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}
function tem_perfil(array $perfis): bool {
    return in_array($_SESSION['perfil'] ?? '', $perfis, true);
}

// ---------- Mensagens (flash) ----------
function flash_set(string $tipo, string $msg): void {
    $_SESSION['flash'][] = ['tipo'=>$tipo, 'msg'=>$msg];
}
function flash_mostrar(): void {
    if(empty($_SESSION['flash'])) return;
    foreach($_SESSION['flash'] as $f){
        echo '<div class="alert alert-'.e($f['tipo']).' alert-dismissible fade show d-print-none" role="alert">'
            .e($f['msg']).'<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    unset($_SESSION['flash']);
}

// ---------- Formatação ----------
function fmt_valor($v, string $moeda='MZN'): string {
    return number_format((float)$v, 2, ',', ' ').' '.$moeda;
}
function fmt_data($ts): string {
    return $ts ? date('d/m/Y H:i', strtotime($ts)) : '-';
}
function badge_estado(string $estado): string {
    $map = ['activo'=>'success','activa'=>'success','concluida'=>'success',
            'inactivo'=>'secondary','bloqueada'=>'warning','bloqueado'=>'warning','pendente'=>'warning',
            'encerrada'=>'dark','rejeitada'=>'danger'];
    return '<span class="badge bg-'.($map[$estado] ?? 'secondary').'">'.e(ucfirst($estado)).'</span>';
}
function rotulo_tipo(string $t): string {
    $m = ['deposito'=>'Depósito','levantamento'=>'Levantamento',
          'transferencia_debito'=>'Transferência (débito)','transferencia_credito'=>'Transferência (crédito)'];
    return $m[$t] ?? $t;
}

// ---------- Dinheiro (sempre em centavos inteiros, nunca float) ----------
// Aceita "1500", "1500.50" ou "1500,50". Devolve centavos ou null se inválido.
function para_centavos($txt): ?int {
    $t = preg_replace('/\s+/', '', (string)$txt);
    if(!preg_match('/^(\d{1,12})(?:[.,](\d{1,2}))?$/', $t, $m)) return null;
    $dec = isset($m[2]) ? str_pad($m[2], 2, '0') : '00';
    return ((int)$m[1]) * 100 + (int)$dec;
}
function de_centavos(int $c): string {
    return sprintf('%d.%02d', intdiv($c, 100), $c % 100);
}

// ---------- Validações ----------
function normalizar_telefone(string $t): ?string {
    $t = preg_replace('/[\s\-()]/', '', $t);
    if(preg_match('/^(?:\+?258)?(8[2-7]\d{7})$/', $t, $m)) return '+258'.$m[1];
    return null;
}
function data_valida(?string $s): ?string {
    if(!$s) return null;
    $d = DateTime::createFromFormat('Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}
function idade_anos(string $data): int {
    return (new DateTime($data))->diff(new DateTime('today'))->y;
}
function nova_idempotency_key(): string {
    return bin2hex(random_bytes(16));
}

// ---------- Paginação ----------
function render_paginacao(int $total, int $porPagina, int $pagina): void {
    $paginas = (int)ceil($total / $porPagina);
    if($paginas <= 1) return;
    $q = $_GET;
    echo '<nav class="d-print-none"><ul class="pagination pagination-sm justify-content-center mt-3">';
    $ini = max(1, $pagina - 3); $fim = min($paginas, $pagina + 3);
    if($ini > 1){ $q['p'] = 1; echo '<li class="page-item"><a class="page-link" href="?'.e(http_build_query($q)).'">&laquo;</a></li>'; }
    for($i = $ini; $i <= $fim; $i++){
        $q['p'] = $i;
        echo '<li class="page-item'.($i === $pagina ? ' active' : '').'"><a class="page-link" href="?'.e(http_build_query($q)).'">'.$i.'</a></li>';
    }
    if($fim < $paginas){ $q['p'] = $paginas; echo '<li class="page-item"><a class="page-link" href="?'.e(http_build_query($q)).'">&raquo;</a></li>'; }
    echo '</ul></nav>';
}
?>
