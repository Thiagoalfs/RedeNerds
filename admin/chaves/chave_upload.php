<?php
/**
 * chave_upload.php
 * Gerenciador de upload e processamento de imagem para pacotes de chaves e itens da loja.
 * - Suporta upload de arquivos (PNG, WEBP, JPG, GIF) com conversão automática para WebP (preservando transparência)
 * - Suporta URL externa (com preview ao vivo)
 * - Remove arquivos antigos locais quando substituídos para não acumular lixo no servidor
 */

require_once __DIR__ . '/../includes/image_upload_helper.php';

define('CHAVE_UPLOAD_DIR_PUBLICA', '/assets/chaves/');

function getChaveUploadDirFisica(): string
{
    return adminUploadDirFisica(CHAVE_UPLOAD_DIR_PUBLICA);
}

function obterMimeChave(string $caminho, ?string $fallbackMime = null): string
{
    return adminObterMimeImagem($caminho, $fallbackMime);
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
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        error_log("chave_upload: falha ao criar diretório '{$uploadDir}'");
        return [$imagemAtual, "Diretório de chaves sem permissão de criação/escrita no servidor."];
    }
    if (!is_writable($uploadDir)) {
        error_log("chave_upload: diretório sem permissão de escrita '{$uploadDir}'");
        return [$imagemAtual, "Diretório de chaves sem permissão de escrita no servidor."];
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

function converterParaWebpChave(string $caminhoOrigem, string $caminhoDestino, string $mime, int $qualidade = 90): bool
{
    return adminConverterParaWebp($caminhoOrigem, $caminhoDestino, $mime, $qualidade);
}

function apagarImagemChaveAntigaSeForUpload(?string $imagemAtual, string $imagemNova): void
{
    adminApagarUploadAntigo($imagemAtual, $imagemNova, CHAVE_UPLOAD_DIR_PUBLICA);
}
