<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/database.php';

$termo     = trim($_GET['q'] ?? '');
$categoria = trim($_GET['categoria'] ?? '');

try {
    $db = (new Database())->getConnection();

    $sql = 'SELECT id, titulo, autor, categoria, isbn, preco, estoque, descricao, capa_url FROM livros WHERE 1=1';
    $params = [];

    if ($termo !== '') {
        $sql .= ' AND (titulo LIKE ? OR autor LIKE ? OR isbn LIKE ?)';
        $like = '%' . $termo . '%';
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    if ($categoria !== '') {
        $sql .= ' AND categoria = ?';
        $params[] = $categoria;
    }

    $sql .= ' ORDER BY id DESC';

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $livros = $stmt->fetchAll();

    echo json_encode($livros, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['erro' => 'Erro ao pesquisar livros.'], JSON_UNESCAPED_UNICODE);
}
