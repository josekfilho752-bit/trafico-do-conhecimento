<?php
declare(strict_types=1);

session_start();
header('Content-Type: text/html; charset=UTF-8');

// Acesso restrito exclusivamente a usuários autenticados com perfil 'funcionario'
if (!isset($_SESSION['usuario_id']) || ($_SESSION['usuario_perfil'] ?? '') !== 'funcionario') {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/config/database.php';

$db = (new Database())->getConnection();
$mensagem = '';
$erro = '';

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
    $acao = $_POST['acao'] ?? '';

    try {
        if ($acao === 'salvar') {
            $id        = (int) ($_POST['id'] ?? 0);
            $titulo    = trim($_POST['titulo'] ?? '');
            $autor     = trim($_POST['autor'] ?? '');
            $categoria = trim($_POST['categoria'] ?? '');
            $isbn      = trim($_POST['isbn'] ?? '');
            $preco     = (float) str_replace(',', '.', $_POST['preco'] ?? '0');
            $estoque   = (int) ($_POST['estoque'] ?? 0);
            $descricao = trim($_POST['descricao'] ?? '');
            $capaUrl   = trim($_POST['capa_url'] ?? '');

            if ($capaUrl === '') {
                $capaUrl = 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?auto=format&fit=crop&w=400&q=80';
            }

            if ($titulo === '' || $autor === '' || $categoria === '' || $isbn === '') {
                $erro = 'Preencha todos os campos obrigatórios (Título, Autor, Categoria e ISBN).';
            } elseif ($preco <= 0 || $estoque < 0) {
                $erro = 'O preço deve ser maior que zero e o estoque não pode ser negativo.';
            } elseif ($id > 0) {
                $stmt = $db->prepare('UPDATE livros SET titulo = ?, autor = ?, categoria = ?, isbn = ?, preco = ?, estoque = ?, descricao = ?, capa_url = ? WHERE id = ?');
                $stmt->execute([$titulo, $autor, $categoria, $isbn, $preco, $estoque, $descricao, $capaUrl, $id]);
                $mensagem = 'Livro atualizado com sucesso.';
            } else {
                $stmt = $db->prepare('INSERT INTO livros (titulo, autor, categoria, isbn, preco, estoque, descricao, capa_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute([$titulo, $autor, $categoria, $isbn, $preco, $estoque, $descricao, $capaUrl]);
                $mensagem = 'Novo livro cadastrado no acervo.';
            }
        } elseif ($acao === 'excluir') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id > 0) {
                $stmt = $db->prepare('DELETE FROM livros WHERE id = ?');
                $stmt->execute([$id]);
                $mensagem = 'Livro removido do acervo.';
            }
        } elseif ($acao === 'atualizar_status_pedido') {
            $pedidoId = (int) ($_POST['pedido_id'] ?? 0);
            $novoStatus = $_POST['novo_status'] ?? '';

            if ($pedidoId > 0 && in_array($novoStatus, ['Pendente', 'Confirmado', 'Cancelado'], true)) {
                $stmtPed = $db->prepare('SELECT status FROM pedidos WHERE id = ? LIMIT 1');
                $stmtPed->execute([$pedidoId]);
                $pedidoAtual = $stmtPed->fetch();

                if ($pedidoAtual) {
                    $statusAnterior = $pedidoAtual['status'];

                    if ($statusAnterior !== 'Cancelado' && $novoStatus === 'Cancelado') {
                        // Devolve o estoque dos itens se o pedido for cancelado
                        $stmtItens = $db->prepare('SELECT livro_id, quantidade FROM pedido_itens WHERE pedido_id = ?');
                        $stmtItens->execute([$pedidoId]);
                        $stmtDevolve = $db->prepare('UPDATE livros SET estoque = estoque + ? WHERE id = ?');
                        foreach ($stmtItens->fetchAll() as $it) {
                            $stmtDevolve->execute([(int) $it['quantidade'], (int) $it['livro_id']]);
                        }
                    }

                    $stmtUpd = $db->prepare('UPDATE pedidos SET status = ? WHERE id = ?');
                    $stmtUpd->execute([$novoStatus, $pedidoId]);
                    $mensagem = 'Status do pedido #' . $pedidoId . ' atualizado para "' . $novoStatus . '".';
                }
            }
        }
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            $erro = 'Já existe um livro cadastrado com este ISBN.';
        } else {
            $erro = 'Erro ao salvar alterações no banco de dados.';
        }
    }
}

$livroEdicao = null;
if (isset($_GET['editar'])) {
    $idEditar = (int) $_GET['editar'];
    $stmt = $db->prepare('SELECT * FROM livros WHERE id = ?');
    $stmt->execute([$idEditar]);
    $livroEdicao = $stmt->fetch() ?: null;
}

