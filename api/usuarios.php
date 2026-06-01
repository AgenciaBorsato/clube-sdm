<?php
/**
 * API de Usuarios (Super Admin)
 * Endpoint consumido pelo painel /index.html
 *
 * Acoes: listar, detalhe, criar, editar, reset_senha
 * Os nomes de campos/parametros seguem o contrato do frontend (usa clube_id/clube_nome).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
exigirSuperAdmin();

$input = getInput();
$acao = $input['acao'] ?? $_GET['acao'] ?? '';
$db = getDB();

switch ($acao) {

    // ====================================================
    // LISTAR (paginado, filtros: busca, clube_id, role)
    // ====================================================
    case 'listar': {
        $busca = $input['busca'] ?? $_GET['busca'] ?? '';
        $clubeId = $input['clube_id'] ?? $_GET['clube_id'] ?? '';
        $role = $input['role'] ?? $_GET['role'] ?? '';
        $page = max(1, (int) ($input['page'] ?? $_GET['page'] ?? 1));
        $perPage = (int) ($input['per_page'] ?? $_GET['per_page'] ?? 10);
        $perPage = max(1, min(1000, $perPage));
        $offset = ($page - 1) * $perPage;

        $where = [];
        $params = [];
        if ($busca) {
            $where[] = "(u.nome ILIKE :busca OR u.email ILIKE :busca)";
            $params[':busca'] = '%' . $busca . '%';
        }
        if ($clubeId !== '' && $clubeId !== null) {
            $where[] = "u.club_id = :clube_id";
            $params[':clube_id'] = (int) $clubeId;
        }
        if ($role) {
            $where[] = "u.role = :role";
            $params[':role'] = $role;
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $stmtCount = $db->prepare("SELECT COUNT(*) FROM users u" . $whereSql);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $sql = "
            SELECT u.id, u.nome, u.email, u.role,
                   u.club_id AS clube_id, c.nome AS clube_nome,
                   u.ultimo_login,
                   CASE WHEN u.ativo THEN 'ativo' ELSE 'inativo' END AS status,
                   u.criado_em
            FROM users u
            LEFT JOIN clubs c ON c.id = u.club_id" . $whereSql . "
            ORDER BY u.criado_em DESC LIMIT :limite OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limite', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        jsonResponse([
            'usuarios' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
        break;
    }

    // ====================================================
    // DETALHE (para edicao)
    // ====================================================
    case 'detalhe': {
        $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) jsonResponse(['erro' => 'ID obrigatorio'], 400);
        $stmt = $db->prepare("SELECT id, nome, email, role, club_id AS clube_id, ativo FROM users WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$usuario) jsonResponse(['erro' => 'Usuario nao encontrado'], 404);
        jsonResponse(['usuario' => $usuario]);
        break;
    }

    // ====================================================
    // CRIAR
    // ====================================================
    case 'criar': {
        $nome = trim($input['nome'] ?? '');
        $email = trim($input['email'] ?? '');
        $senha = $input['senha'] ?? '';
        $role = trim($input['role'] ?? '');
        $clubeId = $input['clube_id'] ?? null;

        if (!$nome || !$email || !$senha || !$role) {
            jsonResponse(['erro' => 'Nome, email, senha e role sao obrigatorios'], 400);
        }
        if (!in_array($role, ['CLUB_ADMIN', 'OPERATOR'])) {
            jsonResponse(['erro' => 'Role deve ser CLUB_ADMIN ou OPERATOR'], 400);
        }
        if (strlen($senha) < 8) {
            jsonResponse(['erro' => 'Senha deve ter no minimo 8 caracteres'], 400);
        }
        if (!$clubeId) {
            jsonResponse(['erro' => 'Selecione o clube'], 400);
        }

        $stmtEmail = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $stmtEmail->execute([':email' => $email]);
        if ((int) $stmtEmail->fetchColumn() > 0) {
            jsonResponse(['erro' => 'Email ja cadastrado'], 409);
        }

        $hash = password_hash($senha, PASSWORD_DEFAULT);
        $stmt = $db->prepare("
            INSERT INTO users (nome, email, password_hash, role, club_id)
            VALUES (:nome, :email, :senha, :role, :club_id)
            RETURNING id
        ");
        $stmt->execute([
            ':nome' => $nome,
            ':email' => $email,
            ':senha' => $hash,
            ':role' => $role,
            ':club_id' => (int) $clubeId,
        ]);
        $id = (int) $stmt->fetchColumn();
        registrarAudit((int) $clubeId, 'usuario_criado', 'users', $id, null, ['nome' => $nome, 'email' => $email, 'role' => $role]);
        jsonResponse(['sucesso' => true, 'id' => $id]);
        break;
    }

    // ====================================================
    // EDITAR
    // ====================================================
    case 'editar': {
        $id = (int) ($input['id'] ?? 0);
        if (!$id) jsonResponse(['erro' => 'ID do usuario obrigatorio'], 400);

        $campos = [];
        $params = [':id' => $id];
        // mapeia clube_id (frontend) -> club_id (banco)
        if (isset($input['clube_id'])) {
            $campos[] = "club_id = :club_id";
            $params[':club_id'] = $input['clube_id'] !== '' ? (int) $input['clube_id'] : null;
        }
        foreach (['nome', 'email', 'role'] as $campo) {
            if (isset($input[$campo])) {
                $campos[] = "$campo = :$campo";
                $params[":$campo"] = $input[$campo];
            }
        }
        if (isset($input['ativo'])) {
            $campos[] = "ativo = :ativo";
            $params[':ativo'] = $input['ativo'] ? 'TRUE' : 'FALSE';
        }
        if (empty($campos)) jsonResponse(['erro' => 'Nenhum campo para atualizar'], 400);

        if (isset($input['email'])) {
            $stmtEmail = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email AND id != :check_id");
            $stmtEmail->execute([':email' => $input['email'], ':check_id' => $id]);
            if ((int) $stmtEmail->fetchColumn() > 0) jsonResponse(['erro' => 'Email ja cadastrado para outro usuario'], 409);
        }

        $campos[] = "atualizado_em = NOW()";
        $sql = "UPDATE users SET " . implode(', ', $campos) . " WHERE id = :id";
        $db->prepare($sql)->execute($params);
        registrarAudit(null, 'usuario_editado', 'users', $id, null, ['campos' => array_keys(array_diff_key($params, [':id' => true]))]);
        jsonResponse(['sucesso' => true]);
        break;
    }

    // ====================================================
    // RESET SENHA
    // ====================================================
    case 'reset_senha': {
        $id = (int) ($input['id'] ?? 0);
        $novaSenha = $input['nova_senha'] ?? '';
        if (!$id) jsonResponse(['erro' => 'ID do usuario obrigatorio'], 400);
        if (strlen($novaSenha) < 8) jsonResponse(['erro' => 'Nova senha deve ter no minimo 8 caracteres'], 400);

        $hash = password_hash($novaSenha, PASSWORD_DEFAULT);
        $db->prepare("UPDATE users SET password_hash = :senha, atualizado_em = NOW() WHERE id = :id")
           ->execute([':senha' => $hash, ':id' => $id]);
        registrarAudit(null, 'senha_resetada', 'users', $id);
        jsonResponse(['sucesso' => true]);
        break;
    }

    default:
        jsonResponse(['erro' => 'Acao invalida'], 400);
}
