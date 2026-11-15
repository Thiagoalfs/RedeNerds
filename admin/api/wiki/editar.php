<?php
require_once __DIR__ . "/../../sessao.php";
require_once __DIR__ . "/../../../wiki/wiki_helper.php";

exigirCSRF();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "ID de artigo inválido."], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmtCheck = $pdo->prepare("
    SELECT a.*, c.servidor_id 
    FROM wiki_artigos a 
    JOIN wiki_categorias c ON c.id = a.categoria_id 
    WHERE a.id = :id 
    LIMIT 1
");
$stmtCheck->execute([':id' => $id]);
$artigoAtual = $stmtCheck->fetch(PDO::FETCH_ASSOC);

if (!$artigoAtual) {
    http_response_code(404);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Artigo não encontrado."], JSON_UNESCAPED_UNICODE);
    exit;
}

$servidor_id  = (int)($_POST['servidor_id'] ?? $artigoAtual['servidor_id']);
$categoria_id = (int)($_POST['categoria_id'] ?? $artigoAtual['categoria_id']);
$titulo       = trim($_POST['titulo'] ?? $artigoAtual['titulo']);
$conteudo     = trim($_POST['conteudo'] ?? $artigoAtual['conteudo']);
$autor        = trim($_POST['autor'] ?? $artigoAtual['autor']);
$publicado    = isset($_POST['publicado']) ? (!empty($_POST['publicado']) ? 1 : 0) : (int)$artigoAtual['publicado'];

if ($servidor_id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Selecione o servidor de destino."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($titulo === '' || mb_strlen($titulo) < 2) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Informe um título válido para o artigo (mínimo 2 caracteres)."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($autor === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Selecione ou informe o autor do artigo."], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($conteudo === '') {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Informe o conteúdo do artigo."], JSON_UNESCAPED_UNICODE);
    exit;
}

garantirCategoriasPadraoWiki($pdo, $servidor_id);

if ($categoria_id <= 0) {
    $stmtFirstCat = $pdo->prepare("SELECT id FROM wiki_categorias WHERE servidor_id = :servidor_id ORDER BY ordem ASC, id ASC LIMIT 1");
    $stmtFirstCat->execute([':servidor_id' => $servidor_id]);
    $categoria_id = (int)$stmtFirstCat->fetchColumn();
}

if ($categoria_id <= 0) {
    http_response_code(400);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Nenhuma categoria encontrada para o servidor selecionado."], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $versaoHash = trim($_POST['_versao_hash'] ?? '');
    $ignorarWiki = ['visualizacoes', 'atualizado_em', 'criado_em', 'servidor_id', 'servername', 'servidor_slug', 'themecolor', 'categoria_nome'];
    $pdo->beginTransaction();
    exigirConcorrenciaEdicao($pdo, 'wiki_artigos', $id, $versaoHash, $ignorarWiki, true);

    $upd = $pdo->prepare("
        UPDATE wiki_artigos 
        SET categoria_id = :categoria_id, titulo = :titulo, conteudo = :conteudo, autor = :autor, publicado = :publicado, atualizado_em = NOW()
        WHERE id = :id
    ");
    $upd->execute([
        ':categoria_id' => $categoria_id,
        ':titulo'       => $titulo,
        ':conteudo'     => $conteudo,
        ':autor'        => $autor,
        ':publicado'    => $publicado,
        ':id'           => $id
    ]);
    $pdo->commit();

    $busca = $pdo->prepare("
        SELECT a.id, a.titulo, a.slug, a.publicado, a.visualizacoes, a.autor, a.criado_em, a.conteudo, a.categoria_id,
               s.id as servidor_id, s.servername, s.nome as servidor_slug, s.themecolor, c.nome as categoria_nome
        FROM wiki_artigos a
        JOIN wiki_categorias c ON c.id = a.categoria_id
        JOIN servidores s ON s.id = c.servidor_id
        WHERE a.id = :id
        LIMIT 1
    ");
    $busca->execute([':id' => $id]);
    $artigoAtualizado = $busca->fetch(PDO::FETCH_ASSOC);
    $buscaRaw = $pdo->prepare("SELECT * FROM wiki_artigos WHERE id = :id LIMIT 1");
    $buscaRaw->execute([':id' => $id]);
    $artigoRaw = $buscaRaw->fetch(PDO::FETCH_ASSOC);
    if ($artigoAtualizado && $artigoRaw) {
        $artigoAtualizado['_versao_hash'] = gerarHashRegistro($artigoRaw, [], 'wiki_artigos');
    }
    registrarLogAdmin($pdo, 'editar', 'wiki', "Editou artigo da wiki '{$titulo}' (#{$id})", $id);

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        "success"  => true,
        "mensagem" => "Artigo '{$titulo}' atualizado com sucesso!",
        "artigo"   => $artigoAtualizado
    ], JSON_UNESCAPED_UNICODE);
    exit;
} catch (PDOException $e) {
    error_log("Erro ao atualizar artigo wiki via API: " . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(["success" => false, "erro" => "Erro ao salvar alterações no banco de dados."], JSON_UNESCAPED_UNICODE);
    exit;
}
