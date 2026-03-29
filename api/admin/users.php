<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
exigirSuperAdmin();
$input = getInput();
$acao = $input['acao'] ?? $_GET['acao'] ?? '';
$db = getDB();

switch ($acao) {

    case 'listar':
        $busca = $input['busca'] ?? $_GET['busca'] ?? '';
        $club_id = $input['club_id'] ?? $_GET['club_id'] ?? '';
        $role = $input['role'] ?? $_GET['role'] ?? '';
        $pagina = max(1, (int) ($input['pagina'] ?? $_GET['pagina'] ?? 1));
        $limite = 20;
        $offset = ($pagina - 1) * $limite;

        $where = [];
        $params = [];

        if ($busca) {
            $where[] = "(u.nome ILIKE :busca OR u.email ILIKE :busca)";
            $params[':busca'] = '%' . $busca . '%';
        }
        if ($club_id) {
            $where[] = "u.club_id = :club_id";
            $params[':club_id'] = (int) $club_id;
        }
        if ($role) {
            $where[] = "u.role = :role";
            $params[':role'] = $role;
        }

        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        // Total count
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM usuarios u" . $whereSql);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        // Paginated results
        $sql = "
            SELECT
                u.id, u.nome, u.email, u.role, u.club_id,
                c.nome AS club_nome,
                u.ativo, u.ultimo_login, u.criado_em
            FROM usuarios u
            LEFT JOIN clubes c ON c.id = u.club_id
        " . $whereSql . " ORDER BY u.criado_em DESC LIMIT :limite OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse([
            'usuarios' => $usuarios,
            'total' => $total,
            'pagina' => $pagina,
            'total_paginas' => ceil($total / $limite),
        ]);
        break;

    case 'criar':
        $nome = trim($input['nome'] ?? '');
        $email = trim($input['email'] ?? '');
        $senha = $input['senha'] ?? '';
        $role = trim($input['role'] ?? '');
        $club_id = $input['club_id'] ?? null;

        if (!$nome || !$email || !$senha || !$role) {
            jsonResponse(['erro' => 'Nome, email, senha e role sao obrigatorios'], 400);
        }

        if (!in_array($role, ['CLUB_ADMIN', 'OPERATOR'])) {
            jsonResponse(['erro' => 'Role deve ser CLUB_ADMIN ou OPERATOR'], 400);
        }

        if (strlen($senha) < 8) {
            jsonResponse(['erro' => 'Senha deve ter no minimo 8 caracteres'], 400);
        }

        if (!$club_id) {
            jsonResponse(['erro' => 'club_id e obrigatorio para este role'], 400);
        }

        // Validar unicidade do email
        $stmtEmail = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email");
        $stmtEmail->execute([':email' => $email]);
        if ((int) $stmtEmail->fetchColumn() > 0) {
            jsonResponse(['erro' => 'Email ja cadastrado'], 409);
        }

        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);

        $stmt = $db->prepare("
            INSERT INTO usuarios (nome, email, senha, role, club_id)
            VALUES (:nome, :email, :senha, :role, :club_id)
            RETURNING id
        ");
        $stmt->execute([
            ':nome' => $nome,
            ':email' => $email,
            ':senha' => $senhaHash,
            ':role' => $role,
            ':club_id' => $club_id,
        ]);

        $id = $stmt->fetchColumn();

        registrarAudit('usuario_criado', ['user_id' => $id, 'nome' => $nome, 'email' => $email, 'role' => $role, 'club_id' => $club_id]);

        jsonResponse(['sucesso' => true, 'id' => $id]);
        break;

    case 'editar':
        $id = (int) ($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['erro' => 'ID do usuario e obrigatorio'], 400);
        }

        $campos = [];
        $params = [':id' => $id];

        $editaveis = ['nome', 'email', 'role', 'club_id', 'ativo'];

        foreach ($editaveis as $campo) {
            if (isset($input[$campo])) {
                if ($campo === 'ativo') {
                    $campos[] = "$campo = :$campo";
                    $params[":$campo"] = $input[$campo] ? 'TRUE' : 'FALSE';
                } else {
                    $campos[] = "$campo = :$campo";
                    $params[":$campo"] = $input[$campo];
                }
            }
        }

        if (empty($campos)) {
            jsonResponse(['erro' => 'Nenhum campo para atualizar'], 400);
        }

        // Se email estiver sendo alterado, validar unicidade
        if (isset($input['email'])) {
            $stmtEmail = $db->prepare("SELECT COUNT(*) FROM usuarios WHERE email = :email AND id != :check_id");
            $stmtEmail->execute([':email' => $input['email'], ':check_id' => $id]);
            if ((int) $stmtEmail->fetchColumn() > 0) {
                jsonResponse(['erro' => 'Email ja cadastrado para outro usuario'], 409);
            }
        }

        $sql = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        registrarAudit('usuario_editado', ['user_id' => $id, 'campos' => array_keys(array_diff_key($params, [':id' => true]))]);

        jsonResponse(['sucesso' => true]);
        break;

    case 'desativar':
        $id = (int) ($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['erro' => 'ID do usuario e obrigatorio'], 400);
        }

        $stmt = $db->prepare("UPDATE usuarios SET ativo = FALSE WHERE id = :id");
        $stmt->execute([':id' => $id]);

        registrarAudit('usuario_desativado', ['user_id' => $id]);

        jsonResponse(['sucesso' => true]);
        break;

    case 'ativar':
        $id = (int) ($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['erro' => 'ID do usuario e obrigatorio'], 400);
        }

        $stmt = $db->prepare("UPDATE usuarios SET ativo = TRUE WHERE id = :id");
        $stmt->execute([':id' => $id]);

        registrarAudit('usuario_ativado', ['user_id' => $id]);

        jsonResponse(['sucesso' => true]);
        break;

    case 'resetar_senha':
        $id = (int) ($input['id'] ?? 0);
        $nova_senha = $input['nova_senha'] ?? '';

        if (!$id) {
            jsonResponse(['erro' => 'ID do usuario e obrigatorio'], 400);
        }

        if (strlen($nova_senha) < 8) {
            jsonResponse(['erro' => 'Nova senha deve ter no minimo 8 caracteres'], 400);
        }

        $senhaHash = password_hash($nova_senha, PASSWORD_DEFAULT);

        $stmt = $db->prepare("UPDATE usuarios SET senha = :senha WHERE id = :id");
        $stmt->execute([':senha' => $senhaHash, ':id' => $id]);

        registrarAudit('senha_resetada', ['user_id' => $id]);

        jsonResponse(['sucesso' => true]);
        break;

    default:
        jsonResponse(['erro' => 'Acao invalida'], 400);
        break;
}
