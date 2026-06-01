<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
exigirSuperAdmin();
$input = getInput();
$acao = $input['acao'] ?? $_GET['acao'] ?? '';
$db = getDB();

function gerarSlug($nome) {
    $slug = mb_strtolower($nome);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return trim($slug, '-');
}

switch ($acao) {

    case 'listar':
        $busca = $input['busca'] ?? $_GET['busca'] ?? '';
        $pagina = max(1, (int) ($input['pagina'] ?? $_GET['pagina'] ?? 1));
        $limite = 20;
        $offset = ($pagina - 1) * $limite;

        $sqlBase = " FROM clubs c";
        $params = [];

        if ($busca) {
            $sqlBase .= " WHERE c.nome ILIKE :busca";
            $params[':busca'] = '%' . $busca . '%';
        }

        // Total count
        $stmtCount = $db->prepare("SELECT COUNT(*)" . $sqlBase);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        // Paginated results
        $sql = "
            SELECT
                c.id, c.nome, c.segmento, c.slug, c.ativo, c.criado_em,
                (SELECT COUNT(*) FROM clientes cl WHERE cl.club_id = c.id) AS num_clientes,
                (SELECT COALESCE(SUM(co.valor), 0) FROM compras co WHERE co.club_id = c.id AND co.estornada = FALSE) AS vendas_total
        " . $sqlBase . " ORDER BY c.nome ASC LIMIT :limite OFFSET :offset";

        $stmt = $db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $clubes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse([
            'clubes' => $clubes,
            'total' => $total,
            'pagina' => $pagina,
            'total_paginas' => ceil($total / $limite),
        ]);
        break;

    case 'criar':
        $nome = trim($input['nome'] ?? '');
        $segmento = trim($input['segmento'] ?? '');
        $slug = trim($input['slug'] ?? '');

        if (!$nome || !$segmento) {
            jsonResponse(['erro' => 'Nome e segmento sao obrigatorios'], 400);
        }

        if (!$slug) {
            $slug = gerarSlug($nome);
        }

        // Validar unicidade do slug
        $stmtSlug = $db->prepare("SELECT COUNT(*) FROM clubs WHERE slug = :slug");
        $stmtSlug->execute([':slug' => $slug]);
        if ((int) $stmtSlug->fetchColumn() > 0) {
            jsonResponse(['erro' => 'Slug ja existe. Escolha outro nome ou informe um slug diferente.'], 409);
        }

        $stmt = $db->prepare("
            INSERT INTO clubs (nome, slug, segmento, endereco, cidade, estado, telefone, email, cor_primaria, cor_secundaria, expiracao_meses)
            VALUES (:nome, :slug, :segmento, :endereco, :cidade, :estado, :telefone, :email, :cor_primaria, :cor_secundaria, :expiracao_meses)
            RETURNING id
        ");
        $stmt->execute([
            ':nome' => $nome,
            ':slug' => $slug,
            ':segmento' => $segmento,
            ':endereco' => $input['endereco'] ?? null,
            ':cidade' => $input['cidade'] ?? null,
            ':estado' => $input['estado'] ?? null,
            ':telefone' => $input['telefone'] ?? null,
            ':email' => $input['email'] ?? null,
            ':cor_primaria' => $input['cor_primaria'] ?? null,
            ':cor_secundaria' => $input['cor_secundaria'] ?? null,
            ':expiracao_meses' => $input['expiracao_meses'] ?? null,
        ]);

        $id = $stmt->fetchColumn();

        registrarAudit($id, 'clube_criado', 'clubs', $id, null, ['nome' => $nome, 'slug' => $slug]);

        jsonResponse(['sucesso' => true, 'id' => $id, 'slug' => $slug]);
        break;

    case 'editar':
        $id = (int) ($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['erro' => 'ID do clube e obrigatorio'], 400);
        }

        $campos = [];
        $params = [':id' => $id];

        $editaveis = ['nome', 'slug', 'segmento', 'endereco', 'cidade', 'estado', 'telefone', 'email', 'cor_primaria', 'cor_secundaria', 'expiracao_meses'];

        foreach ($editaveis as $campo) {
            if (isset($input[$campo])) {
                $campos[] = "$campo = :$campo";
                $params[":$campo"] = $input[$campo];
            }
        }

        if (empty($campos)) {
            jsonResponse(['erro' => 'Nenhum campo para atualizar'], 400);
        }

        // Se slug estiver sendo alterado, validar unicidade
        if (isset($input['slug'])) {
            $stmtSlug = $db->prepare("SELECT COUNT(*) FROM clubs WHERE slug = :slug AND id != :check_id");
            $stmtSlug->execute([':slug' => $input['slug'], ':check_id' => $id]);
            if ((int) $stmtSlug->fetchColumn() > 0) {
                jsonResponse(['erro' => 'Slug ja existe para outro clube'], 409);
            }
        }

        $sql = "UPDATE clubs SET " . implode(', ', $campos) . " WHERE id = :id";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        registrarAudit($id, 'clube_editado', 'clubs', $id, null, ['campos' => array_keys(array_diff_key($params, [':id' => true]))]);

        jsonResponse(['sucesso' => true]);
        break;

    case 'desativar':
        $id = (int) ($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['erro' => 'ID do clube e obrigatorio'], 400);
        }

        $stmt = $db->prepare("UPDATE clubs SET ativo = FALSE WHERE id = :id");
        $stmt->execute([':id' => $id]);

        registrarAudit($id, 'clube_desativado', 'clubs', $id);

        jsonResponse(['sucesso' => true]);
        break;

    case 'ativar':
        $id = (int) ($input['id'] ?? 0);
        if (!$id) {
            jsonResponse(['erro' => 'ID do clube e obrigatorio'], 400);
        }

        $stmt = $db->prepare("UPDATE clubs SET ativo = TRUE WHERE id = :id");
        $stmt->execute([':id' => $id]);

        registrarAudit($id, 'clube_ativado', 'clubs', $id);

        jsonResponse(['sucesso' => true]);
        break;

    case 'detalhes':
        $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) {
            jsonResponse(['erro' => 'ID do clube e obrigatorio'], 400);
        }

        // Info do clube
        $stmt = $db->prepare("SELECT * FROM clubs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $clube = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$clube) {
            jsonResponse(['erro' => 'Clube nao encontrado'], 404);
        }

        // Stats
        $stmtClientes = $db->prepare("SELECT COUNT(*) FROM clientes WHERE club_id = :id");
        $stmtClientes->execute([':id' => $id]);
        $total_clientes = (int) $stmtClientes->fetchColumn();

        $stmtCompras = $db->prepare("SELECT COUNT(*) FROM compras WHERE club_id = :id AND estornada = FALSE");
        $stmtCompras->execute([':id' => $id]);
        $total_compras = (int) $stmtCompras->fetchColumn();

        $stmtVendas = $db->prepare("SELECT COALESCE(SUM(valor), 0) FROM compras WHERE club_id = :id AND estornada = FALSE");
        $stmtVendas->execute([':id' => $id]);
        $total_vendas = (float) $stmtVendas->fetchColumn();

        $stmtVendasMes = $db->prepare("
            SELECT COALESCE(SUM(valor), 0) FROM compras
            WHERE club_id = :id AND estornada = FALSE
              AND EXTRACT(MONTH FROM data_compra) = EXTRACT(MONTH FROM CURRENT_DATE)
              AND EXTRACT(YEAR FROM data_compra) = EXTRACT(YEAR FROM CURRENT_DATE)
        ");
        $stmtVendasMes->execute([':id' => $id]);
        $vendas_mes = (float) $stmtVendasMes->fetchColumn();

        $stmtCashback = $db->prepare("SELECT COALESCE(SUM(cashback_valor), 0) FROM compras WHERE club_id = :id AND estornada = FALSE");
        $stmtCashback->execute([':id' => $id]);
        $cashback_gerado = (float) $stmtCashback->fetchColumn();

        $stmtResgatado = $db->prepare("SELECT COALESCE(SUM(valor), 0) FROM resgates WHERE club_id = :id AND estornado = FALSE");
        $stmtResgatado->execute([':id' => $id]);
        $total_resgatado = (float) $stmtResgatado->fetchColumn();

        $stmtUsuarios = $db->prepare("SELECT COUNT(*) FROM users WHERE club_id = :id");
        $stmtUsuarios->execute([':id' => $id]);
        $num_usuarios = (int) $stmtUsuarios->fetchColumn();

        $clube['total_clientes'] = $total_clientes;
        $clube['total_compras'] = $total_compras;
        $clube['total_vendas'] = $total_vendas;
        $clube['vendas_mes'] = $vendas_mes;
        $clube['cashback_gerado'] = $cashback_gerado;
        $clube['total_resgatado'] = $total_resgatado;
        $clube['num_usuarios'] = $num_usuarios;

        jsonResponse(['clube' => $clube]);
        break;

    default:
        jsonResponse(['erro' => 'Acao invalida'], 400);
        break;
}
