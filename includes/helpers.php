<?php
// ============================================================
// CLUBE SDM - Helpers de Negocio (Cashback, Credito)
// ============================================================

require_once __DIR__ . '/db.php';

function getCashbackPercentual($clubId, $ano, $mes) {
    $db = getDB();
    $stmt = $db->prepare("SELECT percentual FROM cashback_mensal WHERE club_id = ? AND ano = ? AND mes = ?");
    $stmt->execute([$clubId, $ano, $mes]);
    $row = $stmt->fetch();
    return $row ? floatval($row['percentual']) : 5.00;
}

function getCashbackAtual($clubId) {
    return getCashbackPercentual($clubId, date('Y'), date('n'));
}

function getExpiracaoMeses($clubId) {
    $db = getDB();
    $stmt = $db->prepare("SELECT expiracao_meses FROM clubs WHERE id = ?");
    $stmt->execute([$clubId]);
    $row = $stmt->fetch();
    return $row ? intval($row['expiracao_meses']) : 3;
}

function calcularCreditoCliente($clubId, $clienteId) {
    $db = getDB();

    $stmt = $db->prepare("SELECT COALESCE(SUM(cashback_valor),0) as total_cashback, COALESCE(SUM(valor),0) as total_compras, COUNT(*) as num_compras, MAX(data_compra) as ultima_compra FROM compras WHERE club_id = ? AND cliente_id = ? AND estornada = FALSE");
    $stmt->execute([$clubId, $clienteId]);
    $compras = $stmt->fetch();

    $stmt = $db->prepare("SELECT COALESCE(SUM(valor),0) as total_resgatado FROM resgates WHERE club_id = ? AND cliente_id = ? AND estornado = FALSE");
    $stmt->execute([$clubId, $clienteId]);
    $resgates = $stmt->fetch();

    $creditoTotal = floatval($compras['total_cashback']);
    $totalResgatado = floatval($resgates['total_resgatado']);
    $creditoDisponivel = max(0, $creditoTotal - $totalResgatado);

    $expirado = false;
    $expiracaoMeses = getExpiracaoMeses($clubId);
    if ($compras['ultima_compra']) {
        $limite = date('Y-m-d H:i:s', strtotime("-{$expiracaoMeses} months"));
        if ($compras['ultima_compra'] < $limite) {
            $expirado = true;
            $creditoDisponivel = 0;
        }
    }

    return [
        'total_compras' => floatval($compras['total_compras']),
        'num_compras' => intval($compras['num_compras']),
        'cashback_total' => $creditoTotal,
        'total_resgatado' => $totalResgatado,
        'credito_disponivel' => $creditoDisponivel,
        'ultima_compra' => $compras['ultima_compra'],
        'expirado' => $expirado,
        'expiracao_meses' => $expiracaoMeses
    ];
}
