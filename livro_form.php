<?php
require_once 'includes/auth.php';
requireFuncionario();

require_once 'config/database.php';
$database = new Database();
$db = $database->getConnection();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$modoEdicao = $id > 0;
$erro = '';

$livro = [
    'titulo' => '',
    'autor' => '',
    'categoria' => '',
    'isbn' => '',
    'preco' => '',
    'estoque' => '',
    'descricao' => '',
    'capa_url' => 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?auto=format&fit=crop&w=400&q=80'
];

// Se for edição, carrega os dados atuais do banco
if ($modoEdicao) {
    $stmt = $db->prepare("SELECT * FROM livros WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $dados = $stmt->fetch();
    if ($dados) {
        $livro = $dados;
    } else {
        header("Location: admin_dashboard.php");
        exit;
    }
}

// Processamento do formulário (CREATE ou UPDATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $livro['titulo'] = trim($_POST['titulo'] ?? '');
    $livro['autor'] = trim($_POST['autor'] ?? '');
    $livro['categoria'] = trim($_POST['categoria'] ?? '');
    $livro['isbn'] = trim($_POST['isbn'] ?? '');
    $livro['preco'] = str_replace(',', '.', trim($_POST['preco'] ?? '0'));
    $livro['estoque'] = (int)($_POST['estoque'] ?? 0);
    $livro['descricao'] = trim($_POST['descricao'] ?? '');
    $livro['capa_url'] = trim($_POST['capa_url'] ?? '') ?: 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?auto=format&fit=crop&w=400&q=80';

    if ($livro['titulo'] === '' || $livro['autor'] === '' || $livro['isbn'] === '' || (float)$livro['preco'] <= 0) {
        $erro = 'Preencha todos os campos obrigatórios com valores válidos.';
    } else {
        try {
            if ($modoEdicao) {
                $sql = "UPDATE livros SET 
                            titulo = :titulo, 
                            autor = :autor, 
                            categoria = :categoria, 
                            isbn = :isbn, 
                            preco = :preco, 
                            estoque = :estoque, 
                            descricao = :descricao, 
                            capa_url = :capa_url 
                        WHERE id = :id";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':titulo' => $livro['titulo'],
                    ':autor' => $livro['autor'],
                    ':categoria' => $livro['categoria'],
                    ':isbn' => $livro['isbn'],
                    ':preco' => $livro['preco'],
                    ':estoque' => $livro['estoque'],
                    ':descricao' => $livro['descricao'],
                    ':capa_url' => $livro['capa_url'],
                    ':id' => $id
                ]);
                header("Location: admin_dashboard.php?msg=atualizado");
                exit;
            } else {
                $sql = "INSERT INTO livros (titulo, autor, categoria, isbn, preco, estoque, descricao, capa_url) 
                        VALUES (:titulo, :autor, :categoria, :isbn, :preco, :estoque, :descricao, :capa_url)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    ':titulo' => $livro['titulo'],
                    ':autor' => $livro['autor'],
                    ':categoria' => $livro['categoria'],
                    ':isbn' => $livro['isbn'],
                    ':preco' => $livro['preco'],
                    ':estoque' => $livro['estoque'],
                    ':descricao' => $livro['descricao'],
                    ':capa_url' => $livro['capa_url']
                ]);
                header("Location: admin_dashboard.php?msg=cadastrado");
                exit;
            }
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $erro = 'Já existe um livro cadastrado com este código ISBN.';
            } else {
                $erro = 'Erro ao salvar no banco de dados: ' . $e->getMessage();
            }
        }
    }
}

require_once 'includes/header.php';
?>

<div class="form-container">
    <div class="form-header">
        <h1><?= $modoEdicao ? 'Editar Livro #' . $id : 'Cadastrar Novo Livro no Acervo' ?></h1>
        <a href="admin_dashboard.php" class="btn btn-outline-primary">← Voltar ao Painel</a>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($erro) ?></div>
    <?php endif; ?>

    <form method="POST" id="form-livro" class="card form-card">
        <div class="form-row">
            <div class="form-group col-8">
                <label for="titulo">Título da Obra *</label>
                <input type="text" id="titulo" name="titulo" required value="<?= htmlspecialchars($livro['titulo']) ?>" placeholder="Ex: Engenharia de Software Moderna">
            </div>
            <div class="form-group col-4">
                <label for="isbn">Código ISBN *</label>
                <input type="text" id="isbn" name="isbn" required value="<?= htmlspecialchars($livro['isbn']) ?>" placeholder="Ex: 978-6500019506">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-6">
                <label for="autor">Autor(es) *</label>
                <input type="text" id="autor" name="autor" required value="<?= htmlspecialchars($livro['autor']) ?>" placeholder="Ex: Marco Tulio Valente">
            </div>
            <div class="form-group col-6">
                <label for="categoria">Categoria / Gênero *</label>
                <input type="text" id="categoria" name="categoria" required list="lista-categorias" value="<?= htmlspecialchars($livro['categoria']) ?>" placeholder="Ex: Tecnologia, Ficção, Negócios...">
                <datalist id="lista-categorias">
                    <option value="Tecnologia">
                    <option value="Ficção Científica">
                    <option value="História">
                    <option value="Negócios">
                    <option value="Literatura Brasileira">
                </datalist>
            </div>
        </div>

        <div class="form-row">
            <div class="form-group col-4">
                <label for="preco">Preço de Venda (R$) *</label>
                <input type="number" step="0.01" min="0.01" id="preco" name="preco" required value="<?= htmlspecialchars($livro['preco']) ?>" placeholder="89.90">
            </div>
            <div class="form-group col-4">
                <label for="estoque">Quantidade em Estoque *</label>
                <input type="number" min="0" id="estoque" name="estoque" required value="<?= htmlspecialchars($livro['estoque']) ?>" placeholder="10">
            </div>
            <div class="form-group col-4">
                <label for="capa_url">URL da Imagem de Capa</label>
                <input type="url" id="capa_url" name="capa_url" value="<?= htmlspecialchars($livro['capa_url']) ?>" placeholder="https://...">
            </div>
        </div>

        <div class="form-group">
            <label for="descricao">Sinopse / Descrição do Livro</label>
            <textarea id="descricao" name="descricao" rows="4" placeholder="Breve resumo sobre o conteúdo da obra..."><?= htmlspecialchars($livro['descricao']) ?></textarea>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary btn-lg">
                <?= $modoEdicao ? 'Salvar Alterações' : 'Cadastrar no Acervo' ?>
            </button>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ?>
