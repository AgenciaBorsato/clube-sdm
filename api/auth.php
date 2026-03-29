<?php
// ============================================================
// CLUBE SDM - Autenticacao (Login/Logout/Senha)
// ============================================================

require_once __DIR__ . '/../includes/auth.php';

$input = getInput();
$acao = $input['acao'] ?? '';
$db = getDB();

// ===== LOGIN =====
if ($acao === 'login') {
    verificarBloqueioLogin();

    $email = trim($input['email'] ?? '');
    $senha = $input['senha'] ?? '';

    if (!$email || !$senha) {
        jsonResponse(['sucesso' => false, 'erro' => 'Email e senha obrigatorios'], 400);
    }

    $stmt = $db->prepare("SELECT u.*, c.nome as club_nome, c.slug as club_slug, c.ativo as club_ativo, c.cor_primaria, c.cor_secundaria, c.logo_url as club_logo FROM users u LEFT JOIN clubs c ON c.id = u.club_id WHERE u.email = ? AND u.ativo = TRUE");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($senha, $user['password_hash'])) {
        registrarTentativaLogin();
        jsonResponse(['sucesso' => false, 'erro' => 'Email ou senha incorretos'], 401);
    }

    // Verificar se clube esta ativo (para usuarios de clube)
    if ($user['club_id'] && !$user['club_ativo']) {
        jsonResponse(['sucesso' => false, 'erro' => 'Clube desativado. Entre em contato com o administrador.'], 403);
    }

    // Regenerar sessao para prevenir fixacao
    session_regenerate_id(true);

    $_SESSION['logado'] = true;
    $_SESSION['user_id'] = intval($user['id']);
    $_SESSION['user_nome'] = $user['nome'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['club_id'] = $user['club_id'] ? intval($user['club_id']) : null;
    $_SESSION['club_nome'] = $user['club_nome'];
    $_SESSION['club_slug'] = $user['club_slug'];
    $csrfToken = gerarCSRF();

    // Atualizar ultimo login
    $db->prepare("UPDATE users SET ultimo_login = NOW() WHERE id = ?")->execute([$user['id']]);

    limparTentativasLogin();
    registrarAudit('login', 'users', $user['id']);

    jsonResponse([
        'sucesso' => true,
        'usuario' => [
            'id' => intval($user['id']),
            'nome' => $user['nome'],
            'email' => $user['email'],
            'role' => $user['role'],
            'club_id' => $user['club_id'] ? intval($user['club_id']) : null,
            'club_nome' => $user['club_nome'],
            'club_slug' => $user['club_slug'],
            'club_logo' => $user['club_logo'],
            'cor_primaria' => $user['cor_primaria'],
            'cor_secundaria' => $user['cor_secundaria']
        ],
        'csrf_token' => $csrfToken
    ]);
}

// ===== VERIFICAR SESSAO =====
if ($acao === 'verificar') {
    if (empty($_SESSION['logado'])) {
        jsonResponse(['logado' => false]);
    }
    jsonResponse([
        'logado' => true,
        'usuario' => [
            'id' => $_SESSION['user_id'],
            'nome' => $_SESSION['user_nome'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['user_role'],
            'club_id' => $_SESSION['club_id'],
            'club_nome' => $_SESSION['club_nome'] ?? null,
            'club_slug' => $_SESSION['club_slug'] ?? null
        ],
        'csrf_token' => $_SESSION['csrf_token'] ?? ''
    ]);
}

// ===== LOGOUT =====
if ($acao === 'logout') {
    registrarAudit('logout', 'users', $_SESSION['user_id'] ?? null);
    session_destroy();
    jsonResponse(['sucesso' => true]);
}

// ===== ALTERAR SENHA =====
if ($acao === 'alterar_senha') {
    exigirLogin();
    $senhaAtual = $input['senha_atual'] ?? '';
    $novaSenha = $input['nova_senha'] ?? '';

    if (!$senhaAtual || !$novaSenha) {
        jsonResponse(['sucesso' => false, 'erro' => 'Preencha todos os campos'], 400);
    }
    if (strlen($novaSenha) < 8) {
        jsonResponse(['sucesso' => false, 'erro' => 'Nova senha deve ter no minimo 8 caracteres'], 400);
    }

    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([getUserId()]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($senhaAtual, $user['password_hash'])) {
        jsonResponse(['sucesso' => false, 'erro' => 'Senha atual incorreta'], 403);
    }

    $novoHash = password_hash($novaSenha, PASSWORD_DEFAULT);
    $db->prepare("UPDATE users SET password_hash = ?, atualizado_em = NOW() WHERE id = ?")->execute([$novoHash, getUserId()]);

    registrarAudit('alterar_senha', 'users', getUserId());
    jsonResponse(['sucesso' => true, 'mensagem' => 'Senha alterada com sucesso']);
}

jsonResponse(['erro' => 'Acao invalida'], 400);
