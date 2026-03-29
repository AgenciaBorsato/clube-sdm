<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
exigirSuperAdmin();
$input = getInput();
$acao = $input['acao'] ?? $_GET['acao'] ?? '';
$db = getDB();

switch ($acao) {

    case 'global_stats':
        // Total de clubes ativos
        $stmtClubes = $db->query("SELECT COUNT(*) FROM clubes WHERE ativo = TRUE");
        $total_clubes = (int) $stmtClubes->fetchColumn();

        // Total de clientes (todos os clubes)
        $stmtClientes = $db->query("SELECT COUNT(*) FROM clientes");
        $total_clientes = (int) $stmtClientes->fetchColumn();

        // Total de vendas (all time, non-refunded)
        $stmtVendas = $db->query("SELECT COALESCE(SUM(valor), 0) FROM compras WHERE estornada = FALSE");
        $total_vendas = (float) $stmtVendas->fetchColumn();

        // Vendas do mes atual
        $stmtVendasMes = $db->query("
            SELECT COALESCE(SUM(valor), 0) FROM compras
            WHERE estornada = FALSE
              AND EXTRACT(MONTH FROM criado_em) = EXTRACT(MONTH FROM CURRENT_DATE)
              AND EXTRACT(YEAR FROM criado_em) = EXTRACT(YEAR FROM CURRENT_DATE)
        ");
        $vendas_mes = (float) $stmtVendasMes->fetchColumn();

        // Total de cashback gerado
        $stmtCashback = $db->query("SELECT COALESCE(SUM(valor_cashback), 0) FROM compras WHERE estornada = FALSE");
        $total_cashback_gerado = (float) $stmtCashback->fetchColumn();

        // Total resgatado
        $stmtResgatado = $db->query("SELECT COALESCE(SUM(valor), 0) FROM resgates WHERE status = 'APROVADO'");
        $total_resgatado = (float) $stmtResgatado->fetchColumn();

        jsonResponse([
            'total_clubes' => $total_clubes,
            'total_clientes' => $total_clientes,
            'total_vendas' => $total_vendas,
            'vendas_mes' => $vendas_mes,
            'total_cashback_gerado' => $total_cashback_gerado,
            'total_resgatado' => $total_resgatado,
        ]);
        break;

    case 'clubs_overview':
        $busca = $input['busca'] ?? $_GET['busca'] ?? '';

        $sql = "
            SELECT
                c.id,
                c.nome,
                c.segmento,
                c.slug,
                c.ativo,
                (SELECT COUNT(*) FROM clientes cl WHERE cl.club_id = c.id) AS num_clientes,
                (SELECT COALESCE(SUM(co.valor), 0) FROM compras co WHERE co.club_id = c.id AND co.estornada = FALSE
                    AND EXTRACT(MONTH FROM co.criado_em) = EXTRACT(MONTH FROM CURRENT_DATE)
                    AND EXTRACT(YEAR FROM co.criado_em) = EXTRACT(YEAR FROM CURRENT_DATE)
                ) AS vendas_mes,
                (SELECT COALESCE(SUM(co.valor), 0) FROM compras co WHERE co.club_id = c.id AND co.estornada = FALSE) AS vendas_total
            FROM clubes c
        ";

        $params = [];

        if ($busca) {
            $sql .= " WHERE c.nome ILIKE :busca";
            $params[':busca'] = '%' . $busca . '%';
        }

        $sql .= " ORDER BY c.nome ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $clubes = $stmt->fetchAll(PDO::FETCH_ASSOC);

        jsonResponse(['clubes' => $clubes]);
        break;

    default:
        jsonResponse(['erro' => 'Acao invalida'], 400);
        break;
}
