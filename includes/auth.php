<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/functions.php';
require_once __DIR__.'/layout.php';
require_once __DIR__.'/operacoes.php';

function verificarLogin(){
    if(!isset($_SESSION['utilizador_id'])){
        header('Location: '.BASE_URL.'login.php'); exit;
    }
}
function verificarPerfil($perfisPermitidos = []){
    verificarLogin();
    if(!in_array($_SESSION['perfil'], $perfisPermitidos, true)){
        http_response_code(403);
        die("Acesso negado para o perfil: ".htmlspecialchars($_SESSION['perfil']).". <a href='".BASE_URL."dashboard.php'>Voltar</a>");
    }
}
function registarAuditoria($pdo, $accao, $detalhes){
    $stmt = $pdo->prepare("INSERT INTO auditoria (utilizador_id, accao, detalhes, ip) VALUES (?,?,?,?)");
    $stmt->execute([$_SESSION['utilizador_id'] ?? null, $accao, $detalhes, $_SERVER['REMOTE_ADDR'] ?? '']);
}
?>
