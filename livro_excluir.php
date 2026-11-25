<?php
require_once 'includes/auth.php';
requireFuncionario();

require_once 'config/database.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($id > 0) {
    $database = new Database();
    $db = $database->getConnection();
    $stmt = $db->prepare("DELETE FROM livros WHERE id = :id");
    $stmt->execute([':id' => $id]);
}

header("Location: admin_dashboard.php?msg=excluido");
exit;
