<?php
/**
 * API de Clubes + Dashboard global (Super Admin)
 * Endpoint consumido pelo painel /index.html
 *
 * Acoes: global_stats, clubs_overview, listar, detalhe, criar, editar, toggle_status
 * Os nomes de campos/parametros seguem exatamente o contrato do frontend.
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/helpers.php';
exigirSuperAdmin();

$input = getInput();
$acao = $input['acao'] ?? $_GET['acao'] ?? '';
$db = getDB();

function gerarSlugClube($nome) {
    $slug = mb_strtolower($nome);
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    return trim($slug, '-');
}

switch ($acao) {

    // ====================================================
    // GLOBAL STATS - cards do dashboard
    // ====================================================
    case 'global_stats': {
        $total_clubes_ativos = (int) $db->query("SELECT COUNT(*) FROM clubs WHERE ativo = TRUE")->fetchColumn();
        $total_clientes = (int) $db->query("SELECT COUNT(*) FROM clientes WHERE ativo = TRUE")->fetchColumn();
        $total_vendas = (float) $db->query("SELECT COALESCE(SUM(valor),0) FROM compras WHERE estornada = FALSE")->fetchColumn();
        $vendas_mes = (float) $db->query("
            SELECT COALESCE(SUM(valor),0) FROM compras
            WHERE estornada = FALSE
              AND EXTRACT(MONTH FROM data_compra) = EXTRACT(MONTH FROM CURRENT_DATE)
              AND EXTRACT(YEAR FROM data_compra) = EXTRACT(YEAR FROM CURRENT_DATE)
        ")->fetchColumn();

        jsonResponse([
            'total_clubes_ativos' => $total_clubes_ativos,
            'total_clientes' => $total_clientes,
            'vendas_mes' => $vendas_mes,
            'total_vendas' => $total_vendas,
        ]);
        break;
    }

    // ====================================================
    // CLUBS OVERVIEW - tabela do dashboard / relatorios
    // ====================================================
    case 'clubs_overview': {
        $busca = $input['busca'] ?? $_GET['busca'] ?? '';
        $params = [];
        $where = '';
        if ($busca) {
            $where = " WHERE c.nome ILIKE :busca";
            $params[':busca'] = '%' . $busca . '%';
        }
        $sql = "
            SELECT
                c.id, c.nome, c.segmento, c.slug,
                CASE WHEN c.ativo THEN 'ativo' ELSE 'inativo' END AS status,
                (SELECT COUNT(*) FROM clientes cl WHERE cl.club_id = c.id AND cl.ativo = TRUE) AS total_clientes,
                (SELECT COALESCE(SUM(co.valor),0) FROM compras co WHERE co.club_id = c.id AND co.estornada = FALSE
                    AND EXTRACT(MONTH FROM co.data_compra) = EXTRACT(MONTH FROM CURRENT_DATE)
                    AND EXTRACT(YEAR FROM co.data_compra) = EXTRACT(YEAR FROM CURRENT_DATE)
                ) AS vendas_mes,
                (SELECT COALESCE(SUM(co.valor),0) FROM compras co WHERE co.club_id = c.id AND co.estornada = FALSE) AS total_vendas
            FROM clubs c" . $where . " ORDER BY c.nome ASC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $clubes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        // normalizar numericos
        foreach ($clubes as &$c) {
            $c['total_clientes'] = (int) $c['total_clientes'];
            $c['vendas_mes'] = (float) $c['vendas_mes'];
            $c['total_vendas'] = (float) $c['total_vendas'];
        }
        jsonResponse(['clubes' => $clubes]);
        break;
    }

    // ====================================================
    // LISTAR - tabela de clubes (paginada)
    // ====================================================
    case 'listar': {
        $busca = $input['busca'] ?? $_GET['busca'] ?? '';
        $page = max(1, (int) ($input['page'] ?? $_GET['page'] ?? 1));
        $perPage = (int) ($input['per_page'] ?? $_GET['per_page'] ?? 10);
        $perPage = max(1, min(1000, $perPage));
        $offset = ($page - 1) * $perPage;

        $params = [];
        $where = '';
        if ($busca) {
            $where = " WHERE c.nome ILIKE :busca OR c.slug ILIKE :busca";
            $params[':busca'] = '%' . $busca . '%';
        }

        $stmtCount = $db->prepare("SELECT COUNT(*) FROM clubs c" . $where);
        $stmtCount->execute($params);
        $total = (int) $stmtCount->fetchColumn();

        $sql = "
            SELECT c.id, c.nome, c.slug, c.segmento, c.telefone, c.email,
                   CASE WHEN c.ativo THEN 'ativo' ELSE 'inativo' END AS status
            FROM clubs c" . $where . " ORDER BY c.nome ASC LIMIT :limite OFFSET :offset";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->bindValue(':limite', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        jsonResponse([
            'clubes' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
        ]);
        break;
    }

    // ====================================================
    // DETALHE - dados de um clube (para edicao)
    // ====================================================
    case 'detalhe': {
        $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
        if (!$id) jsonResponse(['erro' => 'ID obrigatorio'], 400);
        $stmt = $db->prepare("SELECT *, CASE WHEN ativo THEN 'ativo' ELSE 'inativo' END AS status FROM clubs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $clube = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$clube) jsonResponse(['erro' => 'Clube nao encontrado'], 404);
        jsonResponse(['clube' => $clube]);
        break;
    }

    // ====================================================
    // CRIAR
    // ====================================================
    case 'criar': {
        $nome = trim($input['nome'] ?? '');
        $segmento = trim($input['segmento'] ?? '');
        $slug = trim($input['slug'] ?? '');
        if (!$nome || !$segmento) jsonResponse(['erro' => 'Nome e segmento sao obrigatorios'], 400);
        if (!$slug) $slug = gerarSlugClube($nome);

        $stmtSlug = $db->prepare("SELECT COUNT(*) FROM clubs WHERE slug = :slug");
        $stmtSlug->execute([':slug' => $slug]);
        if ((int) $stmtSlug->fetchColumn() > 0) {
            jsonResponse(['erro' => 'Slug ja existe. Escolha outro nome ou slug.'], 409);
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
            ':expiracao_meses' => $input['expiracao_meses'] ?? 3,
        ]);
        $id = (int) $stmt->fetchColumn();
        registrarAudit($id, 'clube_criado', 'clubs', $id, null, ['nome' => $nome, 'slug' => $slug]);
        jsonResponse(['sucesso' => true, 'id' => $id, 'slug' => $slug]);
        break;
    }

    // ====================================================
    // EDITAR
    // ====================================================
    case 'editar': {
        $id = (int) ($input['id'] ?? 0);
        if (!$id) jsonResponse(['erro' => 'ID do clube obrigatorio'], 400);

        $editaveis = ['nome', 'slug', 'segmento', 'endereco', 'cidade', 'estado', 'telefone', 'email', 'cor_primaria', 'cor_secundaria', 'expiracao_meses'];
        $campos = [];
        $params = [':id' => $id];
        foreach ($editaveis as $campo) {
            if (isset($input[$campo])) {
                $campos[] = "$campo = :$campo";
                $params[":$campo"] = $input[$campo];
            }
        }
        if (empty($campos)) jsonResponse(['erro' => 'Nenhum campo para atualizar'], 400);

        if (isset($input['slug'])) {
            $stmtSlug = $db->prepare("SELECT COUNT(*) FROM clubs WHERE slug = :slug AND id != :check_id");
            $stmtSlug->execute([':slug' => $input['slug'], ':check_id' => $id]);
            if ((int) $stmtSlug->fetchColumn() > 0) jsonResponse(['erro' => 'Slug ja existe para outro clube'], 409);
        }

        $campos[] = "atualizado_em = NOW()";
        $sql = "UPDATE clubs SET " . implode(', ', $campos) . " WHERE id = :id";
        $db->prepare($sql)->execute($params);
        registrarAudit($id, 'clube_editado', 'clubs', $id, null, ['campos' => array_keys(array_diff_key($params, [':id' => true]))]);
        jsonResponse(['sucesso' => true]);
        break;
    }

    // ====================================================
    // TOGGLE STATUS - ativa/desativa (status: 'ativo'|'inativo')
    // ====================================================
    case 'toggle_status': {
        $id = (int) ($input['id'] ?? 0);
        if (!$id) jsonResponse(['erro' => 'ID do clube obrigatorio'], 400);
        $novoStatus = ($input['status'] ?? '') === 'ativo';
        $db->prepare("UPDATE clubs SET ativo = :ativo, atualizado_em = NOW() WHERE id = :id")
           ->execute([':ativo' => $novoStatus ? 'TRUE' : 'FALSE', ':id' => $id]);
        registrarAudit($id, $novoStatus ? 'clube_ativado' : 'clube_desativado', 'clubs', $id);
        jsonResponse(['sucesso' => true]);
        break;
    }

    default:
        jsonResponse(['erro' => 'Acao invalida'], 400);
}
