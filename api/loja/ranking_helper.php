<?php
/**
 * ranking_helper.php
 * Calcula o ranking de maiores doadores da loja excluindo pedidos não pagos e membros da equipe.
 */

declare(strict_types=1);

if (!function_exists('calcularRankingDoadores')) {
    function calcularRankingDoadores(PDO $pdo, int $limite = 5): array
    {
        $equipeSet = [];
        try {
            $nicksEquipe = $pdo->query("SELECT nick FROM equipe")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($nicksEquipe as $nickEq) {
                $norm = mb_strtolower(trim((string)$nickEq), 'UTF-8');
                if ($norm !== '') {
                    $equipeSet[$norm] = true;
                }
            }
        } catch (Throwable $e) {
            error_log('Erro ao consultar equipe para ranking: ' . $e->getMessage());
        }

        $sql = "
            SELECT LOWER(TRIM(nick)) AS nick_key,
                   MAX(TRIM(nick)) AS nick,
                   SUM(valor) AS total_doado
            FROM pedidos
            WHERE status = 'pago'
              AND nick IS NOT NULL
              AND TRIM(nick) <> ''
            GROUP BY LOWER(TRIM(nick))
            ORDER BY total_doado DESC, MAX(id) ASC
        ";

        $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        $ranking = [];

        foreach ($rows as $row) {
            $nickKey = mb_strtolower(trim((string)($row['nick_key'] ?? '')), 'UTF-8');
            if ($nickKey === '' || isset($equipeSet[$nickKey])) {
                continue;
            }
            $ranking[] = [
                'posicao' => count($ranking) + 1,
                'nick'    => trim((string)$row['nick']),
                'total'   => round((float)($row['total_doado'] ?? 0), 2),
            ];
            if (count($ranking) >= $limite) {
                break;
            }
        }

        return $ranking;
    }
}
