<?php
/**
 * parceiro_upload.php
 * Gerenciador de upload e processamento de fotos para criadores de conteúdo e parceiros.
 * - Suporta upload de arquivos (PNG, WEBP, JPG, GIF) com conversão automática para WebP (preservando transparência)
 * - Salva as fotos em /assets/parceiros/
 * - Suporta URL externa com preview em tempo real
 * - Remove arquivos antigos locais quando substituídos para não acumular lixo no servidor
 */

define('PARCEIRO_UPLOAD_DIR_PUBLICA', '/assets/parceiros/');

/**
 * Retorna o caminho físico do diretório de assets/parceiros.
 */
function getParceiroUploadDirFisica(): string
{
    $candidatos = [
        realpath(__DIR__ . '/../../') . '/assets/parceiros/',
        realpath(__DIR__ . '/../') . '/assets/parceiros/',
        realpath(__DIR__ . '/../../../') . '/public_html/assets/parceiros/',
        ($_SERVER['DOCUMENT_ROOT'] ?? '') . '/assets/parceiros/'
    ];

    foreach ($candidatos as $dir) {
        if (!empty($dir) && is_dir($dir)) {
            return rtrim($dir, '/\\') . '/';
        }
    }

    $fallback = (realpath(__DIR__ . '/../../') ?: dirname(__DIR__, 2)) . '/assets/parceiros/';
    if (!is_dir($fallback)) {
        @mkdir($fallback, 0755, true);
    }
    return rtrim($fallback, '/\\') . '/';
}

/**
 * Gera um slug limpo para nomear o arquivo de foto do parceiro.
 */
function slugificarNomeParceiro(string $nome): string
{
    $slug = mb_strtolower(trim($nome), 'UTF-8');
    // Remove acentos
    $slug = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slug) ?: $slug;
    // Substitui caracteres não alfanuméricos por underline
    $slug = preg_replace('/[^a-z0-9]+/i', '_', $slug);
    $slug = trim($slug, '_');
    return !empty($slug) ? $slug : 'parceiro';
}

/**
 * Obtém o MIME Type de forma segura.
 */
function obterMimeParceiro(string $caminho, ?string $fallbackMime = null): string
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
 * Ponto de entrada único do formulário de foto do parceiro.
 *
 * @param string|null $fotoAtual Valor já existente (edição).
 * @param string $nomeParceiro Nome do parceiro para gerar slug do arquivo.
 * @return array{0: string|null, 1: string|null} [$valorParaSalvar, $mensagemDeErro]
 */
function processarFotoParceiro(?string $fotoAtual = null, string $nomeParceiro = ''): array
{
    try {
        $temArquivo = isset($_FILES['foto_upload']) && is_array($_FILES['foto_upload']) && ($_FILES['foto_upload']['error'] !== UPLOAD_ERR_NO_FILE);
        if ($temArquivo) {
            return processarUploadFotoParceiro($fotoAtual, $nomeParceiro);
        }

        $url = trim($_POST['foto_url'] ?? ($_POST['foto'] ?? ''));
        if ($url !== '') {
            return validarUrlFotoParceiro($url, $fotoAtual);
        }

        // Se marcou para remover foto
        if (isset($_POST['remover_foto']) && $_POST['remover_foto'] === '1') {
            apagarFotoParceiroAntigaSeForUpload($fotoAtual, '');
            return [null, null];
        }

        return [$fotoAtual, null];
    } catch (\Throwable $e) {
        error_log("Erro em processarFotoParceiro: " . $e->getMessage());
        return [$fotoAtual, "Erro ao processar foto: " . $e->getMessage()];
    }
}

/**
 * Valida URL da foto digitada.
 */
function validarUrlFotoParceiro(string $url, ?string $fotoAtual): array
{
    if (strlen($url) > 500) {
        return [$fotoAtual, "O link da foto é muito longo (máx. 500 caracteres)."];
    }

    if (!filter_var($url, FILTER_VALIDATE_URL) && !preg_match('#^/assets/#i', $url)) {
        return [$fotoAtual, "Link da foto inválido. Use uma URL começando com http://, https:// ou /assets/."];
    }

    apagarFotoParceiroAntigaSeForUpload($fotoAtual, $url);

    return [$url, null];
}

/**
 * Processa upload de arquivo e converte para WebP em /assets/parceiros/.
 */
