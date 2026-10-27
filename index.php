<?php
require_once __DIR__.'/config/config.php';
header('Location: '.(isset($_SESSION['utilizador_id']) ? 'dashboard.php' : 'login.php'));
exit;
