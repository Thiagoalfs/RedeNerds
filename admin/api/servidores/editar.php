<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../servidores/icon_upload.php";
require_once __DIR__ . "/../../servidores/bg_upload.php";

exigirCSRF();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "ID de servidor inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM servidores WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$srvAtual = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$srvAtual) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Servidor não encontrado."], JSON_UNESCAPED_UNICODE);
    exit;
}

$servername  = trim($_POST['servername'] ?? $srvAtual['servername']);
$descricao   = trim($_POST['descricao'] ?? $srvAtual['descricao']);
$modpackurl  = trim($_POST['modpackurl'] ?? $srvAtual['modpackurl']);
$ip          = trim($_POST['ip'] ?? $srvAtual['ip']);
$themecolor  = trim($_POST['themecolor'] ?? ($srvAtual['themecolor'] ?? '#B971DA'));
$enabled     = !empty($_POST['enabled']) ? 1 : 0;

if ($servername === '' || mb_strlen($servername) < 2) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Informe um nome válido para o servidor."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($ip === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Informe o IP de conexão do servidor."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($descricao === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Informe uma breve descrição para o servidor."], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $themecolor)) {
    $themecolor = $srvAtual['themecolor'] ?? '#B971DA';
}

$featuresPost = $_POST['features'] ?? null;
if ($featuresPost !== null) {
    if (is_string($featuresPost)) {
        $featuresPost = explode("\n", $featuresPost);
    }
    $features = array_values(array_filter(array_map('trim', (array)$featuresPost), fn($f) => $f !== ''));
    if (empty($features)) {
        $features = ["Economia balanceada e jogabilidade aprimorada"];
    }
    $featuresJson = json_encode($features, JSON_UNESCAPED_UNICODE);
} else {
    $featuresJson = $srvAtual['features'];
}

[$icon, $erro_icone] = processarIcone($srvAtual['icon'] ?? null);
if ($erro_icone) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => $erro_icone], JSON_UNESCAPED_UNICODE);
    exit;
}
if (empty($icon)) {
    $icon = '/assets/images/logo.webp';
}

[$bg_image, $erro_bg] = processarBgServidor($srvAtual['bg_image'] ?? null);
if ($erro_bg) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => $erro_bg], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $stmtUp = $pdo->prepare("
        UPDATE servidores 
        SET servername = :servername, icon = :icon, bg_image = :bg_image, descricao = :descricao,
            features = :features, modpackurl = :modpackurl, ip = :ip, themecolor = :themecolor, enabled = :enabled
        WHERE id = :id
    ");
    $stmtUp->execute([
        ':servername' => $servername,
        ':icon'       => $icon,
        ':bg_image'   => $bg_image,
        ':descricao'  => $descricao,
        ':features'   => $featuresJson,
        ':modpackurl' => $modpackurl,
        ':ip'         => $ip,
        ':themecolor' => $themecolor,
        ':enabled'    => $enabled,
        ':id'         => $id,
    ]);

    require_once __DIR__ . "/../../../api/cache_helper.php";
    invalidarCache('servidores');

    $busca = $pdo->prepare("SELECT * FROM servidores WHERE id = :id LIMIT 1");
    $busca->execute([':id' => $id]);
    $servidorAtualizado = $busca->fetch(PDO::FETCH_ASSOC);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success" => true,
        "mensagem" => "Servidor '{$servername}' atualizado com sucesso!",
        "servidor" => $servidorAtualizado
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("Erro ao atualizar servidor via API: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Erro ao salvar alterações no banco de dados."], JSON_UNESCAPED_UNICODE);
    exit;
}
