<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['usuario_id']);
}

function isFuncionario(): bool {
    return isLoggedIn() && ($_SESSION['usuario_perfil'] ?? '') === 'funcionario';
}

function isCliente(): bool {
    return isLoggedIn() && ($_SESSION['usuario_perfil'] ?? '') === 'cliente';
}

function requireFuncionario(): void {
    if (!isFuncionario()) {
        header("Location: login.php?erro=acesso_negado");
        exit;
    }
}
