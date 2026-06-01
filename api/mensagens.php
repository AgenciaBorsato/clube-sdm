<?php
/**
 * API de Mensagens / Relacionamento (club-scoped)
 * Disparos de WhatsApp em massa por segmento de clientes.
 *
 * Acoes: segmentos (contagens), recipientes (preview), enviar (disparo), historico
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
exigirLogin();

$input = getInput();
$acao = $input['acao'] ?? $_GET['acao'] ?? '';
$db = getDB();
$clubId = getClubId();

const LIMITE_DISPARO = 200;   // teto de destinatarios por disparo
const INTERVALO_MS = 450;     // intervalo entre mensagens (anti-bloqueio)

/**
 * Retorna a base de clientes do clube com saldo de cashback e validade calculados.
 */
function baseClientes($db, $clubId) {
    $stmt = $db->prepare("
        SELECT c.id, c.nome, c.telefone, c.data_nascimento,
               COALESCE(cb.total, 0) AS cashback_total,
               COALESCE(rg.total, 0) AS resgatado,
               cb.ultima_compra,
               cl.expiracao_meses
        FROM clientes c
        JOIN clubs cl ON cl.id = c.club_id
        LEFT JOIN (
            SELECT cliente_id, SUM(cashback_valor) AS total, MAX(data_compra) AS ultima_compra
            FROM compras WHERE club_id = :c1 AND estornada = FALSE GROUP BY cliente_id
        ) cb ON cb.cliente_id = c.id
        LEFT JOIN (
            SELECT cliente_id, SUM(valor) AS total
            FROM resgates WHERE club_id = :c2 AND estornado = FALSE GROUP BY cliente_id
        ) rg ON rg.cliente_id = c.id
        WHERE c.club_id = :c3 AND c.ativo = TRUE AND c.telefone IS NOT NULL AND c.telefone <> ''
    ");
    $stmt->execute([':c1' => $clubId, ':c2' => $clubId, ':c3' => $clubId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $agora = time();
    foreach ($rows as &$r) {
        $r['saldo'] = max(0, (float) $r['cashback_total'] - (float) $r['resgatado']);
        $exp = (int) ($r['expiracao_meses'] ?: 3);
        $r['expirado'] = false;
        $r['dias_para_vencer'] = null;
        if ($r['ultima_compra']) {
            $venc = strtotime($r['ultima_compra'] . " +{$exp} months");
            $r['dias_para_vencer'] = (int) floor(($venc - $agora) / 86400);
            if ($venc < $agora) $r['expirado'] = true;
        }
    }
    return $rows;
}

/** Filtra a base pelo segmento. */
function filtrarSegmento($base, $segmento) {
    $mesAtual = (int) date('n');
    return array_values(array_filter($base, function ($r) use ($segmento, $mesAtual) {
        switch ($segmento) {
            case 'todos':
                return true;
            case 'com_saldo':
                return $r['saldo'] > 0 && !$r['expirado'];
            case 'cashback_vencendo':
                return $r['saldo'] > 0 && !$r['expirado'] && $r['dias_para_vencer'] !== null && $r['dias_para_vencer'] <= 30;
            case 'aniversariantes':
                return $r['data_nascimento'] && (int) date('n', strtotime($r['data_nascimento'])) === $mesAtual;
            case 'inativos':
                // sem compra ou ultima compra ha mais de 60 dias
                return empty($r['ultima_compra']) || (time() - strtotime($r['ultima_compra'])) > 60 * 86400;
            default:
                return false;
        }
    }));
}

switch ($acao) {

    // Contagem de cada segmento (para os cards da UI)
    case 'segmentos': {
        $base = baseClientes($db, $clubId);
        jsonResponse([
            'segmentos' => [
                'todos' => count(filtrarSegmento($base, 'todos')),
                'com_saldo' => count(filtrarSegmento($base, 'com_saldo')),
                'cashback_vencendo' => count(filtrarSegmento($base, 'cashback_vencendo')),
                'aniversariantes' => count(filtrarSegmento($base, 'aniversariantes')),
                'inativos' => count(filtrarSegmento($base, 'inativos')),
            ],
        ]);
        break;
    }

    // Lista (preview) dos destinatarios de um segmento
    case 'recipientes': {
        $segmento = $input['segmento'] ?? $_GET['segmento'] ?? 'todos';
        $base = baseClientes($db, $clubId);
        $lista = filtrarSegmento($base, $segmento);
        $out = array_map(fn($r) => [
            'id' => (int) $r['id'],
            'nome' => $r['nome'],
            'telefone' => $r['telefone'],
            'saldo' => (float) $r['saldo'],
            'dias_para_vencer' => $r['dias_para_vencer'],
        ], $lista);
        jsonResponse(['segmento' => $segmento, 'total' => count($out), 'clientes' => $out]);
        break;
    }

    // Disparo em massa
    case 'enviar': {
        exigirClubAdmin();
        $segmento = $input['segmento'] ?? '';
        $mensagem = trim($input['mensagem'] ?? '');
        if (!$mensagem) jsonResponse(['sucesso' => false, 'erro' => 'Mensagem vazia'], 400);

        // Config de WhatsApp do clube
        $cfgStmt = $db->prepare("SELECT nome, whatsapp_enabled, evolution_instance, evolution_token FROM clubs WHERE id = ?");
        $cfgStmt->execute([$clubId]);
        $cfg = $cfgStmt->fetch();
        $waOn = $cfg && in_array($cfg['whatsapp_enabled'], [true, 't', '1', 1, 'true'], true);
        if (!$waOn || empty($cfg['evolution_instance'])) {
            jsonResponse(['sucesso' => false, 'erro' => 'WhatsApp nao configurado para este clube. Peca ao administrador para ativar.'], 400);
        }

        $base = baseClientes($db, $clubId);
        $lista = filtrarSegmento($base, $segmento);

        $truncado = false;
        if (count($lista) > LIMITE_DISPARO) {
            $lista = array_slice($lista, 0, LIMITE_DISPARO);
            $truncado = true;
        }

        $enviados = 0;
        $falhas = 0;
        foreach ($lista as $r) {
            $msg = montarMensagemCashback($mensagem, [
                'nome' => $r['nome'],
                'saldo' => $r['saldo'],
                'clube' => $cfg['nome'],
                'valor' => 0,
                'cashback' => 0,
            ]);
            $ok = enviarWhatsAppEvolution($cfg['evolution_instance'], $r['telefone'], $msg, $cfg['evolution_token'] ?? null);
            if ($ok) $enviados++; else $falhas++;
            usleep(INTERVALO_MS * 1000);
        }

        // Registra a campanha
        $db->prepare("INSERT INTO campanhas (club_id, segmento, mensagem, total, enviados, falhas, criado_por) VALUES (?, ?, ?, ?, ?, ?, ?)")
           ->execute([$clubId, $segmento, $mensagem, count($lista), $enviados, $falhas, getUserId()]);
        registrarAudit($clubId, 'campanha_enviada', 'campanhas', null, null, ['segmento' => $segmento, 'enviados' => $enviados, 'falhas' => $falhas]);

        jsonResponse([
            'sucesso' => true,
            'enviados' => $enviados,
            'falhas' => $falhas,
            'total' => count($lista),
            'truncado' => $truncado,
        ]);
        break;
    }

    // Historico de campanhas
    case 'historico': {
        $stmt = $db->prepare("
            SELECT ca.id, ca.segmento, ca.mensagem, ca.total, ca.enviados, ca.falhas, ca.criado_em, u.nome AS autor
            FROM campanhas ca LEFT JOIN users u ON u.id = ca.criado_por
            WHERE ca.club_id = ? ORDER BY ca.criado_em DESC LIMIT 50
        ");
        $stmt->execute([$clubId]);
        jsonResponse(['campanhas' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        break;
    }

    default:
        jsonResponse(['erro' => 'Acao invalida'], 400);
}
