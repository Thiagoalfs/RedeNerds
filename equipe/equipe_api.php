<?php
// Ajuste este caminho conforme a profundidade real deste arquivo em relação ao config.php
require_once "../../config.php";

header('Content-Type: application/json; charset=utf-8');

// Ordem fixa dos cargos na página. Cargos que existirem no banco mas não
// estiverem nesta lista aparecem no final, em ordem alfabética entre eles.
$ordemCargos = [
    'Fundadores',
    'Diretores',
    'Coordenadores',
    'Administradores',
    'Moderadores',
    'Desenvolvedores',
    'Designers',
];

try {
    // 1) Descobre os cargos (categorias) que existem no banco
    $stmtCargos = $pdo->query("SELECT DISTINCT cargo FROM equipe");
    $cargos = $stmtCargos->fetchAll(PDO::FETCH_COLUMN, 0);

    // 2) Ordena pela lista fixa; o que não estiver na lista vai pro final (alfabético)
    usort($cargos, function ($a, $b) use ($ordemCargos) {
        $posA = array_search($a, $ordemCargos, true);
        $posB = array_search($b, $ordemCargos, true);
        $posA = $posA === false ? PHP_INT_MAX : $posA;
        $posB = $posB === false ? PHP_INT_MAX : $posB;

        if ($posA === $posB) {
            return strcasecmp($a, $b);
        }
        return $posA <=> $posB;
    });

    // 3) Para cada cargo, busca os membros em ordem alfabética pelo nick
    $stmtMembros = $pdo->prepare("
        SELECT nick
        FROM equipe
        WHERE cargo = :cargo
        ORDER BY nick ASC
    ");

    $resultado = [];
    foreach ($cargos as $cargo) {
        $stmtMembros->execute([':cargo' => $cargo]);
        $membros = $stmtMembros->fetchAll(PDO::FETCH_COLUMN, 0);

        $resultado[] = [
            'categoryTitle' => $cargo,
            'members'       => $membros,
        ];
    }

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao carregar a equipe.']);
}