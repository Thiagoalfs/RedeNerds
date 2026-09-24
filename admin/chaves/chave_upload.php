<?php
/**
 * chave_upload.php
 * Gerenciador de upload e processamento de imagem para pacotes de chaves e itens da loja.
 * - Suporta upload de arquivos (PNG, WEBP, JPG, GIF) com conversão automática para WebP (preservando transparência)
 * - Suporta URL externa (com preview ao vivo)
 * - Remove arquivos antigos locais quando substituídos para não acumular lixo no servidor
 */

define('CHAVE_UPLOAD_DIR_PUBLICA', '/assets/chaves/');

/**
 * Retorna o caminho físico do diretório de assets/chaves.
 */
function getChaveUploadDirFisica(): string
{
    $candidatos = [
        realpath(__DIR__ . '/../../') . '/assets/chaves/',
        realpath(__DIR__ . '/../') . '/assets/chaves/',
        realpath(__DIR__ . '/../../../') . '/public_html/assets/chaves/',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/assets/chaves/'
    ];

    foreach ($candidatos as $dir) {
        if (!empty($dir) && is_dir($dir)) {
            return rtrim($dir, '/\\') . '/';
        }
    }

    $fallback = (realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2)) . '/assets/chaves/';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0755, true);
    }
    return rtrim($fallback, '/\\') . '/';
}

/**
 * Obtém o MIME Type de forma segura.
 */
function obterMimeChave(string $caminho, ?string $fallbackMime = null): string
{
    if (function_exists('finfo_open')) {
        $finfo = @finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo) {
            $mime = @finfo_file($finfo, $caminho);
            if (PHP_VERSION_ID < 80500) {
                @finfo_close($finfo);
            }
            if (!empty($mime)) {
                return strtolower($mime);
            }
        }
    }

    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($caminho);
        if (!empty($mime)) {
            return strtolower($mime);
        }
    }

    if (function_exists('getimagesize')) {
        $info = @getimagesize($caminho);
        if (!empty($info['mime'])) {
            return strtolower($info['mime']);
        }
    }

    return $fallbackMime ? strtolower($fallbackMime) : 'application/octet-stream';
}

/**
 * Ponto de entrada único do formulário de imagem de chave.
 *
 * @param string|null $imagemAtual Valor já existente (edição).
 * @return array{0: string|null, 1: string|null} [$valorParaSalvar, $mensagemDeErro]
 */
function processarImagemChave(?string $imagemAtual = null): array
{
    try {
        $temArquivo = isset($_FILES['imagem_upload']) && is_array($_FILES['imagem_upload']) && ($_FILES['imagem_upload']['error'] !== UPLOAD_ERR_NO_FILE);
        if ($temArquivo) {
            return processarUploadImagemChave($imagemAtual);
        }

        $url = trim($_POST['imagem_url'] ?? ($_POST['imagem'] ?? ''));
        if ($url !== '') {
            return validarUrlImagemChave($url, $imagemAtual);
        }

        // Se marcou para remover
        if (isset($_POST['remover_imagem']) && $_POST['remover_imagem'] === '1') {
            apagarImagemChaveAntigaSeForUpload($imagemAtual, '');
            return [null, null];
        }

        return [$imagemAtual, null];
    } catch (\Throwable $e) {
        error_log("Erro em processarImagemChave: " . $e->getMessage());
        return [$imagemAtual, "Erro ao processar imagem: " . $e->getMessage()];
    }
}

/**
 * Valida URL da imagem digitada.
 */
function validarUrlImagemChave(string $url, ?string $imagemAtual): array
{
    if (strlen($url) > 500) {
        return [$imagemAtual, "O link da imagem é muito longo (máx. 500 caracteres)."];
    }

    if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('#^/assets/#i', $url)) {
        return [$imagemAtual, "Link da imagem inválido. Use uma URL começando com http://, https:// ou /assets/."];
    }

    apagarImagemChaveAntigaSeForUpload($imagemAtual, $url);

    return [$url, null];
}

/**
 * Processa upload de arquivo e converte para WebP preservando transparência.
 */
