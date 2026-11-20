<?php

require_once __DIR__ . '/../includes/image_upload_helper.php';

define('CAPA_UPLOAD_DIR_PUBLICA', '/assets/novidades/');

function getCapaUploadDirFisica(): string
{
    return adminUploadDirFisica(CAPA_UPLOAD_DIR_PUBLICA);
}

/**
 * Ponto de entrada único do formulário.
 *
 * @param string|null $capaAtual Caminho/URL da capa já existente (edição).
 * @return array{0: string|null, 1: string|null} [$caminhoParaSalvar, $mensagemDeErro]
 */
function processarCapa(?string $capaAtual = null): array
{
    $temArquivo = isset($_FILES['capa_upload']) && $_FILES['capa_upload']['error'] !== UPLOAD_ERR_NO_FILE;
    $urlDigitada = trim($_POST['capa_url'] ?? '');

    if ($temArquivo) {
        return processarUploadCapa($capaAtual);
    }

    if (!empty($urlDigitada)) {
        return validarUrlCapa($urlDigitada, $capaAtual);
    }

    return [$capaAtual, null];
}

/**
 * Valida uma URL digitada pelo usuário e, se a capa atual era um upload
 * próprio, apaga o arquivo físico antigo (agora órfão).
 */
function validarUrlCapa(string $url, ?string $capaAtual): array
{
    if (strlen($url) > 500) {
        return [$capaAtual, "O link da imagem é muito longo (máx. 500 caracteres)."];
    }

    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $url)) {
        return [$capaAtual, "Link da imagem inválido. Use uma URL começando com http:// ou https://."];
    }

    apagarCapaAntigaSeForUpload($capaAtual, $url);

    return [$url, null];
}

/**
 * Processa o upload de $_FILES['capa_upload'] e converte para WebP.
 */
function processarUploadCapa(?string $capaAtual = null): array
{
    $arquivo = $_FILES['capa_upload'];

    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        if ($arquivo['error'] === UPLOAD_ERR_INI_SIZE || $arquivo['error'] === UPLOAD_ERR_FORM_SIZE) {
            return [$capaAtual, "A imagem excede o limite de upload permitido pelo servidor (máx. 5MB)."];
        }
        return [$capaAtual, "Erro ao enviar o arquivo (código {$arquivo['error']})."];
    }

    // Limite de tamanho: 5MB
    $tamanhoMaximo = 5 * 1024 * 1024;
    if ($arquivo['size'] > $tamanhoMaximo) {
        return [$capaAtual, "A imagem deve ter no máximo 5MB."];
    }

    // Valida o tipo real do arquivo
    $tiposPermitidos = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $arquivo['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $tiposPermitidos)) {
        return [$capaAtual, "Formato de imagem inválido. Use JPG, PNG, WEBP ou GIF."];
    }

    $uploadDir = getCapaUploadDirFisica();
    if (!is_dir($uploadDir) && !@mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
        error_log("capa_upload: falha ao criar diretório '{$uploadDir}'");
        return [$capaAtual, "Diretório de capas sem permissão de criação/escrita no servidor."];
    }
    if (!is_writable($uploadDir)) {
        error_log("capa_upload: diretório sem permissão de escrita '{$uploadDir}'");
        return [$capaAtual, "Diretório de capas sem permissão de escrita no servidor."];
    }

    // Gera o arquivo com extensão .webp
    do {
        $nomeArquivo = bin2hex(random_bytes(16)) . '.webp';
        $caminhoDestino = $uploadDir . $nomeArquivo;
    } while (file_exists($caminhoDestino));

    // Converte e salva a imagem para WebP
    if (!converterParaWebp($arquivo['tmp_name'], $caminhoDestino, $mime)) {
        return [$capaAtual, "Erro ao processar e converter a imagem para WebP."];
    }

    $caminhoPublico = CAPA_UPLOAD_DIR_PUBLICA . $nomeArquivo;

    apagarCapaAntigaSeForUpload($capaAtual, $caminhoPublico);

    return [$caminhoPublico, null];
}

function converterParaWebp(string $caminhoOrigem, string $caminhoDestino, string $mime, int $qualidade = 80): bool
{
    return adminConverterParaWebp($caminhoOrigem, $caminhoDestino, $mime, $qualidade);
}

function apagarCapaAntigaSeForUpload(?string $capaAtual, string $capaNova): void
{
    adminApagarUploadAntigo($capaAtual, $capaNova, CAPA_UPLOAD_DIR_PUBLICA);
}
