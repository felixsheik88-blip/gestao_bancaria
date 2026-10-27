<?php
if (session_status() === PHP_SESSION_NONE) session_start();
define('BASE_URL', 'http://localhost/gestao_bancaria/');
date_default_timezone_set('Africa/Maputo');

function gerarReferencia($prefixo){
    return $prefixo.'-'.date('Ymd').'-'.strtoupper(bin2hex(random_bytes(4)));
}
function gerarNumeroConta(){
    return 'MZ'.random_int(10,99).'-'.random_int(1000,9999).'-'.random_int(100000,999999);
}
?>
