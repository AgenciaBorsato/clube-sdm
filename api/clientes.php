<?php
/**
 * API de Clientes - Clube SDM
 *
 * Acoes: listar, cadastrar, editar, buscar, excluir
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
// LISTAR - lista clientes do clube com paginacao e busca
// ============================================================
if ($acao === 'listar') {
    $busca = $input['busca'] ?? $_GET['busca'] ?? '';
    $pagina = max(1, intval($_GET['pagina'] ?? 1));
    $porPagina = 50;
    $offset = ($pagina - 1) * $porPagina;

    $sql = "SELECT * FROM clientes WHERE ativo = TRUE AND club_id = ?";
    $sqlCount = "SELECT COUNT(*) as total FROM clientes WHERE ativo = TRUE AND club_id = ?";
    $params = [$clubId];

    if ($busca) {
        $limpo = preg_replace('/\D/', '', $busca);
        $where = " AND (nome ILIKE ? OR cpf LIKE ? OR telefone LIKE ?)";
        $sql .= $where;
        $sqlCount .= $where;
        $params[] = "%$busca%";
        $params[] = "%$limpo%";
        $params[] = "%$limpo%";
    }

    // Contagem total
    $stmtC = $db->prepare($sqlCount);
    $stmtC->execute($params);
    $total = $stmtC->fetch()['total'];

    // Resultados paginados
    $sql .= " ORDER BY nome LIMIT ? OFFSET ?";
    $params[] = $porPagina;
    $params[] = $offset;
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $clientes = $stmt->fetchAll();

    // Enriquecer com credito calculado
    foreach ($clientes as &$cl) {
        $info = calcularCreditoCliente($clubId, $cl['id']);
        $cl['total_compras'] = $info['total_compras'];
        $cl['credito_disponivel'] = $info['credito_disponivel'];
        $cl['expirado'] = $info['expirado'];
    }

    jsonResponse([
        'clientes' => $clientes,
        'total' => intval($total),
        'pagina' => $pagina,
        'total_paginas' => ceil($total / $porPagina)
    ]);
}

// ============================================================
// CADASTRAR - cria novo cliente no clube
// ============================================================
if ($acao === 'cadastrar') {
    $nome = trim($input['nome'] ?? '');
    $cpf = preg_replace('/\D/', '', $input['cpf'] ?? '');
    $telefone = preg_replace('/\D/', '', $input['telefone'] ?? '');
    $email = trim($input['email'] ?? '');
    $endereco = trim($input['endereco'] ?? '');
    $cidade = trim($input['cidade'] ?? '');
    $estado = trim($input['estado'] ?? '');
    $cep = preg_replace('/\D/', '', $input['cep'] ?? '');
    $dataNascimento = trim($input['data_nascimento'] ?? '');
    $observacoes = trim($input['observacoes'] ?? '');

    // Validacoes obrigatorias
    if (!$nome || !$cpf || !$telefone) {
        jsonResponse(['sucesso' => false, 'erro' => 'Preencha todos os campos obrigatorios'], 400);
    }
    if (strlen($cpf) !== 11) {
        jsonResponse(['sucesso' => false, 'erro' => 'CPF invalido'], 400);
    }
    if (strlen($telefone) < 10) {
        jsonResponse(['sucesso' => false, 'erro' => 'Telefone invalido'], 400);
    }

    // Verificar duplicidade dentro do clube
    $stmt = $db->prepare("SELECT id FROM clientes WHERE (cpf = ? OR telefone = ?) AND club_id = ? AND ativo = TRUE");
    $stmt->execute([$cpf, $telefone, $clubId]);
    if ($stmt->fetch()) {
        jsonResponse(['sucesso' => false, 'erro' => 'CPF ou telefone ja cadastrado'], 400);
    }

    // Inserir com campos expandidos
    $stmt = $db->prepare("
        INSERT INTO clientes (club_id, nome, cpf, telefone, email, endereco, cidade, estado, cep, data_nascimento, observacoes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        RETURNING id
    ");
    $stmt->execute([
        $clubId, $nome, $cpf, $telefone, $email, $endereco,
        $cidade, $estado, $cep,
        $dataNascimento ?: null,
        $observacoes
    ]);
    $id = $stmt->fetch()['id'];

    registrarAudit($clubId, 'cliente_cadastrado', 'clientes', $id, null, [
        'nome' => $nome, 'cpf' => $cpf, 'telefone' => $telefone
    ]);

    jsonResponse(['sucesso' => true, 'mensagem' => 'Cliente cadastrado com sucesso', 'id' => $id]);
}

// ============================================================
// EDITAR - atualiza dados do cliente
// ============================================================
if ($acao === 'editar') {
    $id = intval($input['id'] ?? 0);
    $nome = trim($input['nome'] ?? '');
    $cpf = preg_replace('/\D/', '', $input['cpf'] ?? '');
    $telefone = preg_replace('/\D/', '', $input['telefone'] ?? '');
    $email = trim($input['email'] ?? '');
    $endereco = trim($input['endereco'] ?? '');
    $cidade = trim($input['cidade'] ?? '');
    $estado = trim($input['estado'] ?? '');
    $cep = preg_replace('/\D/', '', $input['cep'] ?? '');
    $dataNascimento = trim($input['data_nascimento'] ?? '');
    $observacoes = trim($input['observacoes'] ?? '');

    if (!$id) jsonResponse(['sucesso' => false, 'erro' => 'ID invalido'], 400);
    if (!$nome || !$cpf || !$telefone) {
        jsonResponse(['sucesso' => false, 'erro' => 'Preencha todos os campos obrigatorios'], 400);
    }

    // Verificar duplicidade (excluindo o proprio cliente) dentro do clube
    $stmt = $db->prepare("SELECT id FROM clientes WHERE (cpf = ? OR telefone = ?) AND club_id = ? AND ativo = TRUE AND id != ?");
    $stmt->execute([$cpf, $telefone, $clubId, $id]);
    if ($stmt->fetch()) {
        jsonResponse(['sucesso' => false, 'erro' => 'CPF ou telefone ja pertence a outro cliente'], 400);
    }

    // Atualizar todos os campos
    $stmt = $db->prepare("
        UPDATE clientes
        SET nome = ?, cpf = ?, telefone = ?, email = ?, endereco = ?, cidade = ?,
            estado = ?, cep = ?, data_nascimento = ?, observacoes = ?
        WHERE id = ? AND club_id = ? AND ativo = TRUE
    ");
    $stmt->execute([
        $nome, $cpf, $telefone, $email, $endereco, $cidade,
        $estado, $cep, $dataNascimento ?: null, $observacoes,
        $id, $clubId
    ]);

    registrarAudit($clubId, 'cliente_editado', 'clientes', $id, null, [
        'nome' => $nome, 'cpf' => $cpf
    ]);

    jsonResponse(['sucesso' => true, 'mensagem' => 'Cliente atualizado com sucesso']);
}

// ============================================================
// BUSCAR - busca cliente por telefone, CPF ou nome parcial
// ============================================================
if ($acao === 'buscar') {
    $termo = trim($input['termo'] ?? $_GET['termo'] ?? '');
    if (strlen($termo) < 3) {
        jsonResponse(['sucesso' => false, 'erro' => 'Termo de busca invalido (minimo 3 caracteres)'], 400);
    }

    $limpo = preg_replace('/\D/', '', $termo);

    // Se o termo e numerico (telefone/cpf), busca exata; senao busca por nome
    if (strlen($limpo) >= 10) {
        // Busca por telefone ou CPF exato
        $stmt = $db->prepare("SELECT * FROM clientes WHERE (telefone = ? OR cpf = ?) AND club_id = ? AND ativo = TRUE");
        $stmt->execute([$limpo, $limpo, $clubId]);
    } else {
        // Busca por nome parcial (ILIKE)
        $stmt = $db->prepare("SELECT * FROM clientes WHERE nome ILIKE ? AND club_id = ? AND ativo = TRUE LIMIT 10");
        $stmt->execute(["%$termo%", $clubId]);
    }

    $cliente = $stmt->fetch();
    if (!$cliente) {
        jsonResponse(['sucesso' => false, 'erro' => 'Cliente nao encontrado'], 404);
    }

    // Enriquecer com credito
    $info = calcularCreditoCliente($clubId, $cliente['id']);
    $cliente = array_merge($cliente, $info);
    jsonResponse(['sucesso' => true, 'cliente' => $cliente]);
}

// ============================================================
// EXCLUIR - desativa cliente (soft delete)
// ============================================================
if ($acao === 'excluir') {
    $id = intval($input['id'] ?? 0);
    if (!$id) jsonResponse(['sucesso' => false, 'erro' => 'ID invalido'], 400);

    $db->prepare("UPDATE clientes SET ativo = FALSE WHERE id = ? AND club_id = ?")->execute([$id, $clubId]);

    registrarAudit($clubId, 'cliente_excluido', 'clientes', $id, null, null);

    jsonResponse(['sucesso' => true, 'mensagem' => 'Cliente excluido']);
}

jsonResponse(['erro' => 'Acao invalida'], 400);
