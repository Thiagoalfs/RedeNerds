<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../upload/upload_helper.php";

exigirCSRF();
header('Content-Type: application/json; charset=utf-8');

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Conexão com o banco indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

$pastaRaw = trim((string)($_POST['pasta'] ?? ''));
$novaPastaRaw = trim((string)($_POST['nova_pasta'] ?? ''));
$pastaAlvo = ($pastaRaw === '__nova__') ? $novaPastaRaw : $pastaRaw;
$pasta = uploadNormalizarPasta($pastaAlvo);

if ($pasta === null) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "Pasta inválida. Use apenas letras, números, hífen, underline ou subpastas dentro de assets/."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($_FILES['imagem']) || $_FILES['imagem']['error'] === UPLOAD_ERR_NO_FILE) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "Selecione uma imagem para enviar."], JSON_UNESCAPED_UNICODE);
    exit;
}

$arquivo = $_FILES['imagem'];
if ($arquivo['error'] !== UPLOAD_ERR_OK) {
    $msg = ($arquivo['error'] === UPLOAD_ERR_INI_SIZE || $arquivo['error'] === UPLOAD_ERR_FORM_SIZE)
        ? "A imagem excede o limite de upload permitido pelo servidor (máx. 10MB)."
        : "Erro ao enviar o arquivo (código {$arquivo['error']}).";
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($arquivo['size'] > 10 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "A imagem deve ter no máximo 10MB."], JSON_UNESCAPED_UNICODE);
    exit;
}

$mime = adminObterMimeImagem($arquivo['tmp_name'], $arquivo['type'] ?? null);
$tiposPermitidos = [
    'image/jpeg' => 'jpg',
    'image/pjpeg' => 'jpg',
    'image/png' => 'png',
    'image/x-png' => 'png',
    'image/webp' => 'webp',
    'image/gif' => 'gif',
];

if (!isset($tiposPermitidos[$mime])) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "Formato de imagem inválido. Use JPG, PNG, WEBP ou GIF."], JSON_UNESCAPED_UNICODE);
    exit;
}

$assetsRoot = realpath(uploadAssetsRootFisica());
if (!$assetsRoot || !is_dir($assetsRoot)) {
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Diretório assets/ não encontrado no servidor."], JSON_UNESCAPED_UNICODE);
    exit;
}

$destinoDir = $assetsRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $pasta);
if (!is_dir($destinoDir) && !@mkdir($destinoDir, 0775, true) && !is_dir($destinoDir)) {
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Não foi possível criar a pasta '{$pasta}' dentro de assets/."], JSON_UNESCAPED_UNICODE);
    exit;
}

$destinoReal = realpath($destinoDir);
if (!$destinoReal || (!str_starts_with($destinoReal, $assetsRoot . DIRECTORY_SEPARATOR) && $destinoReal !== $assetsRoot)) {
    http_response_code(400);
    echo json_encode(["success" => false, "erro" => "Caminho de pasta não permitido."], JSON_UNESCAPED_UNICODE);
    exit;
}
if (!is_writable($destinoReal)) {
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Pasta '{$pasta}' sem permissão de escrita."], JSON_UNESCAPED_UNICODE);
    exit;
}

$nomeCustom = trim((string)($_POST['nome_custom'] ?? ''));
$nomeOriginal = pathinfo((string)$arquivo['name'], PATHINFO_FILENAME);
$nomeBase = $nomeCustom !== '' ? $nomeCustom : ($nomeOriginal !== '' ? $nomeOriginal : 'imagem');
$slug = strtolower(trim((string)preg_replace('/[^a-zA-Z0-9_-]+/', '-', $nomeBase), '-_'));
if ($slug === '') {
    $slug = 'imagem';
}
$slug = substr($slug, 0, 60);
$sufixo = bin2hex(random_bytes(3));

$nomeFinal = "{$slug}-{$sufixo}.webp";
$caminhoDestino = $destinoReal . DIRECTORY_SEPARATOR . $nomeFinal;
$converteu = adminConverterParaWebp($arquivo['tmp_name'], $caminhoDestino, $mime, 88);

if (!$converteu) {
    $ext = $tiposPermitidos[$mime];
    $nomeFinal = "{$slug}-{$sufixo}.{$ext}";
    $caminhoDestino = $destinoReal . DIRECTORY_SEPARATOR . $nomeFinal;
    if (!@move_uploaded_file($arquivo['tmp_name'], $caminhoDestino)) {
        http_response_code(500);
        echo json_encode(["success" => false, "erro" => "Falha ao salvar a imagem no servidor."], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

$caminhoPublico = '/assets/' . $pasta . '/' . $nomeFinal;
$tamanhoFinal = (int)@filesize($caminhoDestino);
$autorNome = obterAutorLogadoEquipe($pdo) ?: 'Admin';
$usuarioId = isset($_SESSION['usuario_id']) && (int)$_SESSION['usuario_id'] > 0 ? (int)$_SESSION['usuario_id'] : null;
$nomeExibicao = $nomeCustom !== '' ? mb_substr($nomeCustom, 0, 160, 'UTF-8') : mb_substr((string)$arquivo['name'], 0, 160, 'UTF-8');

try {
    $stmt = $pdo->prepare("
        INSERT INTO site_uploads (nome, pasta, caminho_publico, tamanho_bytes, usuario_id, autor_nome)
        VALUES (:nome, :pasta, :caminho_publico, :tamanho_bytes, :usuario_id, :autor_nome)
    ");
    $stmt->execute([
        ':nome' => $nomeExibicao,
        ':pasta' => $pasta,
        ':caminho_publico' => $caminhoPublico,
        ':tamanho_bytes' => $tamanhoFinal,
        ':usuario_id' => $usuarioId,
        ':autor_nome' => $autorNome,
    ]);
    $novoId = (int)$pdo->lastInsertId();
    registrarLogAdmin($pdo, 'criar', 'upload', "Enviou imagem '{$nomeExibicao}' em {$caminhoPublico}", $novoId ?: null);

    $urlCompleta = uploadBaseSiteUrl() . $caminhoPublico;
    echo json_encode([
        "success" => true,
        "mensagem" => "Imagem enviada com sucesso!",
        "url_completa" => $urlCompleta,
        "caminho_publico" => $caminhoPublico,
        "item" => [
            "id" => $novoId,
            "nome" => $nomeExibicao,
            "pasta" => $pasta,
            "caminho_publico" => $caminhoPublico,
            "url_completa" => $urlCompleta,
            "tamanho_bytes" => $tamanhoFinal,
            "tamanho_formatado" => uploadFormatarTamanho($tamanhoFinal),
            "autor_nome" => $autorNome,
            "criado_em" => date('Y-m-d H:i:s'),
            "registrado" => true,
        ]
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (is_file($caminhoDestino)) {
        @unlink($caminhoDestino);
    }
    error_log("Erro ao registrar site_uploads: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(["success" => false, "erro" => "Erro ao registrar upload no banco de dados."], JSON_UNESCAPED_UNICODE);
}
