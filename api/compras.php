<?php
/**
 * API de Compras - Clube SDM
 *
 * Acoes: registrar, preview, estornar, historico, ultimas,
 *        dashboard, relatorio_mensal, ranking_clientes,
 *        exportar_clientes, exportar_compras
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
// REGISTRAR - registra nova compra para um cliente
// ============================================================
if ($acao === 'registrar') {
    $telefone = preg_replace('/\D/', '', $input['telefone'] ?? '');
    $valor = floatval($input['valor'] ?? 0);
    if (strlen($telefone) < 10) jsonResponse(['sucesso' => false, 'erro' => 'Telefone invalido'], 400);
    if ($valor < 0.01) jsonResponse(['sucesso' => false, 'erro' => 'Valor invalido'], 400);

    // Buscar cliente dentro do clube
    $stmt = $db->prepare("SELECT id, nome FROM clientes WHERE telefone = ? AND club_id = ? AND ativo = TRUE");
    $stmt->execute([$telefone, $clubId]);
    $cliente = $stmt->fetch();
    if (!$cliente) jsonResponse(['sucesso' => false, 'erro' => 'Cliente nao encontrado'], 404);

    // Calcular cashback
    $pct = getCashbackAtual($clubId);
    $cashbackValor = round($valor * ($pct / 100), 2);

    $stmt = $db->prepare("
        INSERT INTO compras (club_id, cliente_id, valor, cashback_percentual, cashback_valor, registrado_por)
        VALUES (?, ?, ?, ?, ?, ?)
        RETURNING id
    ");
    $stmt->execute([$clubId, $cliente['id'], $valor, $pct, $cashbackValor, getUserId()]);
    $compraId = $stmt->fetch()['id'];

    registrarAudit($clubId, 'compra_registrada', 'compras', $compraId, null, [
        'cliente_id' => $cliente['id'], 'valor' => $valor,
        'cashback_percentual' => $pct, 'cashback_valor' => $cashbackValor
    ]);

    // Disparo automatico de WhatsApp (Evolution) — nunca trava a venda se falhar
    try {
        $cfgStmt = $db->prepare("SELECT nome, whatsapp_enabled, evolution_instance, evolution_token, whatsapp_template FROM clubs WHERE id = ?");
        $cfgStmt->execute([$clubId]);
        $cfg = $cfgStmt->fetch();
        $waOn = $cfg && in_array($cfg['whatsapp_enabled'], [true, 't', '1', 1, 'true'], true);
        if ($waOn && !empty($cfg['evolution_instance'])) {
            $credito = calcularCreditoCliente($clubId, $cliente['id']);
            $msg = montarMensagemCashback($cfg['whatsapp_template'], [
                'nome' => $cliente['nome'],
                'valor' => $valor,
                'cashback' => $cashbackValor,
                'saldo' => $credito['credito_disponivel'],
                'clube' => $cfg['nome'],
            ]);
            enviarWhatsAppEvolution($cfg['evolution_instance'], $telefone, $msg, $cfg['evolution_token'] ?? null);
        }
    } catch (\Throwable $e) { /* nao bloquear a venda */ }

    jsonResponse([
        'sucesso' => true,
        'mensagem' => 'Compra registrada com sucesso',
        'compra' => [
            'id' => $compraId,
            'valor' => $valor,
            'cashback_percentual' => $pct,
            'cashback_valor' => $cashbackValor,
            'cliente_nome' => $cliente['nome']
        ]
    ]);
}

// ============================================================
// PREVIEW - calcula cashback antes de confirmar a compra
// ============================================================
if ($acao === 'preview') {
    $valor = floatval($input['valor'] ?? 0);
    if ($valor < 0.01) jsonResponse(['sucesso' => false, 'erro' => 'Valor invalido'], 400);

    $pct = getCashbackAtual($clubId);
    $cashbackValor = round($valor * ($pct / 100), 2);

    jsonResponse([
        'sucesso' => true,
        'valor' => $valor,
        'cashback_percentual' => $pct,
        'cashback_valor' => $cashbackValor
    ]);
}

