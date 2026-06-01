<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
exigirSuperAdmin();
$input = getInput();
$acao = $input['acao'] ?? $_GET['acao'] ?? '';
$db = getDB();

switch ($acao) {

    case 'global_stats':
        // Total de clubs ativos
        $stmtClubes = $db->query("SELECT COUNT(*) FROM clubs WHERE ativo = TRUE");
        $total_clubes = (int) $stmtClubes->fetchColumn();

        // Total de clientes (todos os clubs)
        $stmtClientes = $db->query("SELECT COUNT(*) FROM clientes");
        $total_clientes = (int) $stmtClientes->fetchColumn();

        // Total de vendas (all time, non-refunded)
        $stmtVendas = $db->query("SELECT COALESCE(SUM(valor), 0) FROM compras WHERE estornada = FALSE");
        $total_vendas = (float) $stmtVendas->fetchColumn();

        // Vendas do mes atual
        $stmtVendasMes = $db->query("
            SELECT COALESCE(SUM(valor), 0) FROM compras
            WHERE estornada = FALSE
              AND EXTRACT(MONTH FROM data_compra) = EXTRACT(MONTH FROM CURRENT_DATE)
              AND EXTRACT(YEAR FROM data_compra) = EXTRACT(YEAR FROM CURRENT_DATE)
        ");
        $vendas_mes = (float) $stmtVendasMes->fetchColumn();

        // Total de cashback gerado
        $stmtCashback = $db->query("SELECT COALESCE(SUM(cashback_valor), 0) FROM compras WHERE estornada = FALSE");
        $total_cashback_gerado = (float) $stmtCashback->fetchColumn();

        // Total resgatado
        $stmtResgatado = $db->query("SELECT COALESCE(SUM(valor), 0) FROM resgates WHERE estornado = FALSE");
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
                    AND EXTRACT(MONTH FROM co.data_compra) = EXTRACT(MONTH FROM CURRENT_DATE)
                    AND EXTRACT(YEAR FROM co.data_compra) = EXTRACT(YEAR FROM CURRENT_DATE)
                ) AS vendas_mes,
                (SELECT COALESCE(SUM(co.valor), 0) FROM compras co WHERE co.club_id = c.id AND co.estornada = FALSE) AS vendas_total
            FROM clubs c
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
