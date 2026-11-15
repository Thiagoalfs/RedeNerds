<?php
/**
 * Helper central de auditoria do painel administrativo.
 */

if (!function_exists('obterIpRealCliente')) {
    $ipHelper = __DIR__ . '/../../api/loja/ip_helper.php';
    if (file_exists($ipHelper)) {
        require_once $ipHelper;
    }
}

if (!function_exists('filtrarDetalhesLog')) {
    function filtrarDetalhesLog(array $dados): array {
        $limpo = [];
        foreach ($dados as $k => $v) {
            if (preg_match('/(senha|password|token|secret|hash)/i', (string)$k)) {
                continue;
            }
            $limpo[$k] = is_array($v) ? filtrarDetalhesLog($v) : $v;
        }
        return $limpo;
    }
}

if (!function_exists('registrarLogAdmin')) {
    function registrarLogAdmin(
        PDO $pdo,
        string $acao,
        string $modulo,
        string $descricao,
        ?int $alvoId = null,
        ?array $detalhes = null,
        ?array $ator = null
    ): void {
        try {
            $usuarioId = $ator['usuario_id'] ?? ($_SESSION['usuario_id'] ?? null);
            $usuarioId = ($usuarioId !== null && (int)$usuarioId > 0) ? (int)$usuarioId : null;

            $usuarioNome = trim((string)($ator['usuario_nome'] ?? ($_SESSION['usuario_nome'] ?? 'Sistema')));
            if ($usuarioNome === '') {
                $usuarioNome = 'Sistema';
            }
            $usuarioNome = mb_substr($usuarioNome, 0, 100, 'UTF-8');

            $cargo = $ator['cargo'] ?? ($_SESSION['cargo'] ?? null);
            $cargo = $cargo !== null ? mb_substr(trim((string)$cargo), 0, 100, 'UTF-8') : null;
            if ($cargo === '') {
                $cargo = null;
            }

            $ip = function_exists('obterIpRealCliente')
                ? obterIpRealCliente()
                : (trim((string)($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')) ?: '127.0.0.1');
            $ip = substr($ip, 0, 45);

            $detalhesJson = null;
            if (!empty($detalhes)) {
                $filtrado = filtrarDetalhesLog($detalhes);
                if (!empty($filtrado)) {
                    $detalhesJson = json_encode($filtrado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO admin_logs (usuario_id, usuario_nome, cargo, acao, modulo, alvo_id, descricao, detalhes, ip, criado_em)
                VALUES (:usuario_id, :usuario_nome, :cargo, :acao, :modulo, :alvo_id, :descricao, :detalhes, :ip, :criado_em)
            ");
            $stmt->execute([
                ':usuario_id' => $usuarioId,
                ':usuario_nome' => $usuarioNome,
                ':cargo' => $cargo,
                ':acao' => mb_substr($acao, 0, 40, 'UTF-8'),
                ':modulo' => mb_substr($modulo, 0, 50, 'UTF-8'),
                ':alvo_id' => $alvoId,
                ':descricao' => mb_substr($descricao, 0, 500, 'UTF-8'),
                ':detalhes' => $detalhesJson,
                ':ip' => $ip,
                ':criado_em' => (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            error_log('Falha ao registrar admin_logs: ' . $e->getMessage());
        }
    }
}
