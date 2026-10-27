<?php
require_once __DIR__.'/includes/auth.php';
if(isset($_SESSION['utilizador_id'])){
    registarAuditoria($pdo, 'logout', 'Fim de sessão');
}
$_SESSION = [];
session_destroy();
header('Location: login.php');
exit;
