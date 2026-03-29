<?php
// ============================================================
// CLUBE SDM - Autenticacao e Controle de Acesso
// ============================================================

require_once __DIR__ . '/db.php';

define('MAX_TENTATIVAS_LOGIN', 5);
define('BLOQUEIO_MINUTOS', 15);

// ===== SESSAO E ROLES =====

function exigirLogin() {
    if (empty($_SESSION['logado']) || $_SESSION['logado'] !== true) {
        jsonResponse(['erro' => 'Acesso nao autorizado. Faca login novamente.'], 401);
    }
}

function exigirRole(array $roles) {
    exigirLogin();
    if (!in_array($_SESSION['user_role'] ?? '', $roles)) {
        jsonResponse(['erro' => 'Permissao insuficiente.'], 403);
    }
}

function exigirSuperAdmin() {
    exigirRole(['SUPER_ADMIN']);
}

function exigirClubAdmin() {
    exigirRole(['SUPER_ADMIN', 'CLUB_ADMIN']);
}

/**
 * Retorna o club_id do contexto atual.
 * SUPER_ADMIN: le do request (pode ver qualquer clube)
 * CLUB_ADMIN/OPERATOR: SEMPRE retorna da sessao (ignora request)
 */
function getClubId($obrigatorio = true) {
    $role = $_SESSION['user_role'] ?? '';

    if ($role === 'SUPER_ADMIN') {
        $input = getInput();
        $clubId = intval($input['club_id'] ?? $_GET['club_id'] ?? 0);
        if ($obrigatorio && !$clubId) {
            jsonResponse(['erro' => 'club_id obrigatorio'], 400);
        }
        return $clubId;
    }

    $clubId = intval($_SESSION['club_id'] ?? 0);
    if ($obrigatorio && !$clubId) {
        jsonResponse(['erro' => 'Sessao invalida. Faca login novamente.'], 401);
    }
    return $clubId;
}

function getUserId() {
    return intval($_SESSION['user_id'] ?? 0);
}

function getUserRole() {
    return $_SESSION['user_role'] ?? '';
}

// ===== CSRF =====

function gerarCSRF() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}

function verificarCSRF($token) {
    if (empty($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        jsonResponse(['erro' => 'Token CSRF invalido. Recarregue a pagina.'], 403);
    }
}

// ===== RATE LIMITING =====

function verificarBloqueioLogin() {
    $db = getDB();
    $ip = getClientIP();
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM login_tentativas WHERE ip = ? AND tentativa_em > NOW() - INTERVAL '" . BLOQUEIO_MINUTOS . " minutes'");
    $stmt->execute([$ip]);
    $row = $stmt->fetch();
    if ($row['c'] >= MAX_TENTATIVAS_LOGIN) {
        jsonResponse(['sucesso' => false, 'erro' => 'Muitas tentativas. Aguarde ' . BLOQUEIO_MINUTOS . ' minutos.'], 429);
    }
}

function registrarTentativaLogin() {
    $db = getDB();
    $db->prepare("INSERT INTO login_tentativas (ip) VALUES (?)")->execute([getClientIP()]);
}

function limparTentativasLogin() {
    $db = getDB();
    $db->prepare("DELETE FROM login_tentativas WHERE ip = ?")->execute([getClientIP()]);
    $db->query("DELETE FROM login_tentativas WHERE tentativa_em < NOW() - INTERVAL '1 hour'");
}

// ===== AUDIT LOG =====

function registrarAudit($acao, $tabela = null, $registroId = null, $dadosAnteriores = null, $dadosNovos = null) {
    $db = getDB();
    $db->prepare("INSERT INTO audit_log (user_id, club_id, acao, tabela, registro_id, dados_anteriores, dados_novos, ip) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
       ->execute([
           getUserId() ?: null,
           $_SESSION['club_id'] ?? null,
           $acao,
           $tabela,
           $registroId,
           $dadosAnteriores ? json_encode($dadosAnteriores) : null,
           $dadosNovos ? json_encode($dadosNovos) : null,
           getClientIP()
       ]);
}
