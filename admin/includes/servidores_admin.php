<?php
/**
 * admin/includes/servidores_admin.php
 * Helper centralizado de servidores do painel (elimina a query+mapping
 * duplicados em dashboard, chaves/index, vips/index, noticias e pedidos).
 *
 * Uso:
 *   $dados = carregarServidoresAdmin($pdo);
 *   $servidores    = $dados['lista'];  // lista plena (filtros, selects)
 *   $servidoresMap = $dados['mapa'];   // mapa por id, servername e nome
 */

if (!function_exists('carregarServidoresAdmin')) {
    function carregarServidoresAdmin(PDO $pdo): array
    {
        try {
            $stmt = $pdo->query("SELECT id, servername, nome, themecolor, icon, enabled FROM servidores ORDER BY servername ASC");
            $lista = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $mapa = [];
            foreach ($lista as $s) {
                $mapa[(int)$s['id']] = $s;
                $mapa[$s['servername']] = $s;
                if (!empty($s['nome'])) {
                    $mapa[$s['nome']] = $s;
                }
            }

            return ['lista' => $lista, 'mapa' => $mapa];
        } catch (PDOException $e) {
            error_log("Erro ao carregar servidores: " . $e->getMessage());
            return ['lista' => [], 'mapa' => []];
        }
    }
}
