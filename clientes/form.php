<?php
// Criar (sem ?id) ou editar (?id=) um cliente
require __DIR__.'/../includes/auth.php';
verificarPerfil(PERFIS_OPERADORES);

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$cliente = null;
if($id){
    $st = $pdo->prepare("SELECT * FROM clientes WHERE id=?");
    $st->execute([$id]);
    $cliente = $st->fetch();
    if(!$cliente){
        flash_set('danger', 'Cliente não encontrado.');
        header('Location: index.php'); exit;
    }
}

$d = $cliente ?: ['nome_completo'=>'','data_nascimento'=>'','nuit'=>'','bi_numero'=>'','telefone'=>'','email'=>'','endereco'=>''];
$erros = [];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    verificar_csrf();
    $d['nome_completo']   = trim(preg_replace('/\s+/', ' ', $_POST['nome_completo'] ?? ''));
    $d['data_nascimento'] = trim($_POST['data_nascimento'] ?? '');
    $d['nuit']            = preg_replace('/\s+/', '', $_POST['nuit'] ?? '');
    $d['bi_numero']       = strtoupper(preg_replace('/\s+/', '', $_POST['bi_numero'] ?? ''));
    $d['telefone']        = trim($_POST['telefone'] ?? '');
    $d['email']           = trim($_POST['email'] ?? '');
    $d['endereco']        = trim($_POST['endereco'] ?? '');

    if(mb_strlen($d['nome_completo']) < 3 || mb_strlen($d['nome_completo']) > 150) $erros[] = 'Nome completo: entre 3 e 150 caracteres.';
    $dn = data_valida($d['data_nascimento']);
    if(!$dn) $erros[] = 'Data de nascimento inválida.';
    elseif(strtotime($dn) > time()) $erros[] = 'A data de nascimento não pode estar no futuro.';
    elseif(idade_anos($dn) < 18) $erros[] = 'O cliente tem de ter pelo menos 18 anos.';
    elseif(idade_anos($dn) > 120) $erros[] = 'Data de nascimento improvável.';
    if(!preg_match('/^\d{9}$/', $d['nuit'])) $erros[] = 'NUIT inválido: deve ter 9 dígitos.';
    if(!preg_match('/^[A-Z0-9]{8,20}$/', $d['bi_numero'])) $erros[] = 'Nº do BI inválido: 8 a 20 letras/números, sem espaços.';
    $tel = normalizar_telefone($d['telefone']);
    if(!$tel) $erros[] = 'Telefone inválido. Exemplo: 84 123 4567 ou +258841234567.';
    if($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) $erros[] = 'Email inválido.';
    if(mb_strlen($d['email']) > 100) $erros[] = 'Email demasiado longo.';

    // NUIT e BI únicos
    if(!$erros){
        $st = $pdo->prepare("SELECT nuit, bi_numero FROM clientes WHERE (nuit=? OR bi_numero=?) AND id<>?");
        $st->execute([$d['nuit'], $d['bi_numero'], $id]);
        foreach($st->fetchAll() as $o){
            if($o['nuit'] === $d['nuit']) $erros[] = 'Já existe um cliente com este NUIT.';
            if($o['bi_numero'] === $d['bi_numero']) $erros[] = 'Já existe um cliente com este Nº de BI.';
        }
        $erros = array_values(array_unique($erros));
    }

    if(!$erros){
        $email = $d['email'] !== '' ? $d['email'] : null;
        $end   = $d['endereco'] !== '' ? $d['endereco'] : null;
        try {
            $pdo->beginTransaction();
            if($id){
                $pdo->prepare("UPDATE clientes SET nome_completo=?, data_nascimento=?, nuit=?, bi_numero=?, telefone=?, email=?, endereco=? WHERE id=?")
                    ->execute([$d['nome_completo'], $dn, $d['nuit'], $d['bi_numero'], $tel, $email, $end, $id]);
                registarAuditoria($pdo, 'cliente_editado', 'Cliente #'.$id.' ('.$d['nome_completo'].')');
                $destino = $id;
                $msg = 'Cliente actualizado com sucesso.';
            } else {
                $tmp = 'TMP'.bin2hex(random_bytes(6));
                $pdo->prepare("INSERT INTO clientes (codigo_cliente,nome_completo,data_nascimento,nuit,bi_numero,telefone,email,endereco) VALUES (?,?,?,?,?,?,?,?)")
                    ->execute([$tmp, $d['nome_completo'], $dn, $d['nuit'], $d['bi_numero'], $tel, $email, $end]);
                $destino = (int)$pdo->lastInsertId();
                $codigo = 'CLI-'.str_pad((string)$destino, 6, '0', STR_PAD_LEFT);
                $pdo->prepare("UPDATE clientes SET codigo_cliente=? WHERE id=?")->execute([$codigo, $destino]);
                registarAuditoria($pdo, 'cliente_criado', $codigo.' ('.$d['nome_completo'].')');
                $msg = 'Cliente registado com o código '.$codigo.'. Já pode abrir uma conta.';
            }
            $pdo->commit();
            flash_set('success', $msg);
            header('Location: ver.php?id='.$destino); exit;
        } catch(PDOException $ex){
            if($pdo->inTransaction()) $pdo->rollBack();
            if($ex->getCode() == '23000') $erros[] = 'NUIT ou Nº de BI já registado.';
            else { error_log($ex->getMessage()); $erros[] = 'Erro ao gravar o cliente.'; }
        }
    }
}

pagina_inicio($id ? 'Editar cliente' : 'Novo cliente');
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><?=$id ? 'Editar cliente '.e($cliente['codigo_cliente']) : 'Novo cliente'?></h4>
    <a href="<?=$id ? 'ver.php?id='.$id : 'index.php'?>" class="btn btn-outline-secondary">Cancelar</a>
</div>

<?php if($erros): ?>
<div class="alert alert-danger"><strong>Corrija os erros:</strong><ul class="mb-0">
    <?php foreach($erros as $er) echo '<li>'.e($er).'</li>'; ?>
</ul></div>
<?php endif; ?>

<form method="POST" class="card p-4 shadow-sm" data-once>
    <input type="hidden" name="csrf" value="<?=csrf_token()?>">
    <input type="hidden" name="id" value="<?=$id?>">
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label">Nome completo *</label>
            <input name="nome_completo" class="form-control" value="<?=e($d['nome_completo'])?>" maxlength="150" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Data de nascimento *</label>
            <input type="date" name="data_nascimento" class="form-control" value="<?=e($d['data_nascimento'])?>" max="<?=date('Y-m-d')?>" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">NUIT * <small class="text-muted">(9 dígitos)</small></label>
            <input name="nuit" class="form-control" value="<?=e($d['nuit'])?>" maxlength="9" inputmode="numeric" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Nº do BI *</label>
            <input name="bi_numero" class="form-control" value="<?=e($d['bi_numero'])?>" maxlength="20" required>
        </div>
        <div class="col-md-4">
            <label class="form-label">Telefone *</label>
            <input name="telefone" class="form-control" value="<?=e($d['telefone'])?>" placeholder="84 123 4567" required>
        </div>
        <div class="col-md-6">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?=e($d['email'])?>" maxlength="100">
        </div>
        <div class="col-md-6">
            <label class="form-label">Endereço</label>
            <input name="endereco" class="form-control" value="<?=e($d['endereco'])?>">
        </div>
    </div>
    <div class="mt-4"><button type="submit" class="btn btn-banco px-4"><?=$id ? 'Guardar alterações' : 'Registar cliente'?></button></div>
</form>
<?php pagina_fim(); ?>
