<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../parceiros/parceiro_upload.php";

exigirCSRF();

$nome = trim($_POST['nome'] ?? '');
$youtube = trim($_POST['youtube'] ?? '');
$tiktok = trim($_POST['tiktok'] ?? '');
$instagram = trim($_POST['instagram'] ?? '');
$twitch = trim($_POST['twitch'] ?? '');
$kick = trim($_POST['kick'] ?? '');
$ativo = !empty($_POST['ativo']) ? 1 : 0;

if (empty($nome)) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "O nome do parceiro é obrigatório."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (mb_strlen($nome) > 100) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "O nome do parceiro pode ter no máximo 100 caracteres."], JSON_UNESCAPED_UNICODE);
    exit;
}

// Normaliza URLs se digitadas sem https://
$normalizarUrl = function(?string $url): ?string {
    $url = trim((string)$url);
    if ($url === '') return null;
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
    }
    return $url;
};

$youtube = $normalizarUrl($youtube);
$tiktok = $normalizarUrl($tiktok);
$instagram = $normalizarUrl($instagram);
$twitch = $normalizarUrl($twitch);
$kick = $normalizarUrl($kick);

// Processa upload ou URL de foto
list($fotoSalvar, $erroFoto) = processarFotoParceiro(null, $nome);

if ($erroFoto) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => $erroFoto], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Conexão com o banco de dados indisponível."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO parceiros (nome, foto, youtube, tiktok, instagram, twitch, kick, ativo) 
        VALUES (:nome, :foto, :youtube, :tiktok, :instagram, :twitch, :kick, :ativo)
    ");
    $stmt->execute([
        ':nome' => $nome,
        ':foto' => $fotoSalvar,
        ':youtube' => $youtube,
        ':tiktok' => $tiktok,
        ':instagram' => $instagram,
        ':twitch' => $twitch,
        ':kick' => $kick,
        ':ativo' => $ativo
    ]);

    $novoId = (int)$pdo->lastInsertId();

    require_once __DIR__ . "/../../../api/cache_helper.php";
    invalidarCache('parceiros');

    $busca = $pdo->prepare("SELECT * FROM parceiros WHERE id = :id LIMIT 1");
    $busca->execute([':id' => $novoId]);
    $parceiro = $busca->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => true,
        "mensagem" => "Parceiro '{$nome}' cadastrado com sucesso!",
        "parceiro" => $parceiro
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("Erro ao criar parceiro via API: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Erro ao salvar no banco de dados."], JSON_UNESCAPED_UNICODE);
    exit;
}
