<?php
/**
 * API de Resgates - Clube SDM
 *
 * Acoes: resgatar, historico
 * Todas as operacoes sao escopadas por club_id.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
exigirLogin();

$input = getInput();
$acao = $input['acao'] ?? $_GET['acao'] ?? '';
$db = getDB();
$clubId = getClubId();

// ============================================================
// RESGATAR - realiza resgate de credito do cliente
// ============================================================
if ($acao === 'resgatar') {
    $clienteId = intval($input['cliente_id'] ?? 0);
    $valor = floatval($input['valor'] ?? 0);
    if (!$clienteId) jsonResponse(['sucesso' => false, 'erro' => 'Cliente invalido'], 400);
    if ($valor <= 0) jsonResponse(['sucesso' => false, 'erro' => 'Valor invalido'], 400);

    // Verificar credito disponivel dentro do clube
    $info = calcularCreditoCliente($clubId, $clienteId);
    if ($info['expirado']) {
        jsonResponse(['sucesso' => false, 'erro' => 'Creditos expirados'], 400);
    }
    if ($valor > $info['credito_disponivel']) {
        jsonResponse(['sucesso' => false, 'erro' => 'Valor maior que o credito disponivel'], 400);
    }

    // Registrar resgate
    $stmt = $db->prepare("
        INSERT INTO resgates (club_id, cliente_id, valor, registrado_por)
        VALUES (?, ?, ?, ?)
        RETURNING id
    ");
    $stmt->execute([$clubId, $clienteId, $valor, getUserId()]);
    $resgateId = $stmt->fetch()['id'];

    registrarAudit($clubId, 'resgate_realizado', 'resgates', $resgateId, null, [
        'cliente_id' => $clienteId, 'valor' => $valor
    ]);

    jsonResponse(['sucesso' => true, 'mensagem' => 'Resgate realizado com sucesso', 'id' => $resgateId]);
}

// ============================================================
// HISTORICO - historico de resgates de um cliente
// ============================================================
if ($acao === 'historico') {
    $clienteId = intval($input['cliente_id'] ?? $_GET['cliente_id'] ?? 0);
    if (!$clienteId) jsonResponse(['sucesso' => false, 'erro' => 'ID do cliente invalido'], 400);

    $stmt = $db->prepare("SELECT * FROM resgates WHERE cliente_id = ? AND club_id = ? ORDER BY data_resgate DESC");
    $stmt->execute([$clienteId, $clubId]);

    jsonResponse(['sucesso' => true, 'resgates' => $stmt->fetchAll()]);
}

jsonResponse(['erro' => 'Acao invalida'], 400);