function processarUploadFotoParceiro(?string $fotoAtual = null, string $nomeParceiro = ''): array
{
    $arquivo = $_FILES['foto_upload'];

    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        $erros = [
            UPLOAD_ERR_INI_SIZE   => "A foto excede o limite permitido pelo servidor (upload_max_filesize).",
            UPLOAD_ERR_FORM_SIZE  => "A foto excede o limite do formulário.",
            UPLOAD_ERR_PARTIAL    => "O upload da foto foi feito parcialmente.",
            UPLOAD_ERR_NO_TMP_DIR => "Pasta temporária ausente no servidor.",
            UPLOAD_ERR_CANT_WRITE => "Falha ao gravar foto em disco.",
            UPLOAD_ERR_EXTENSION  => "Upload interrompido por extensão do PHP."
        ];
        return [$fotoAtual, $erros[$arquivo['error']] ?? "Erro ao enviar a foto (código {$arquivo['error']})."];
    }

    $tamanhoMaximo = 5 * 1024 * 1024; // 5MB
    if ($arquivo['size'] > $tamanhoMaximo) {
        return [$fotoAtual, "A foto deve ter no máximo 5MB."];
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

    $mime = obterMimeParceiro($arquivo['tmp_name'], $arquivo['type'] ?? null);

    if (!in_array($mime, $tiposPermitidos, true)) {
        return [$fotoAtual, "Formato de imagem inválido ({$mime}). Use PNG, WEBP, JPG ou GIF."];
    }

    $uploadDir = getParceiroUploadDirFisica();
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }

    $slug = slugificarNomeParceiro($nomeParceiro);
    $sufixoUnico = substr(md5((string)mt_rand()), 0, 6);
    $nomeArquivoBase = $slug . '_' . $sufixoUnico;

    // Conversão para WebP se GD estiver disponível
    if (function_exists('imagewebp')) {
        $nomeArquivo = $nomeArquivoBase . '.webp';
        $caminhoDestino = $uploadDir . $nomeArquivo;

        if (converterParaWebpParceiro($arquivo['tmp_name'], $caminhoDestino, $mime)) {
            $caminhoPublico = PARCEIRO_UPLOAD_DIR_PUBLICA . $nomeArquivo;
            apagarFotoParceiroAntigaSeForUpload($fotoAtual, $caminhoPublico);
            return [$caminhoPublico, null];
        }
    }

    // Fallback caso GD WebP falhe
    $extOriginal = strtolower(pathinfo($arquivo['name'] ?? '', PATHINFO_EXTENSION) ?: 'webp');
    if (!in_array($extOriginal, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
        $extOriginal = 'png';
    }

    $nomeArquivo = $nomeArquivoBase . '.' . $extOriginal;
    $caminhoDestino = $uploadDir . $nomeArquivo;

    if (@move_uploaded_file($arquivo['tmp_name'], $caminhoDestino) || @copy($arquivo['tmp_name'], $caminhoDestino)) {
        $caminhoPublico = PARCEIRO_UPLOAD_DIR_PUBLICA . $nomeArquivo;
        apagarFotoParceiroAntigaSeForUpload($fotoAtual, $caminhoPublico);
        return [$caminhoPublico, null];
    }

    return [$fotoAtual, "Não foi possível gravar a foto do parceiro no diretório do servidor."];
}

/**
 * Converte imagem para WebP preservando transparência se houver.
 */
function converterParaWebpParceiro(string $caminhoOrigem, string $caminhoDestino, string $mime, int $qualidade = 90): bool
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
        error_log("Erro em converterParaWebpParceiro: " . $e->getMessage());
        return false;
    }
}

/**
 * Apaga o arquivo físico da foto antiga quando era upload local em /assets/parceiros/ e foi substituída.
 */
function apagarFotoParceiroAntigaSeForUpload(?string $fotoAtual, string $fotoNova): void
{
    if (
        $fotoAtual
        && strpos($fotoAtual, PARCEIRO_UPLOAD_DIR_PUBLICA) === 0
        && $fotoAtual !== $fotoNova
    ) {
        $antigoCaminhoFisico = getParceiroUploadDirFisica() . basename($fotoAtual);
        if (is_file($antigoCaminhoFisico)) {
            @unlink($antigoCaminhoFisico);
        }
    }
}