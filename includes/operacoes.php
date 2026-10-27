<?php
// Lógica bancária: todas as operações correm dentro de transação, com a(s) linha(s)
// da conta bloqueadas (SELECT ... FOR UPDATE) até ao COMMIT.
require_once __DIR__.'/functions.php';

const SALDO_MAX_CENT = 999999999999999; // limite de DECIMAL(15,2)

function gerarNumeroContaUnico(PDO $pdo): string {
    do {
        $n = gerarNumeroConta();
        $s = $pdo->prepare("SELECT 1 FROM contas WHERE numero_conta=?");
        $s->execute([$n]);
    } while($s->fetch());
    return $n;
}

// Referência de transferência "TRF-AAAAMMDD-XXXXXXXX-D" -> "TRF-AAAAMMDD-XXXXXXXX"
function ref_base(string $ref): string {
    return preg_replace('/^(TRF-[0-9]{8}-[A-F0-9]{8})-[DC]$/', '$1', $ref);
}

function ref_por_idem(PDO $pdo, string $idem): ?string {
    $s = $pdo->prepare("SELECT referencia FROM transacoes WHERE idempotency_key=?");
    $s->execute([$idem]);
    $r = $s->fetchColumn();
    return $r ? ref_base($r) : null;
}

// Aplica um movimento a uma conta JÁ BLOQUEADA (dentro de transação).
function aplicar_movimento(PDO $pdo, array $conta, string $tipo, int $cent, string $ref, ?string $desc, ?string $idem): void {
    $antes  = para_centavos($conta['saldo']);
    $sinal  = in_array($tipo, ['deposito','transferencia_credito'], true) ? 1 : -1;
    $depois = $antes + $sinal * $cent;
    if($depois < 0) throw new RuntimeException('Saldo insuficiente na conta '.$conta['numero_conta'].'.');
    if($depois > SALDO_MAX_CENT) throw new RuntimeException('O saldo máximo permitido seria excedido.');

    $pdo->prepare("UPDATE contas SET saldo=? WHERE id=?")->execute([de_centavos($depois), $conta['id']]);
    $pdo->prepare("INSERT INTO transacoes
        (referencia,tipo,conta_id,montante,saldo_antes,saldo_depois,descricao,estado,utilizador_id,idempotency_key)
        VALUES (?,?,?,?,?,?,?,'concluida',?,?)")
        ->execute([$ref, $tipo, $conta['id'], de_centavos($cent), de_centavos($antes), de_centavos($depois),
                   $desc, $_SESSION['utilizador_id'] ?? null, $idem]);
}

// Depósito ou levantamento. Devolve a referência da operação.
function op_simples(PDO $pdo, string $tipo, string $numero, int $cent, string $desc, string $idem): string {
    $prefixo = $tipo === 'deposito' ? 'DEP' : 'LEV';
    $pdo->beginTransaction();
    try {
        $dup = ref_por_idem($pdo, $idem);
        if($dup){ $pdo->rollBack(); return $dup; }

        $s = $pdo->prepare("SELECT * FROM contas WHERE numero_conta=? FOR UPDATE");
        $s->execute([$numero]);
        $conta = $s->fetch();
        if(!$conta) throw new RuntimeException('Conta não encontrada.');
        if($conta['estado'] !== 'activa') throw new RuntimeException('A conta '.$numero.' não está activa ('.$conta['estado'].').');

        $ref = gerarReferencia($prefixo);
        aplicar_movimento($pdo, $conta, $tipo, $cent, $ref, $desc !== '' ? $desc : rotulo_tipo($tipo), $idem);
        registarAuditoria($pdo, $tipo, "$ref | conta $numero | ".de_centavos($cent)." ".$conta['moeda']);
        $pdo->commit();
        return $ref;
    } catch(PDOException $ex){
        if($pdo->inTransaction()) $pdo->rollBack();
        if($ex->getCode() == '23000'){ // envio duplicado (chave de idempotência já usada)
            $r = ref_por_idem($pdo, $idem);
            if($r) return $r;
        }
        throw $ex;
    } catch(Throwable $ex){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
}

// Transferência entre duas contas. Devolve a referência (TRF-...).
function op_transferencia(PDO $pdo, string $origem, string $destino, int $cent, string $motivo, string $idem): string {
    if($origem === $destino) throw new RuntimeException('A conta de origem e a de destino têm de ser diferentes.');
    $pdo->beginTransaction();
    try {
        $dup = ref_por_idem($pdo, $idem.'D');
        if($dup){ $pdo->rollBack(); return $dup; }

        // Bloqueia as duas contas sempre na mesma ordem (por id) para evitar deadlocks
        $s = $pdo->prepare("SELECT * FROM contas WHERE numero_conta IN (?,?) ORDER BY id FOR UPDATE");
        $s->execute([$origem, $destino]);
        $contas = [];
        foreach($s->fetchAll() as $c) $contas[$c['numero_conta']] = $c;
        if(!isset($contas[$origem]))  throw new RuntimeException('Conta de origem não encontrada.');
        if(!isset($contas[$destino])) throw new RuntimeException('Conta de destino não encontrada.');
        $co = $contas[$origem]; $cd = $contas[$destino];
        if($co['estado'] !== 'activa') throw new RuntimeException('A conta de origem não está activa ('.$co['estado'].').');
        if($cd['estado'] !== 'activa') throw new RuntimeException('A conta de destino não está activa ('.$cd['estado'].').');
        if($co['moeda'] !== $cd['moeda']) throw new RuntimeException('Transferência entre moedas diferentes não é suportada ('.$co['moeda'].' → '.$cd['moeda'].').');

        $ref = gerarReferencia('TRF');
        $pdo->prepare("INSERT INTO transferencias (referencia,conta_origem_id,conta_destino_id,montante,estado,motivo)
                       VALUES (?,?,?,?,'concluida',?)")
            ->execute([$ref, $co['id'], $cd['id'], de_centavos($cent), $motivo !== '' ? $motivo : null]);

        aplicar_movimento($pdo, $co, 'transferencia_debito',  $cent, $ref.'-D', 'Transferência para '.$destino, $idem.'D');
        aplicar_movimento($pdo, $cd, 'transferencia_credito', $cent, $ref.'-C', 'Transferência de '.$origem,  $idem.'C');
        registarAuditoria($pdo, 'transferencia', "$ref | $origem -> $destino | ".de_centavos($cent)." ".$co['moeda']);
        $pdo->commit();
        return $ref;
    } catch(PDOException $ex){
        if($pdo->inTransaction()) $pdo->rollBack();
        if($ex->getCode() == '23000'){
            $r = ref_por_idem($pdo, $idem.'D');
            if($r) return $r;
        }
        throw $ex;
    } catch(Throwable $ex){
        if($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
}
?>
