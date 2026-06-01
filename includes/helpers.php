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

/**
 * Envia mensagem de texto via Evolution API.
 * URL e API key vem de variaveis de ambiente (EVOLUTION_API_URL, EVOLUTION_API_KEY).
 * Falha silenciosamente (retorna false) — nunca deve travar o fluxo de venda.
 */
function enviarWhatsAppEvolution($instance, $telefone, $mensagem, $token = null) {
    $base = getenv('EVOLUTION_API_URL');
    // Prioriza o token do proprio clube (1 instancia por clube); fallback p/ env global
    $key = ($token !== null && trim((string) $token) !== '') ? $token : getenv('EVOLUTION_API_KEY');
    if (!$base || !$key || !$instance || !$telefone || !$mensagem) return false;

    $numero = preg_replace('/\D/', '', $telefone);
    if ($numero === '') return false;
    if (strlen($numero) <= 11) $numero = '55' . $numero; // DDI Brasil

    $url = rtrim($base, '/') . '/message/sendText/' . rawurlencode($instance);
    $payload = json_encode(['number' => $numero, 'text' => $mensagem], JSON_UNESCAPED_UNICODE);

    try {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'apikey: ' . $key],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 4,
                CURLOPT_TIMEOUT => 6,
            ]);
            curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            return $code >= 200 && $code < 300;
        }
        // fallback sem curl
        $ctx = stream_context_create(['http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\napikey: $key\r\n",
            'content' => $payload,
            'timeout' => 6,
            'ignore_errors' => true,
        ]]);
        return @file_get_contents($url, false, $ctx) !== false;
    } catch (\Throwable $e) {
        return false;
    }
}

/**
 * Monta a mensagem de cashback a partir do template do clube (ou um padrao).
 * Variaveis: {nome} {valor} {cashback} {saldo} {clube}
 */
function montarMensagemCashback($template, $dados) {
    $padrao = "Ola {nome}! Sua compra de {valor} na {clube} gerou {cashback} de cashback. Seu saldo disponivel e {saldo}. Obrigado!";
    $tpl = trim((string) $template) !== '' ? $template : $padrao;
    $fmt = fn($v) => 'R$ ' . number_format((float) $v, 2, ',', '.');
    return strtr($tpl, [
        '{nome}' => $dados['nome'] ?? '',
        '{valor}' => $fmt($dados['valor'] ?? 0),
        '{cashback}' => $fmt($dados['cashback'] ?? 0),
        '{saldo}' => $fmt($dados['saldo'] ?? 0),
        '{clube}' => $dados['clube'] ?? '',
    ]);
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
