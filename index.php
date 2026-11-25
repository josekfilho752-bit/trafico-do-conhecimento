<?php
declare(strict_types=1);

session_start();
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/config/database.php';

$usuarioNome   = $_SESSION['usuario_nome'] ?? null;
$usuarioPerfil = $_SESSION['usuario_perfil'] ?? null;
$usuarioId     = isset($_SESSION['usuario_id']) ? (int) $_SESSION['usuario_id'] : null;

if (!isset($_SESSION['carrinho']) || !is_array($_SESSION['carrinho'])) {
    $_SESSION['carrinho'] = [];
}

$mensagem = '';
$erro = '';

try {
    $db = (new Database())->getConnection();

    // Garante tabelas de pedidos caso o banco tenha sido criado antes desta versão
    $db->exec("
        CREATE TABLE IF NOT EXISTS pedidos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id INT NOT NULL,
            total DECIMAL(10, 2) NOT NULL,
            status ENUM('Pendente', 'Confirmado', 'Cancelado') NOT NULL DEFAULT 'Pendente',
            criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");
    $db->exec("
        CREATE TABLE IF NOT EXISTS pedido_itens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            pedido_id INT NOT NULL,
            livro_id INT NOT NULL,
            quantidade INT NOT NULL,
            preco_unitario DECIMAL(10, 2) NOT NULL,
            FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
            FOREIGN KEY (livro_id) REFERENCES livros(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ");

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $acaoCarrinho = $_POST['acao_carrinho'] ?? '';

        if ($acaoCarrinho === 'adicionar') {
            $livroId = (int) ($_POST['livro_id'] ?? 0);
            $stmt = $db->prepare('SELECT id, titulo, preco, estoque FROM livros WHERE id = ? LIMIT 1');
            $stmt->execute([$livroId]);
            $livro = $stmt->fetch();

            if (!$livro || (int) $livro['estoque'] <= 0) {
                $erro = 'Livro indisponível ou sem estoque.';
            } else {
                $qtdAtual = (int) ($_SESSION['carrinho'][$livroId] ?? 0);
                if ($qtdAtual + 1 > (int) $livro['estoque']) {
                    $erro = 'Quantidade solicitada excede o estoque disponível (' . (int) $livro['estoque'] . ').';
                } else {
                    $_SESSION['carrinho'][$livroId] = $qtdAtual + 1;
                    $mensagem = 'Livro "' . $livro['titulo'] . '" adicionado ao carrinho.';
                }
            }
        } elseif ($acaoCarrinho === 'remover') {
            $livroId = (int) ($_POST['livro_id'] ?? 0);
            unset($_SESSION['carrinho'][$livroId]);
            $mensagem = 'Item removido do carrinho.';
        } elseif ($acaoCarrinho === 'limpar') {
            $_SESSION['carrinho'] = [];
            $mensagem = 'Carrinho esvaziado.';
        } elseif ($acaoCarrinho === 'finalizar') {
            if ($usuarioId === null) {
                $erro = 'Faça login como cliente para finalizar a compra.';
            } elseif (empty($_SESSION['carrinho'])) {
                $erro = 'Seu carrinho está vazio.';
            } else {
                $db->beginTransaction();
                try {
                    $totalPedido = 0.0;
                    $itensParaInserir = [];

                    foreach ($_SESSION['carrinho'] as $livroId => $qtd) {
                        $livroId = (int) $livroId;
                        $qtd = (int) $qtd;
                        if ($qtd <= 0) {
                            continue;
                        }

                        $stmt = $db->prepare('SELECT id, titulo, preco, estoque FROM livros WHERE id = ? FOR UPDATE');
                        $stmt->execute([$livroId]);
                        $livro = $stmt->fetch();

                        if (!$livro || (int) $livro['estoque'] < $qtd) {
                            throw new RuntimeException('Estoque insuficiente para "' . ($livro['titulo'] ?? 'livro') . '".');
                        }

                        $precoUnit = (float) $livro['preco'];
                        $totalPedido += $precoUnit * $qtd;
                        $itensParaInserir[] = [
                            'livro_id' => $livroId,
                            'quantidade' => $qtd,
                            'preco_unitario' => $precoUnit,
                        ];
                    }

                    if (empty($itensParaInserir)) {
                        throw new RuntimeException('Nenhum item válido no carrinho.');
                    }

                    $stmtPedido = $db->prepare('INSERT INTO pedidos (usuario_id, total, status) VALUES (?, ?, ?)');
                    $stmtPedido->execute([$usuarioId, $totalPedido, 'Pendente']);
                    $pedidoId = (int) $db->lastInsertId();

                    $stmtItem = $db->prepare('INSERT INTO pedido_itens (pedido_id, livro_id, quantidade, preco_unitario) VALUES (?, ?, ?, ?)');
                    $stmtBaixa = $db->prepare('UPDATE livros SET estoque = estoque - ? WHERE id = ?');

                    foreach ($itensParaInserir as $item) {
                        $stmtItem->execute([$pedidoId, $item['livro_id'], $item['quantidade'], $item['preco_unitario']]);
                        $stmtBaixa->execute([$item['quantidade'], $item['livro_id']]);
                    }

                    $db->commit();
                    $_SESSION['carrinho'] = [];
                    $mensagem = 'Compra finalizada com sucesso! Pedido #' . $pedidoId . ' registrado como Pendente para confirmação do funcionário.';
                } catch (Throwable $ex) {
                    $db->rollBack();
                    $erro = $ex->getMessage();
                }
            }
        }
    }

    $livros = $db->query('SELECT id, titulo, autor, categoria, isbn, preco, estoque, descricao, capa_url FROM livros ORDER BY id ASC')->fetchAll();
    $categorias = $db->query('SELECT DISTINCT categoria FROM livros ORDER BY categoria ASC')->fetchAll(PDO::FETCH_COLUMN);

    // Itens do carrinho atual
    $itensCarrinho = [];
    $totalCarrinho = 0.0;
    $qtdItensCarrinho = 0;

    if (!empty($_SESSION['carrinho'])) {
        $ids = array_map('intval', array_keys($_SESSION['carrinho']));
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmtCart = $db->prepare("SELECT id, titulo, preco, estoque FROM livros WHERE id IN ($placeholders)");
        $stmtCart->execute($ids);
        $livrosCartMap = [];
        foreach ($stmtCart->fetchAll() as $row) {
            $livrosCartMap[(int) $row['id']] = $row;
        }

        foreach ($_SESSION['carrinho'] as $idLiv => $qtd) {
            $idLiv = (int) $idLiv;
            $qtd = (int) $qtd;
            if (isset($livrosCartMap[$idLiv]) && $qtd > 0) {
                $preco = (float) $livrosCartMap[$idLiv]['preco'];
                $subtotal = $preco * $qtd;
                $totalCarrinho += $subtotal;
                $qtdItensCarrinho += $qtd;
                $itensCarrinho[] = [
                    'id' => $idLiv,
                    'titulo' => $livrosCartMap[$idLiv]['titulo'],
                    'preco' => $preco,
                    'quantidade' => $qtd,
                    'subtotal' => $subtotal,
                ];
            }
        }
    }

    // Pedidos do cliente logado
    $meusPedidos = [];
    if ($usuarioId !== null && $usuarioPerfil !== 'funcionario') {
        $stmtPedidos = $db->prepare('SELECT id, total, status, criado_em FROM pedidos WHERE usuario_id = ? ORDER BY id DESC');
        $stmtPedidos->execute([$usuarioId]);
        $meusPedidos = $stmtPedidos->fetchAll();
    }
} catch (Throwable $e) {
    $erro = 'Não foi possível carregar o acervo no momento.';
    $livros = $livros ?? [];
    $categorias = $categorias ?? [];
    $itensCarrinho = [];
    $totalCarrinho = 0.0;
    $qtdItensCarrinho = 0;
    $meusPedidos = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tráfico do Conhecimento — Livraria</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <a class="brand" href="index.php">Tráfico do Conhecimento</a>
        <div class="nav-actions">
            <?php if ($usuarioNome !== null): ?>
                <span>Olá, <strong><?= htmlspecialchars($usuarioNome, ENT_QUOTES, 'UTF-8') ?></strong> (<?= htmlspecialchars($usuarioPerfil ?? 'cliente', ENT_QUOTES, 'UTF-8') ?>)</span>
                <?php if ($usuarioPerfil === 'funcionario'): ?>
                    <a class="btn btn-outline" href="admin_dashboard.php">Painel Administrativo</a>
                <?php else: ?>
                    <a class="btn btn-outline" href="#carrinho-cliente">Carrinho (<?= $qtdItensCarrinho ?>)</a>
                <?php endif; ?>
                <a class="btn btn-outline" href="logout.php">Sair</a>
            <?php else: ?>
                <a class="btn btn-outline" href="#carrinho-cliente">Carrinho (<?= $qtdItensCarrinho ?>)</a>
                <a class="btn" href="login.php">Entrar</a>
            <?php endif; ?>
        </div>
    </header>

    <section class="hero">
        <h1>Explore nosso Acervo</h1>
        <p>Descubra títulos selecionados de Tecnologia, Ficção Científica, História e Literatura Brasileira.</p>
        <div class="search-bar">
            <input type="search" id="busca-termo" placeholder="Buscar por título, autor ou ISBN...">
            <select id="busca-categoria">
                <option value="">Todas as categorias</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($cat, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </section>

    <main>
        <?php if ($mensagem !== ''): ?>
            <div class="alert alert-ok"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($erro !== ''): ?>
            <div class="alert alert-err"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <?php if ($usuarioPerfil !== 'funcionario'): ?>
            <section id="carrinho-cliente" class="cart-box" aria-label="Carrinho de Compras">
                <h2 style="margin: 0 0 12px; color: var(--bg-dark);">Carrinho de Compras (<?= $qtdItensCarrinho ?> <?= $qtdItensCarrinho === 1 ? 'item' : 'itens' ?>)</h2>

                <?php if (empty($itensCarrinho)): ?>
                    <p style="margin: 0; color: var(--muted);">Seu carrinho está vazio. Clique em <strong>Adicionar</strong> em qualquer livro abaixo para montar seu pedido.</p>
                <?php else: ?>
                    <?php foreach ($itensCarrinho as $item): ?>
                        <div class="cart-item">
                            <div>
                                <strong><?= htmlspecialchars($item['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <span style="color: var(--muted);"> — <?= (int) $item['quantidade'] ?> × R$ <?= number_format($item['preco'], 2, ',', '.') ?></span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <strong>R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></strong>
                                <form method="POST" action="index.php" style="margin: 0;">
                                    <input type="hidden" name="acao_carrinho" value="remover">
                                    <input type="hidden" name="livro_id" value="<?= (int) $item['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm">Remover</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="cart-total-bar">
                        <div>
                            <span style="color: var(--muted); font-size: 0.9rem;">Total do Pedido:</span>
                            <strong style="font-size: 1.35rem; color: var(--bg-dark); margin-left: 6px;">R$ <?= number_format($totalCarrinho, 2, ',', '.') ?></strong>
                        </div>
                        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                            <form method="POST" action="index.php" style="margin: 0;">
                                <input type="hidden" name="acao_carrinho" value="limpar">
                                <button type="submit" class="btn btn-outline" style="color: var(--bg-dark); border-color: var(--border);">Limpar Carrinho</button>
                            </form>

                            <?php if ($usuarioId !== null): ?>
                                <form method="POST" action="index.php" style="margin: 0;">
                                    <input type="hidden" name="acao_carrinho" value="finalizar">
                                    <button type="submit" class="btn btn-success">Finalizar Compra</button>
                                </form>
                            <?php else: ?>
                                <a class="btn btn-success" href="login.php">Entrar para Finalizar Compra</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <div class="grid" id="grid-livros">
            <?php foreach ($livros as $livro): ?>
                <article class="book-card">
                    <div class="cover-wrap">
                        <span class="badge"><?= htmlspecialchars($livro['categoria'], ENT_QUOTES, 'UTF-8') ?></span>
                        <img src="<?= htmlspecialchars($livro['capa_url'], ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($livro['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="book-body">
                        <h2 class="book-title"><?= htmlspecialchars($livro['titulo'], ENT_QUOTES, 'UTF-8') ?></h2>
                        <p class="book-author">por <?= htmlspecialchars($livro['autor'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="book-desc"><?= htmlspecialchars($livro['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                        <div class="book-footer">
                            <div>
                                <div class="price">R$ <?= number_format((float) $livro['preco'], 2, ',', '.') ?></div>
                                <div class="stock"><?= (int) $livro['estoque'] ?> em estoque</div>
                            </div>
                            <?php if ((int) $livro['estoque'] > 0): ?>
                                <form method="POST" action="index.php" style="margin: 0;">
                                    <input type="hidden" name="acao_carrinho" value="adicionar">
                                    <input type="hidden" name="livro_id" value="<?= (int) $livro['id'] ?>">
                                    <button type="submit" class="btn">Adicionar</button>
                                </form>
                            <?php else: ?>
                                <button type="button" class="btn" disabled style="opacity: 0.5; cursor: not-allowed;">Sem estoque</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($usuarioId !== null && $usuarioPerfil !== 'funcionario' && !empty($meusPedidos)): ?>
            <section class="panel-card" style="margin-top: 32px;">
                <h2 style="margin-top: 0; color: var(--bg-dark);">Meus Pedidos</h2>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Pedido #</th>
                                <th>Data</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($meusPedidos as $ped): ?>
                                <?php
                                $st = (string) $ped['status'];
                                $cls = 'status-pendente';
                                if ($st === 'Confirmado') $cls = 'status-confirmado';
                                elseif ($st === 'Cancelado') $cls = 'status-cancelado';
                                ?>
                                <tr>
                                    <td>#<?= (int) $ped['id'] ?></td>
                                    <td><?= htmlspecialchars((string) $ped['criado_em'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><strong>R$ <?= number_format((float) $ped['total'], 2, ',', '.') ?></strong></td>
                                    <td><span class="status-badge <?= $cls ?>"><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        <?php endif; ?>
    </main>

    <script src="assets/js/app.js"></script>
</body>
</html>