$porPagina    = 5;
$paginaAtual  = max(1, (int) ($_GET['pagina'] ?? 1));
$totalLivros  = (int) $db->query('SELECT COUNT(*) FROM livros')->fetchColumn();
$totalPaginas = max(1, (int) ceil($totalLivros / $porPagina));
$paginaAtual  = min($paginaAtual, $totalPaginas);
$offset       = ($paginaAtual - 1) * $porPagina;

$stmtLista = $db->prepare('SELECT * FROM livros ORDER BY id DESC LIMIT :limit OFFSET :offset');
$stmtLista->bindValue(':limit', $porPagina, PDO::PARAM_INT);
$stmtLista->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmtLista->execute();
$livros = $stmtLista->fetchAll();

$kpi = $db->query('SELECT COUNT(*) AS total_titulos, COALESCE(SUM(estoque), 0) AS total_exemplares, COALESCE(SUM(preco * estoque), 0) AS valor_acervo FROM livros')->fetch();

// Pedidos realizados pelos clientes para confirmação do funcionário
$pedidos = $db->query("
    SELECT p.id, p.total, p.status, p.criado_em, u.nome AS cliente_nome, u.email AS cliente_email
    FROM pedidos p
    INNER JOIN usuarios u ON u.id = p.usuario_id
    ORDER BY p.id DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel Administrativo — Tráfico do Conhecimento</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <header>
        <a class="brand" href="index.php">Tráfico do Conhecimento — Admin</a>
        <div class="nav-actions">
            <span>Funcionário: <strong><?= htmlspecialchars($_SESSION['usuario_nome'], ENT_QUOTES, 'UTF-8') ?></strong></span>
            <a class="btn btn-outline" href="index.php">Ver Vitrine</a>
            <a class="btn btn-outline" href="logout.php">Sair</a>
        </div>
    </header>

    <main>
        <!-- Indicadores internos apenas para o painel administrativo do funcionário -->
        <section class="kpi-grid" aria-label="Resumo Interno do Acervo">
            <div class="kpi-card">
                <span class="kpi-label">Títulos Cadastrados</span>
                <strong class="kpi-value"><?= (int) ($kpi['total_titulos'] ?? 0) ?></strong>
                <span class="kpi-sub">Total no banco de dados</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Exemplares em Estoque</span>
                <strong class="kpi-value"><?= (int) ($kpi['total_exemplares'] ?? 0) ?></strong>
                <span class="kpi-sub">Soma das quantidades</span>
            </div>
            <div class="kpi-card">
                <span class="kpi-label">Valor Total do Acervo</span>
                <strong class="kpi-value">R$ <?= number_format((float) ($kpi['valor_acervo'] ?? 0), 2, ',', '.') ?></strong>
                <span class="kpi-sub">Preço × Estoque</span>
            </div>
        </section>

        <?php if ($mensagem !== ''): ?>
            <div class="alert alert-ok"><?= htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($erro !== ''): ?>
            <div class="alert alert-err"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <div id="alerta-validacao-js" class="alert alert-err" style="display: none;"></div>

        <!-- Gestão e Confirmação de Pedidos dos Clientes -->
        <section class="panel-card">
            <h2 style="margin-top: 0; color: var(--bg-dark);">Pedidos de Clientes (<?= count($pedidos) ?>)</h2>
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Pedido #</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Total</th>
                            <th>Status Atual</th>
                            <th>Ação do Funcionário</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                            <tr>
                                <td colspan="6">Nenhum pedido registrado até o momento.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $ped): ?>
                                <?php
                                $st = (string) $ped['status'];
                                $cls = 'status-pendente';
                                if ($st === 'Confirmado') $cls = 'status-confirmado';
                                elseif ($st === 'Cancelado') $cls = 'status-cancelado';
                                ?>
                                <tr>
                                    <td><strong>#<?= (int) $ped['id'] ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars($ped['cliente_nome'], ENT_QUOTES, 'UTF-8') ?><br>
                                        <small style="color: var(--muted);"><?= htmlspecialchars($ped['cliente_email'], ENT_QUOTES, 'UTF-8') ?></small>
                                    </td>
                                    <td><?= htmlspecialchars((string) $ped['criado_em'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><strong>R$ <?= number_format((float) $ped['total'], 2, ',', '.') ?></strong></td>
                                    <td><span class="status-badge <?= $cls ?>"><?= htmlspecialchars($st, ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td>
                                        <form method="POST" style="display: flex; gap: 8px; align-items: center; margin: 0;">
                                            <input type="hidden" name="acao" value="atualizar_status_pedido">
                                            <input type="hidden" name="pedido_id" value="<?= (int) $ped['id'] ?>">
                                            <select name="novo_status" style="width: auto; padding: 6px 10px;">
                                                <option value="Pendente" <?= $st === 'Pendente' ? 'selected' : '' ?>>Pendente</option>
                                                <option value="Confirmado" <?= $st === 'Confirmado' ? 'selected' : '' ?>>Confirmado</option>
                                                <option value="Cancelado" <?= $st === 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                            </select>
                                            <button type="submit" class="btn btn-success btn-sm">Confirmar Status</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel-card">
            <h2 style="margin-top: 0; color: var(--bg-dark);">
                <?= $livroEdicao ? 'Editar Livro #' . (int) $livroEdicao['id'] : 'Cadastrar Novo Livro' ?>
            </h2>
            <form method="POST" id="form-livro" novalidate>
                <input type="hidden" name="acao" value="salvar">
                <input type="hidden" name="id" value="<?= (int) ($livroEdicao['id'] ?? 0) ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label for="titulo">Título *</label>
                        <input id="titulo" type="text" name="titulo" required value="<?= htmlspecialchars($livroEdicao['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="autor">Autor *</label>
                        <input id="autor" type="text" name="autor" required value="<?= htmlspecialchars($livroEdicao['autor'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="categoria">Categoria *</label>
                        <input id="categoria" type="text" name="categoria" required placeholder="Ex.: Tecnologia, História, Ficção Científica" value="<?= htmlspecialchars($livroEdicao['categoria'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="isbn">ISBN *</label>
                        <input id="isbn" type="text" name="isbn" required placeholder="978-8500000000" value="<?= htmlspecialchars($livroEdicao['isbn'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="preco">Preço (R$) *</label>
                        <input id="preco" type="number" step="0.01" min="0.01" name="preco" required value="<?= htmlspecialchars((string) ($livroEdicao['preco'] ?? '49.90'), ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="estoque">Estoque *</label>
                        <input id="estoque" type="number" min="0" name="estoque" required value="<?= (int) ($livroEdicao['estoque'] ?? 10) ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 14px;">
                    <label for="capa_url">URL da Capa</label>
                    <input id="capa_url" type="url" name="capa_url" placeholder="https://..." value="<?= htmlspecialchars($livroEdicao['capa_url'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group" style="margin-top: 14px;">
                    <label for="descricao">Descrição</label>
                    <textarea id="descricao" name="descricao" rows="3"><?= htmlspecialchars($livroEdicao['descricao'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>

                <div style="margin-top: 18px; display: flex; gap: 10px;">
                    <button type="submit" class="btn"><?= $livroEdicao ? 'Salvar Alterações' : 'Cadastrar Livro' ?></button>
                    <?php if ($livroEdicao): ?>
                        <a href="admin_dashboard.php" class="btn btn-outline" style="color: var(--bg-dark); border-color: var(--border);">Cancelar Edição</a>
                    <?php endif; ?>
                </div>
            </form>
        </section>

        <section class="panel-card">
            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 12px; margin-bottom: 16px;">
                <h2 style="margin: 0; color: var(--bg-dark);">Acervo Cadastrado (<?= $totalLivros ?>)</h2>
                <input type="search" id="admin-busca-titulo" placeholder="Pesquisar por título em tempo real..." style="padding: 10px 14px; border: 1px solid var(--border); border-radius: 8px; min-width: 280px;">
            </div>

            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Autor</th>
                            <th>Categoria</th>
                            <th>ISBN</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="admin-tabela-corpo">
                        <?php foreach ($livros as $livro): ?>
                            <tr>
                                <td><?= (int) $livro['id'] ?></td>
                                <td><strong><?= htmlspecialchars($livro['titulo'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                                <td><?= htmlspecialchars($livro['autor'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($livro['categoria'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($livro['isbn'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td>R$ <?= number_format((float) $livro['preco'], 2, ',', '.') ?></td>
                                <td><?= (int) $livro['estoque'] ?></td>
                                <td style="display: flex; gap: 8px;">
                                    <a class="btn" href="admin_dashboard.php?editar=<?= (int) $livro['id'] ?>">Editar</a>
                                    <form method="POST" onsubmit="return confirm('Deseja realmente excluir este livro?');" style="margin: 0;">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $livro['id'] ?>">
                                        <button type="submit" class="btn btn-danger">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <nav id="admin-paginacao" class="pagination" aria-label="Paginação do acervo" style="margin-top: 16px; display: flex; gap: 8px; flex-wrap: wrap;">
                    <?php for ($p = 1; $p <= $totalPaginas; $p++): ?>
                        <a class="btn <?= $p === $paginaAtual ? '' : 'btn-outline' ?>"
                           style="<?= $p === $paginaAtual ? '' : 'color: var(--bg-dark); border-color: var(--border);' ?>"
                           href="admin_dashboard.php?pagina=<?= $p ?>">
                            Página <?= $p ?>
                        </a>
                    <?php endfor; ?>
                </nav>
            <?php endif; ?>
        </section>
    </main>

    <script src="assets/js/admin.js"></script>
</body>
</html>
