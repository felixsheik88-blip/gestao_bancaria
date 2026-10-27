<?php require 'config/config.php'; require 'config/database.php'; require 'includes/functions.php';
if(isset($_SESSION['utilizador_id'])){ header('Location: dashboard.php'); exit; }
$erro='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    verificar_csrf();
    $stmt=$pdo->prepare("SELECT * FROM utilizadores WHERE username=? AND estado='activo'");
    $stmt->execute([$_POST['username'] ?? '']);
    $u=$stmt->fetch();
    if($u && password_verify($_POST['password'] ?? '', $u['password_hash'])){
        session_regenerate_id(true);
        $_SESSION['utilizador_id']=$u['id']; $_SESSION['username']=$u['username']; $_SESSION['perfil']=$u['perfil'];
        $pdo->prepare("INSERT INTO auditoria (utilizador_id, accao, detalhes, ip) VALUES (?,?,?,?)")
            ->execute([$u['id'],'login','Início de sessão',$_SERVER['REMOTE_ADDR'] ?? '']);
        header('Location: dashboard.php'); exit;
    } else $erro="Credenciais inválidas";
}
?>
<!DOCTYPE html><html lang="pt"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Login - Sistema de Gestão Bancária</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#0a1931;color:#fff;display:flex;align-items:center;justify-content:center;height:100vh}.card{border-radius:15px}</style></head>
<body><div class="card p-4 shadow" style="width:400px;color:#000">
<h4 class="text-center mb-3" style="color:#0a1931">🏦 Banco de Moçambique<br><small>Sistema de Gestão</small></h4>
<?php if($erro) echo "<div class='alert alert-danger'>".e($erro)."</div>"; ?>
<form method="POST">
<input type="hidden" name="csrf" value="<?=csrf_token()?>">
<input name="username" class="form-control mb-3" placeholder="Utilizador (admin)" required>
<input name="password" type="password" class="form-control mb-3" placeholder="Senha: password" required>
<button class="btn w-100" style="background:#0a1931;color:#fff">Entrar</button>
</form><p class="mt-3 text-muted small text-center">Teste: admin / gerente / caixa - senha: password</p></div></body></html>
