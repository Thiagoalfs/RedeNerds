<?php
/**
 * rate_limiter.php
 * Middleware de controle de taxa de requisições (Rate Limiting) por endpoint e por IP.
 * Proteção leve e de altíssima performance (< 0.2ms) contra DDoS Camada 7, floods e força bruta.
 */

declare(strict_types=1);

require_once __DIR__ . '/loja/ip_helper.php';

/**
 * Retorna o diretório de armazenamento dos registros temporários de rate limit.
 */
function obterDiretorioRateLimit(): string {
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nerdcube_rate_limits';
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
    return $dir;
}

/**
 * Executa a limpeza probabilística (Garbage Collection) de registros expirados.
 * Ocorre em aproximadamente 1% das requisições.
 */
function executarGcRateLimit(): void {
    if (mt_rand(1, 100) !== 1) {
        return;
    }

    $dir = obterDiretorioRateLimit();
    $arquivos = @glob($dir . DIRECTORY_SEPARATOR . 'rl_*.json');
    if (!$arquivos) {
        return;
    }

    $agora = time();
    foreach ($arquivos as $arquivo) {
        $mtime = @filemtime($arquivo);
        if ($mtime !== false && ($agora - $mtime) > 3600) {
            @unlink($arquivo);
        }
    }
}

/**
 * Verifica o rate limit de um determinado IP e endpoint sem forçar o encerramento da execução.
 * 
 * @param string $endpointKey Identificador único do endpoint (ex: 'loja_criar_pix', 'admin_login')
 * @param int $maxRequests Limite máximo de requisições permitidas na janela
 * @param int $windowSeconds Tamanho da janela em segundos
 * @param string|null $customIp IP customizado (usado principalmente para testes automatizados)
 * @return array{allowed: bool, limit: int, remaining: int, retry_after: int, reset_at: int}
 */
function verificarRateLimit(
    string $endpointKey,
    int $maxRequests,
    int $windowSeconds,
    ?string $customIp = null
): array {
    executarGcRateLimit();

    $ip = $customIp ?? obterIpRealCliente();
    $hash = hash('sha256', "{$endpointKey}_{$ip}");
    $caminhoArquivo = obterDiretorioRateLimit() . DIRECTORY_SEPARATOR . "rl_{$hash}.json";

    $agora = time();
    $fp = @fopen($caminhoArquivo, 'c+');

    if (!$fp) {
        // Em caso de falha de I/O em disco, falha de forma permissiva (fail-open) para não bloquear usuários legítimos
        return [
            'allowed' => true,
            'limit' => $maxRequests,
            'remaining' => $maxRequests - 1,
            'retry_after' => 0,
            'reset_at' => $agora + $windowSeconds
        ];
    }

    // Trava de leitura/escrita atômica exclusiva
    @flock($fp, LOCK_EX);

    $conteudo = '';
    while (!feof($fp)) {
        $conteudo .= fread($fp, 8192);
    }

    $dados = json_decode($conteudo, true);
    if (!is_array($dados) || !isset($dados['start'], $dados['count'])) {
        $dados = [
            'start' => $agora,
            'count' => 0
        ];
    }

    // Se a janela de tempo anterior já expirou, reinicia a contagem
    if (($agora - (int)$dados['start']) >= $windowSeconds) {
        $dados['start'] = $agora;
        $dados['count'] = 0;
    }

    $permitido = ($dados['count'] < $maxRequests);
    $resetAt = (int)$dados['start'] + $windowSeconds;
    $retryAfter = max(1, $resetAt - $agora);

    if ($permitido) {
        $dados['count']++;
        $remaining = max(0, $maxRequests - $dados['count']);
    } else {
        $remaining = 0;
    }

    // Persiste os dados atômicos no arquivo
    ftruncate($fp, 0);
    rewind($fp);
    fwrite($fp, json_encode($dados, JSON_UNESCAPED_SLASHES));
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    return [
        'allowed' => $permitido,
        'limit' => $maxRequests,
        'remaining' => $remaining,
        'retry_after' => $permitido ? 0 : $retryAfter,
        'reset_at' => $resetAt
    ];
}

/**
 * Aplica e exige o Rate Limit para a requisição corrente.
 * Se o limite for ultrapassado, emite HTTP 429 com cabeçalhos RFC 6585 e encerra a execução.
 * 
 * @param string $endpointKey Identificador único do endpoint
 * @param int $maxRequests Limite máximo de requisições
 * @param int $windowSeconds Janela de tempo em segundos
 * @param string|null $customIp IP customizado (opcional)
 */
function exigirRateLimit(
    string $endpointKey,
    int $maxRequests,
    int $windowSeconds,
    ?string $customIp = null
): void {
    $resultado = verificarRateLimit($endpointKey, $maxRequests, $windowSeconds, $customIp);

    // Adiciona cabeçalhos informativos de Rate Limit
    if (!headers_sent()) {
        header("X-RateLimit-Limit: {$resultado['limit']}");
        header("X-RateLimit-Remaining: {$resultado['remaining']}");
        header("X-RateLimit-Reset: {$resultado['reset_at']}");
    }

    if ($resultado['allowed']) {
        return;
    }

    // Limite excedido - Configura HTTP 429
    if (!headers_sent()) {
        http_response_code(429);
        header("Retry-After: {$resultado['retry_after']}");
    }

    // Detecta se a requisição veio de navegador requisitando HTML direto (e não uma API/AJAX)
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $isHtmlRequest = (
        stripos($accept, 'text/html') !== false &&
        empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        stripos($_SERVER['REQUEST_URI'] ?? '', '/api/') === false
    );

    if ($isHtmlRequest) {
        $html429Path = dirname(__DIR__) . '/errors/429/index.html';
        if (file_exists($html429Path)) {
            if (!headers_sent()) {
                header('Content-Type: text/html; charset=UTF-8');
            }
            readfile($html429Path);
            exit;
        }
    }

    // Resposta padrão JSON para APIs e requisições assíncronas
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }

    echo json_encode([
        'status' => 'error',
        'error_code' => 'RATE_LIMIT_EXCEEDED',
        'message' => 'Muitas requisições. Por favor, aguarde alguns segundos antes de tentar novamente.',
        'retry_after' => $resultado['retry_after']
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

    exit;
}

/**
 * Remove o registro de rate limit de um determinado endpoint e IP (usado em testes ou reset administrativo).
 */
function limparRateLimit(string $endpointKey, ?string $customIp = null): void {
    $ip = $customIp ?? obterIpRealCliente();
    $hash = hash('sha256', "{$endpointKey}_{$ip}");
    $caminhoArquivo = obterDiretorioRateLimit() . DIRECTORY_SEPARATOR . "rl_{$hash}.json";
    if (file_exists($caminhoArquivo)) {
        @unlink($caminhoArquivo);
    }
}
