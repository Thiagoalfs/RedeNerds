<?php

/**
 * Mapeia os campos usados no hash de concorrência quando a listagem não faz SELECT *.
 */
function camposConcorrenciaTabela(string $tabela): ?array {
    static $mapa = [
        'servidores' => ['id', 'servername', 'nome', 'title', 'icon', 'bg_image', 'descricao', 'features', 'modpackurl', 'ip', 'themecolor', 'enabled'],
        'equipe' => ['id', 'nick', 'cargo'],
        'equipe_cargos' => ['id', 'nome', 'cor', 'ordem'],
        'wiki_artigos' => ['id', 'categoria_id', 'titulo', 'slug', 'conteudo', 'autor', 'publicado'],
        'wiki_categorias' => ['id', 'servidor_id', 'nome', 'icone', 'ordem'],
        'usuarios' => ['id', 'usuario', 'email', 'email_recuperacao', 'senha', 'equipe_id'],
        'novidades' => ['id', 'titulo', 'conteudo', 'category', 'categoria_envio', 'capa', 'autor'],
    ];
    return $mapa[$tabela] ?? null;
}

/**
 * Gera hash determinístico do estado atual de um registro para optimistic locking.
 */
function gerarHashRegistro(array $row, array $ignorar = [], ?string $tabela = null): string {
    $somenteCampos = $tabela !== null ? camposConcorrenciaTabela($tabela) : null;
    $filtrado = [];
    foreach ($row as $k => $v) {
        if (is_int($k) || is_array($v) || is_object($v) || in_array($k, $ignorar, true) || $k === '_versao_hash') {
            continue;
        }
        if ($somenteCampos !== null && !in_array((string)$k, $somenteCampos, true)) {
            continue;
        }
        $filtrado[(string)$k] = $v === null ? '' : (string)$v;
    }
    ksort($filtrado);
    return substr(hash('sha256', json_encode($filtrado, JSON_UNESCAPED_UNICODE)), 0, 24);
}

/**
 * Verifica existência e concorrência (optimistic locking) de um registro antes de editar.
 * Retorna ['ok' => true, 'row' => array] ou ['ok' => false, 'status' => 404|409, 'erro' => string].
 */
function verificarConcorrenciaEdicao(
    PDO $pdo,
    string $tabela,
    int $id,
    string $hashEnviado = '',
    array $ignorar = []
): array {
    $sql = "SELECT * FROM `{$tabela}` WHERE id = :id LIMIT 1";
    if ($pdo->inTransaction()) {
        $sql .= " FOR UPDATE";
    }
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $atual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$atual) {
        return ['ok' => false, 'status' => 404, 'erro' => 'Registro não encontrado ou já foi excluído por outro administrador.'];
    }
    if ($hashEnviado !== '' && !hash_equals(gerarHashRegistro($atual, $ignorar, $tabela), $hashEnviado)) {
        return ['ok' => false, 'status' => 409, 'erro' => 'Este registro foi alterado por outro administrador enquanto você editava. Recarregue a página para ver os dados atuais.'];
    }

    return ['ok' => true, 'row' => $atual];
}

/**
 * Valida concorrência na edição e encerra com resposta 404/409 em caso de conflito/ausência.
 */
function exigirConcorrenciaEdicao(
    PDO $pdo,
    string $tabela,
    int $id,
    string $hashEnviado = '',
    array $ignorar = [],
    bool $isAjax = true,
    string $redirectUrl = '/admin/'
): array {
    $res = verificarConcorrenciaEdicao($pdo, $tabela, $id, $hashEnviado, $ignorar);
    if ($res['ok']) {
        return $res['row'];
    }
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    responderErroConcorrencia((int)$res['status'], (string)$res['erro'], $isAjax, $redirectUrl);
}

function responderErroConcorrencia(int $status, string $erro, bool $isAjax = true, string $redirectUrl = '/admin/'): void {
    if ($isAjax) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'erro' => $erro, 'conflito' => $status === 409], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $sep = strpos($redirectUrl, '?') !== false ? '&' : '?';
    header("Location: {$redirectUrl}{$sep}erro=" . urlencode($erro));
    exit;
}

/**
 * Executa toggle atômico (UPDATE ... WHERE id = :id AND col = :atual) evitando race conditions
 * e reversões acidentais quando a tela do admin está desatualizada.
 */
function executarToggleAtomico(
    PDO $pdo,
    string $tabela,
    string $coluna,
    int $id,
    string $campoSelectExtra = 'nome',
    ?int $estadoEsperado = null
): array {
    $stmt = $pdo->prepare("SELECT `{$coluna}`, `{$campoSelectExtra}` FROM `{$tabela}` WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        return ['ok' => false, 'status' => 404, 'erro' => 'Registro não encontrado ou já foi excluído por outro administrador.'];
    }

    $atual = (int)$row[$coluna] ? 1 : 0;
    if ($estadoEsperado !== null && $atual !== $estadoEsperado) {
        return ['ok' => false, 'status' => 409, 'erro' => 'Este registro já foi alterado por outro administrador. Recarregue a página para ver o estado atual.'];
    }

    $novo = $atual ? 0 : 1;
    $upd = $pdo->prepare("UPDATE `{$tabela}` SET `{$coluna}` = :novo WHERE id = :id AND `{$coluna}` = :atual");
    $upd->execute([':novo' => $novo, ':id' => $id, ':atual' => $row[$coluna]]);
    if ($upd->rowCount() === 0) {
        return ['ok' => false, 'status' => 409, 'erro' => 'Este registro foi alterado simultaneamente por outro administrador. Recarregue a página.'];
    }

    return ['ok' => true, 'novo' => $novo, 'row' => $row];
}

function obterHashAtualRegistro(PDO $pdo, string $tabela, int $id, ?string $tabelaMapa = null): string {
    $stmt = $pdo->prepare("SELECT * FROM `{$tabela}` WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ? gerarHashRegistro($row, [], $tabelaMapa) : '';
}