// ============================================================
// ESTORNAR - estorna uma compra existente
// ============================================================
if ($acao === 'estornar') {
    $compraId = intval($input['compra_id'] ?? 0);
    $motivo = trim($input['motivo'] ?? 'Estorno administrativo');
    if (!$compraId) jsonResponse(['sucesso' => false, 'erro' => 'ID da compra invalido'], 400);

    // Verificar se a compra existe e pertence ao clube
    $stmt = $db->prepare("SELECT * FROM compras WHERE id = ? AND club_id = ? AND estornada = FALSE");
    $stmt->execute([$compraId, $clubId]);
    $compra = $stmt->fetch();
    if (!$compra) jsonResponse(['sucesso' => false, 'erro' => 'Compra nao encontrada ou ja estornada'], 404);

    $db->prepare("
        UPDATE compras SET estornada = TRUE, data_estorno = NOW(), motivo_estorno = ?
        WHERE id = ? AND club_id = ?
    ")->execute([$motivo, $compraId, $clubId]);

    registrarAudit($clubId, 'compra_estornada', 'compras', $compraId, null, [
        'motivo' => $motivo, 'valor' => floatval($compra['valor'])
    ]);

    jsonResponse(['sucesso' => true, 'mensagem' => 'Compra estornada com sucesso']);
}

// ============================================================
// HISTORICO - historico de compras de um cliente
// ============================================================
if ($acao === 'historico') {
    $clienteId = intval($input['cliente_id'] ?? $_GET['cliente_id'] ?? 0);
    if (!$clienteId) jsonResponse(['sucesso' => false, 'erro' => 'ID do cliente invalido'], 400);

    $stmt = $db->prepare("SELECT * FROM compras WHERE cliente_id = ? AND club_id = ? ORDER BY data_compra ASC");
    $stmt->execute([$clienteId, $clubId]);
    $compras = $stmt->fetchAll();

    $totalValor = 0;
    $totalCashback = 0;
    foreach ($compras as &$c) {
        $c['valor'] = floatval($c['valor']);
        $c['cashback_percentual'] = floatval($c['cashback_percentual']);
        $c['cashback_valor'] = floatval($c['cashback_valor']);
        $c['estornada'] = (bool)$c['estornada'];
        if (!$c['estornada']) {
            $totalValor += $c['valor'];
            $totalCashback += $c['cashback_valor'];
        }
    }

    jsonResponse([
        'sucesso' => true,
        'compras' => $compras,
        'total_valor' => $totalValor,
        'total_cashback' => $totalCashback
    ]);
}

// ============================================================
// ULTIMAS - ultimas compras do clube
// ============================================================
if ($acao === 'ultimas') {
    $limite = min(50, max(1, intval($_GET['limite'] ?? 10)));

    $stmt = $db->prepare("
        SELECT c.*, cl.nome, cl.telefone
        FROM compras c
        JOIN clientes cl ON cl.id = c.cliente_id
        WHERE c.estornada = FALSE AND c.club_id = ?
        ORDER BY c.data_compra DESC
        LIMIT ?
    ");
    $stmt->execute([$clubId, $limite]);

    jsonResponse(['compras' => $stmt->fetchAll()]);
}

// ============================================================
// DASHBOARD - estatisticas gerais do clube
// ============================================================
if ($acao === 'dashboard') {
    $mesAtual = intval(date('n'));
    $anoAtual = intval(date('Y'));
    $stats = [];

    // Total de clientes ativos
    $stmt = $db->prepare("SELECT COUNT(*) as t FROM clientes WHERE ativo = TRUE AND club_id = ?");
    $stmt->execute([$clubId]);
    $stats['total_clientes'] = $stmt->fetch()['t'];

    // Total de compras e vendas
    $stmt = $db->prepare("SELECT COUNT(*) as t, COALESCE(SUM(valor),0) as v FROM compras WHERE estornada = FALSE AND club_id = ?");
    $stmt->execute([$clubId]);
    $row = $stmt->fetch();
    $stats['total_compras'] = $row['t'];
    $stats['total_vendas'] = floatval($row['v']);

    // Vendas do mes atual
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(valor),0) as t FROM compras
        WHERE estornada = FALSE AND club_id = ?
          AND EXTRACT(MONTH FROM data_compra) = ?
          AND EXTRACT(YEAR FROM data_compra) = ?
    ");
    $stmt->execute([$clubId, $mesAtual, $anoAtual]);
    $stats['vendas_mes'] = floatval($stmt->fetch()['t']);

    // Cashback atual
    $stats['cashback_atual'] = getCashbackAtual($clubId);

    // Total de cashback gerado
    $stmt = $db->prepare("SELECT COALESCE(SUM(cashback_valor),0) as t FROM compras WHERE estornada = FALSE AND club_id = ?");
    $stmt->execute([$clubId]);
    $stats['total_cashback_gerado'] = floatval($stmt->fetch()['t']);

    // Total resgatado
    $stmt = $db->prepare("SELECT COALESCE(SUM(valor),0) as t FROM resgates WHERE estornado = FALSE AND club_id = ?");
    $stmt->execute([$clubId]);
    $stats['total_resgatado'] = floatval($stmt->fetch()['t']);

    jsonResponse($stats);
}

// ============================================================
// RELATORIO MENSAL - compras agrupadas por mes
// ============================================================
if ($acao === 'relatorio_mensal') {
    $ano = intval($_GET['ano'] ?? date('Y'));

    $stmt = $db->prepare("
        SELECT EXTRACT(MONTH FROM data_compra)::int as mes,
               COUNT(*) as num_compras,
               SUM(valor) as total_vendas,
               SUM(cashback_valor) as total_cashback
        FROM compras
        WHERE EXTRACT(YEAR FROM data_compra) = ? AND estornada = FALSE AND club_id = ?
        GROUP BY EXTRACT(MONTH FROM data_compra)
        ORDER BY mes
    ");
    $stmt->execute([$ano, $clubId]);
    $dados = $stmt->fetchAll();

    // Mapear meses existentes
    $existentes = [];
    foreach ($dados as $d) $existentes[$d['mes']] = $d;

    // Preencher todos os 12 meses
    $meses = [];
    for ($m = 1; $m <= 12; $m++) {
        $meses[] = [
            'mes' => $m,
            'num_compras' => intval($existentes[$m]['num_compras'] ?? 0),
            'total_vendas' => floatval($existentes[$m]['total_vendas'] ?? 0),
            'total_cashback' => floatval($existentes[$m]['total_cashback'] ?? 0)
        ];
    }

    jsonResponse(['ano' => $ano, 'meses' => $meses]);
}

// ============================================================
// RANKING CLIENTES - clientes com mais compras
// ============================================================
if ($acao === 'ranking_clientes') {
    $limite = min(50, max(5, intval($_GET['limite'] ?? 20)));

    // PostgreSQL exige que todas as colunas nao-agregadas estejam no GROUP BY
    $stmt = $db->prepare("
        SELECT cl.id, cl.nome, cl.telefone,
               COUNT(c.id) as num_compras,
               COALESCE(SUM(c.valor),0) as total_compras
        FROM clientes cl
        LEFT JOIN compras c ON c.cliente_id = cl.id AND c.estornada = FALSE AND c.club_id = ?
        WHERE cl.ativo = TRUE AND cl.club_id = ?
        GROUP BY cl.id, cl.nome, cl.telefone
        ORDER BY total_compras DESC
        LIMIT ?
    ");
    $stmt->execute([$clubId, $clubId, $limite]);

    jsonResponse(['ranking' => $stmt->fetchAll()]);
}

// ============================================================
// EXPORTAR CLIENTES - CSV com dados dos clientes
// ============================================================
if ($acao === 'exportar_clientes') {
    $stmt = $db->prepare("
        SELECT c.nome, c.cpf, c.telefone, c.email, c.endereco, c.data_nascimento, c.data_cadastro
        FROM clientes c
        WHERE c.ativo = TRUE AND c.club_id = ?
        ORDER BY c.nome
    ");
    $stmt->execute([$clubId]);
    $clientes = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=clientes_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['Nome', 'CPF', 'Telefone', 'Email', 'Endereco', 'Data Nascimento', 'Data Cadastro'], ';');

    foreach ($clientes as $cl) {
        $cl['cpf'] = preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $cl['cpf']);
        $cl['telefone'] = preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $cl['telefone']);
        fputcsv($out, $cl, ';');
    }
    fclose($out);
    exit;
}

// ============================================================
// EXPORTAR COMPRAS - CSV com historico de compras
// ============================================================
if ($acao === 'exportar_compras') {
    $stmt = $db->prepare("
        SELECT cl.nome, cl.telefone, c.valor, c.cashback_percentual, c.cashback_valor, c.data_compra,
               CASE WHEN c.estornada = TRUE THEN 'ESTORNADA' ELSE 'OK' END as status
        FROM compras c
        JOIN clientes cl ON cl.id = c.cliente_id
        WHERE c.club_id = ?
        ORDER BY c.data_compra DESC
    ");
    $stmt->execute([$clubId]);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=compras_' . date('Y-m-d') . '.csv');
    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
    fputcsv($out, ['Cliente', 'Telefone', 'Valor', 'Cashback %', 'Cashback R$', 'Data', 'Status'], ';');

    while ($row = $stmt->fetch()) {
        $row['telefone'] = preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $row['telefone']);
        fputcsv($out, $row, ';');
    }
    fclose($out);
    exit;
}

jsonResponse(['erro' => 'Acao invalida'], 400);
