<?php
/**
 * capa_upload.php
 * Helper compartilhado por criar.php e editar.php para processar a capa
 * das notícias, que pode vir por UPLOAD de arquivo ou por LINK (URL).
 *
 * Quando os dois campos são preenchidos, prevalece o que foi usado por
 * último — isso é controlado pelo campo oculto "capa_fonte" ('upload' ou
 * 'url'), que o JS do formulário atualiza a cada interação do usuário.
 *
 * Uso: chame sempre processarCapa($capaAtual) — ela decide internamente
 * qual dos dois caminhos seguir.
 */

// Pasta física onde os arquivos de upload são salvos no servidor
define('CAPA_UPLOAD_DIR_FISICA', __DIR__ . '/../../assets/novidades/');

// Prefixo salvo no banco / usado nas tags <img> para uploads próprios
define('CAPA_UPLOAD_DIR_PUBLICA', '../assets/novidades/');

/**
 * Ponto de entrada único do formulário.
 *
 * @param string|null $capaAtual Caminho/URL da capa já existente (edição).
 * @return array{0: string|null, 1: string|null} [$caminhoParaSalvar, $mensagemDeErro]
 */
function processarCapa(?string $capaAtual = null): array
{
    $fonte        = $_POST['capa_fonte'] ?? '';
    $urlDigitada  = trim($_POST['capa_url'] ?? '');
    $temArquivo   = isset($_FILES['capa']) && $_FILES['capa']['error'] !== UPLOAD_ERR_NO_FILE;

    // Caso normal (JS ativo): o campo oculto diz qual foi usado por último
    if ($fonte === 'url') {
        if ($urlDigitada !== '') {
            return validarUrlCapa($urlDigitada, $capaAtual);
        }
        return [$capaAtual, null];
    }

    if ($fonte === 'upload') {
        if ($temArquivo) {
            return processarUploadCapa($capaAtual);
        }
        return [$capaAtual, null];
    }

    // Fallback (sem JS / campo oculto ausente): upload tem prioridade, depois link
    if ($temArquivo) {
        return processarUploadCapa($capaAtual);
    }
    if ($urlDigitada !== '') {
        return validarUrlCapa($urlDigitada, $capaAtual);
    }

    // Nada enviado: mantém a capa atual (ou vazio, na criação)
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
 * Processa o upload de $_FILES['capa'].
 */
function processarUploadCapa(?string $capaAtual = null): array
{
    $arquivo = $_FILES['capa'];

    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        return [$capaAtual, "Erro ao enviar o arquivo (código {$arquivo['error']})."];
    }

    // Limite de tamanho: 5MB
    $tamanhoMaximo = 5 * 1024 * 1024;
    if ($arquivo['size'] > $tamanhoMaximo) {
        return [$capaAtual, "A imagem deve ter no máximo 5MB."];
    }

    // Valida o tipo real do arquivo (não confia na extensão nem no MIME do navegador)
    $tiposPermitidos = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $arquivo['tmp_name']);
    finfo_close($finfo);

    if (!isset($tiposPermitidos[$mime])) {
        return [$capaAtual, "Formato de imagem inválido. Use JPG, PNG, WEBP ou GIF."];
    }

    $extensao = $tiposPermitidos[$mime];

    if (!is_dir(CAPA_UPLOAD_DIR_FISICA)) {
        mkdir(CAPA_UPLOAD_DIR_FISICA, 0755, true);
    }

    // Gera um nome aleatório (hash) para o arquivo, evitando colisões e expor o nome original
    do {
        $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $extensao;
        $caminhoDestino = CAPA_UPLOAD_DIR_FISICA . $nomeArquivo;
    } while (file_exists($caminhoDestino));

    if (!move_uploaded_file($arquivo['tmp_name'], $caminhoDestino)) {
        return [$capaAtual, "Erro ao salvar a imagem no servidor."];
    }

    $caminhoPublico = CAPA_UPLOAD_DIR_PUBLICA . $nomeArquivo;

    apagarCapaAntigaSeForUpload($capaAtual, $caminhoPublico);

    return [$caminhoPublico, null];
}

/**
 * Apaga o arquivo físico da capa antiga quando ela era um upload próprio
 * (dentro de CAPA_UPLOAD_DIR_PUBLICA) e está sendo substituída por outra coisa
 * (novo upload ou link externo). Nunca apaga links externos.
 */
function apagarCapaAntigaSeForUpload(?string $capaAtual, string $capaNova): void
{
    if (
        $capaAtual
        && strpos($capaAtual, CAPA_UPLOAD_DIR_PUBLICA) === 0
        && $capaAtual !== $capaNova
    ) {
        $antigoCaminhoFisico = CAPA_UPLOAD_DIR_FISICA . basename($capaAtual);
        if (is_file($antigoCaminhoFisico)) {
            @unlink($antigoCaminhoFisico);
        }
    }
}
