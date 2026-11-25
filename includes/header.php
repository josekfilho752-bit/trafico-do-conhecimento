<?php
require_once __DIR__ . '/auth.php';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tráfico do Conhecimento | Livraria & Acervo</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <header class="navbar">
        <div class="container nav-content">
            <a href="index.php" class="brand">
                <span class="brand-icon">📘</span>
                <div class="brand-text">
                    <strong>Tráfico do Conhecimento</strong>
                    <small>Livraria & Gestão de Acervo</small>
                </div>
            </a>

            <nav class="nav-links">
                <a href="index.php" class="nav-link">Vitrine da Loja</a>
                <?php if (isFuncionario()): ?>
                    <a href="admin_dashboard.php" class="nav-link badge-admin">Painel Administrativo</a>
                    <a href="livro_form.php" class="nav-link">+ Novo Livro</a>
                <?php endif; ?>

                <?php if (isLoggedIn()): ?>
                    <span class="user-greeting">
                        Olá, <strong><?= htmlspecialchars($_SESSION['usuario_nome']) ?></strong>
                        <span class="role-tag"><?= $_SESSION['usuario_perfil'] === 'funcionario' ? 'Funcionário' : 'Cliente' ?></span>
                    </span>
                    <a href="logout.php" class="btn btn-outline-light btn-sm">Sair</a>
                <?php else: ?>
                    <a href="login.php" class="btn btn-light btn-sm">Entrar / Cadastrar</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
    <main class="container main-content">