function processarUploadImagemChave(?string $imagemAtual = null): array
{
    $arquivo = $_FILES['imagem_upload'];

    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $erros = [
            UPLOAD_ERR_INI_SIZE   => "A imagem excede o limite permitido pelo servidor (upload_max_filesize).",
            UPLOAD_ERR_FORM_SIZE  => "A imagem excede o limite do formulário.",
            UPLOAD_ERR_PARTIAL    => "O upload da imagem foi feito parcialmente.",
            UPLOAD_ERR_NO_TMP_DIR => "Pasta temporária ausente no servidor.",
            UPLOAD_ERR_CANT_WRITE => "Falha ao gravar imagem em disco.",
            UPLOAD_ERR_EXTENSION  => "Upload interrompido por extensão do PHP."
        ];
        return [$imagemAtual, $erros[$arquivo['error']] ?? "Erro ao enviar a imagem (código {$arquivo['error']})."];
    }

    $tamanhoMaximo = 5 * 1024 * 1024; // 5MB
    if ($arquivo['size'] > $tamanhoMaximo) {
        return [$imagemAtual, "A imagem deve ter no máximo 5MB."];
    }

    $tiposPermitidos = [
        'image/jpeg',
        'image/pjpeg',
        'image/png',
        'image/x-png',
        'image/webp',
        'image/gif',
        'image/avif'
    ];

    $mime = obterMimeChave($arquivo['tmp_name'], $arquivo['type'] ?? null);

    if (!in_array($mime, $tiposPermitidos, true)) {
        return [$imagemAtual, "Formato de imagem inválido ({$mime}). Use PNG, WEBP, JPG ou GIF."];
    }

    $uploadDir = getChaveUploadDirFisica();
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    // Conversão para WebP se GD estiver disponível
    if (function_exists('imagewebp')) {
        do {
            $nomeArquivo = md5(uniqid((string)mt_rand(), true)) . '.webp';
            $caminhoDestino = $uploadDir . $nomeArquivo;
        } while (file_exists($caminhoDestino));

        if (converterParaWebpChave($arquivo['tmp_name'], $caminhoDestino, $mime)) {
            $caminhoPublico = CHAVE_UPLOAD_DIR_PUBLICA . $nomeArquivo;
            apagarImagemChaveAntigaSeForUpload($imagemAtual, $caminhoPublico);
            return [$caminhoPublico, null];
        }
    }

    // Fallback caso GD WebP falhe
    $extOriginal = strtolower(pathinfo($arquivo['name'] ?? '', PATHINFO_EXTENSION) ?: 'webp');
    if (!in_array($extOriginal, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        $extOriginal = 'png';
    }

    do {
        $nomeArquivo = md5(uniqid((string)mt_rand(), true)) . '.' . $extOriginal;
        $caminhoDestino = $uploadDir . $nomeArquivo;
    } while (file_exists($caminhoDestino));

    if (@move_uploaded_file($arquivo['tmp_name'], $caminhoDestino) || @copy($arquivo['tmp_name'], $caminhoDestino)) {
        $caminhoPublico = CHAVE_UPLOAD_DIR_PUBLICA . $nomeArquivo;
        apagarImagemChaveAntigaSeForUpload($imagemAtual, $caminhoPublico);
        return [$caminhoPublico, null];
    }

    return [$imagemAtual, "Não foi possível gravar a imagem da chave no diretório do servidor."];
}

/**
 * Converte qualquer imagem compatível para o formato WebP preservando transparência se houver.
 */
function converterParaWebpChave(string $caminhoOrigem, string $caminhoDestino, string $mime, int $qualidade = 90): bool
{
    @ini_set('memory_limit', '256M');

    $imagem = null;

    try {
        switch ($mime) {
            case 'image/jpeg':
            case 'image/pjpeg':
                if (function_exists('imagecreatefromjpeg')) {
                    $imagem = @imagecreatefromjpeg($caminhoOrigem);
                }
                break;
            case 'image/png':
            case 'image/x-png':
                if (function_exists('imagecreatefrompng')) {
                    $imagem = @imagecreatefrompng($caminhoOrigem);
                }
                break;
            case 'image/webp':
                if (function_exists('imagecreatefromwebp')) {
                    $imagem = @imagecreatefromwebp($caminhoOrigem);
                }
                break;
            case 'image/gif':
                if (function_exists('imagecreatefromgif')) {
                    $imagem = @imagecreatefromgif($caminhoOrigem);
                }
                break;
            case 'image/avif':
                if (function_exists('imagecreatefromavif')) {
                    $imagem = @imagecreatefromavif($caminhoOrigem);
                }
                break;
        }

        if (!$imagem && function_exists('imagecreatefromstring')) {
            $conteudo = @file_get_contents($caminhoOrigem);
            if ($conteudo !== false) {
                $imagem = @imagecreatefromstring($conteudo);
            }
        }

        if (!$imagem) {
            return false;
        }

        if (function_exists('imageistruecolor') && !imageistruecolor($imagem)) {
            if (function_exists('imagepalettetotruecolor')) {
                @imagepalettetotruecolor($imagem);
            }
        }
        if (function_exists('imagealphablending')) {
            @imagealphablending($imagem, true);
        }
        if (function_exists('imagesavealpha')) {
            @imagesavealpha($imagem, true);
        }

        $sucesso = @imagewebp($imagem, $caminhoDestino, $qualidade);

        if (PHP_VERSION_ID < 80000 && is_resource($imagem)) {
            @imagedestroy($imagem);
        }

        return (bool)$sucesso;
    } catch (\Throwable $e) {
        error_log("Erro em converterParaWebpChave: " . $e->getMessage());
        return false;
    }
}

/**
 * Apaga o arquivo físico da imagem antiga quando era upload local e foi substituída.
 */
function apagarImagemChaveAntigaSeForUpload(?string $imagemAtual, string $imagemNova): void
{
    if (
        $imagemAtual
        && strpos($imagemAtual, CHAVE_UPLOAD_DIR_PUBLICA) === 0
        && $imagemAtual !== $imagemNova
    ) {
        $antigoCaminhoFisico = getChaveUploadDirFisica() . basename($imagemAtual);
        if (is_file($antigoCaminhoFisico)) {
            @unlink($antigoCaminhoFisico);
        }
    }
}
