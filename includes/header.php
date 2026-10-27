<nav class="navbar navbar-dark d-print-none" style="background:#0a1931">
<div class="container"><span class="navbar-brand">SGB - Banco MZ</span>
<span class="text-white"><?=e($_SESSION['username'])?> (<?=e($_SESSION['perfil'])?>) | <a href="<?=BASE_URL?>logout.php" class="text-white">Sair</a></span></div></nav>
<?php include __DIR__.'/sidebar.php'; ?>
