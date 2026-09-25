<?php
/**
 * cache_helper.php
 * Utilitário central de controle de cache, ETags e invalidação para APIs da Rede Nerds.
 */

/**
 * Retorna a versão de cache ativa para a chave especificada.
 */
function obterVersaoCache(string $chave, string $fallbackFingerprint = ''): string
{
    global $pdo, $conn;

    $chave = trim($chave);
    if ($chave === '') {
        return md5(uniqid('cache_', true));
    }

    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->prepare("SELECT versao FROM site_cache_versions WHERE chave = :chave LIMIT 1");
            $stmt->execute([':chave' => $chave]);
            $versao = $stmt->fetchColumn();
            if ($versao) {
                return (string)$versao;
            }
        } elseif (isset($conn) && $conn instanceof mysqli) {
            $chaveEsc = $conn->real_escape_string($chave);
            $res = $conn->query("SELECT versao FROM site_cache_versions WHERE chave = '{$chaveEsc}' LIMIT 1");
            if ($res && ($row = $res->fetch_row())) {
                return (string)$row[0];
            }
        }
    } catch (Exception $e) {
        // Fallback silencioso em caso de erro no banco
    }

    return md5($chave . '_' . ($fallbackFingerprint ?: 'default_fingerprint'));
}

/**
 * Valida o cabeçalho If-None-Match da requisição. Se a versão não foi alterada,
 * encerra a requisição imediatamente com HTTP 304 Not Modified.
 */
function verificarEtagCache(string $chave, string $fallbackFingerprint = ''): string
{
    $versao = obterVersaoCache($chave, $fallbackFingerprint);
    $etag = '"' . trim($versao, '"') . '"';

    header('ETag: ' . $etag);
    header('Cache-Control: public, no-cache');

    $clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
    if (!empty($clientEtag)) {
        $normalize = function (string $t): string {
            return trim(str_replace(['W/', '"', "'"], '', $t));
        };

        if ($normalize($clientEtag) === $normalize($etag)) {
            http_response_code(304);
            exit;
        }
    }

    return $versao;
}

/**
 * Invalida o cache de uma chave específica gerando um novo hash de versão no banco de dados.
 */
function invalidarCache(string $chave): void
{
    global $pdo, $conn;

    $chave = trim($chave);
    if ($chave === '') return;

    $novaVersao = md5($chave . '_' . microtime(true) . '_' . bin2hex(random_bytes(8)));

    try {
        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->prepare(
                "INSERT INTO site_cache_versions (chave, versao, atualizado_em)
                 VALUES (:chave, :versao, NOW())
                 ON DUPLICATE KEY UPDATE versao = VALUES(versao), atualizado_em = NOW()"
            );
            $stmt->execute([':chave' => $chave, ':versao' => $novaVersao]);
        } elseif (isset($conn) && $conn instanceof mysqli) {
            $chaveEsc = $conn->real_escape_string($chave);
            $conn->query(
                "INSERT INTO site_cache_versions (chave, versao, atualizado_em)
                 VALUES ('{$chaveEsc}', '{$novaVersao}', NOW())
                 ON DUPLICATE KEY UPDATE versao = '{$novaVersao}', atualizado_em = NOW()"
            );
        }
    } catch (Exception $e) {
        error_log("Falha ao invalidar cache para '{$chave}': " . $e->getMessage());
    }
}
