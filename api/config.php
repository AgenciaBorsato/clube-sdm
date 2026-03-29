<?php
/**
 * API de Configuracao de Cashback - Clube SDM
 *
 * Acoes: listar (publica), salvar (somente admin)
 * Todas as operacoes sao escopadas por club_id.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';

$input = getInput();
$acao = $input['acao'] ?? $_GET['acao'] ?? '';
$db = getDB();

// ============================================================
// LISTAR - lista configuracao de cashback mensal (publica)
// Nao exige login para permitir exibicao do cashback atual
// ============================================================
if ($acao === 'listar') {
    // Para listar, tentamos obter o club_id do usuario logado ou do parametro
    $clubId = null;
    try {
        $clubId = getClubId();
    } catch (\Exception $e) {
        // Se nao estiver logado, aceitar club_id via parametro (para exibicao publica)
        $clubId = intval($input['club_id'] ?? $_GET['club_id'] ?? 0);
    }
    if (!$clubId) jsonResponse(['erro' => 'Club ID necessario'], 400);

    $ano = intval($input['ano'] ?? $_GET['ano'] ?? date('Y'));

    $stmt = $db->prepare("SELECT mes, percentual FROM cashback_mensal WHERE ano = ? AND club_id = ? ORDER BY mes");
    $stmt->execute([$ano, $clubId]);
    $rows = $stmt->fetchAll();

    // Mapear meses existentes
    $existentes = [];
    foreach ($rows as $r) $existentes[$r['mes']] = floatval($r['percentual']);

    // Preencher todos os 12 meses (default 5%)
    $meses = [];
    for ($m = 1; $m <= 12; $m++) {
        $meses[] = ['mes' => $m, 'percentual' => $existentes[$m] ?? 5.00];
    }

    jsonResponse([
        'ano' => $ano,
        'meses' => $meses,
        'cashback_atual' => getCashbackAtual($clubId)
    ]);
}

// ============================================================
// SALVAR - salva configuracao de cashback (somente CLUB_ADMIN ou SUPER_ADMIN)
// ============================================================
if ($acao === 'salvar') {
    exigirLogin();
    exigirClubAdmin(); // Somente SUPER_ADMIN ou CLUB_ADMIN, nao OPERATOR
    $clubId = getClubId();

    $ano = intval($input['ano'] ?? date('Y'));
    $meses = $input['meses'] ?? [];

    if (!is_array($meses) || count($meses) !== 12) {
        jsonResponse(['sucesso' => false, 'erro' => 'Dados invalidos'], 400);
    }

    // Upsert com ON CONFLICT (club_id, ano, mes)
    $stmt = $db->prepare("
        INSERT INTO cashback_mensal (club_id, ano, mes, percentual)
        VALUES (?, ?, ?, ?)
        ON CONFLICT (club_id, ano, mes)
        DO UPDATE SET percentual = EXCLUDED.percentual
    ");

    foreach ($meses as $i => $pct) {
        $stmt->execute([$clubId, $ano, $i + 1, max(0, min(100, floatval($pct)))]);
    }

    registrarAudit($clubId, 'cashback_configurado', 'cashback_mensal', null, null, [
        'ano' => $ano, 'meses' => $meses
    ]);

    jsonResponse(['sucesso' => true, 'mensagem' => 'Configuracoes salvas com sucesso']);
}

jsonResponse(['erro' => 'Acao invalida'], 400);
