<?php
declare(strict_types=1);

session_start();
header('Content-Type: text/html; charset=UTF-8');

require_once __DIR__ . '/config/database.php';

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? 'login';

    try {
        $db = (new Database())->getConnection();

        if ($acao === 'login') {
            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';

            if ($email === '' || $senha === '') {
                $erro = 'Preencha e-mail e senha.';
            } else {
                $stmt = $db->prepare('SELECT id, nome, email, senha, perfil FROM usuarios WHERE email = ? LIMIT 1');
                $stmt->execute([$email]);
                $usuario = $stmt->fetch();

                if ($usuario && password_verify($senha, $usuario['senha'])) {
                    $_SESSION['usuario_id']     = (int) $usuario['id'];
                    $_SESSION['usuario_nome']   = $usuario['nome'];
                    $_SESSION['usuario_email']  = $usuario['email'];
                    $_SESSION['usuario_perfil'] = $usuario['perfil'];

                    if ($usuario['perfil'] === 'funcionario') {
                        header('Location: admin_dashboard.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit;
                }

                $erro = 'E-mail ou senha inválidos.';
            }
        } elseif ($acao === 'cadastro') {
            $nome  = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';

            if ($nome === '' || $email === '' || strlen($senha) < 6) {
                $erro = 'Informe nome, e-mail válido e senha com pelo menos 6 caracteres.';
            } else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $db->prepare('INSERT INTO usuarios (nome, email, senha, perfil) VALUES (?, ?, ?, ?)');
                $stmt->execute([$nome, $email, $hash, 'cliente']);
                $sucesso = 'Conta de leitor criada com sucesso. Faça login ao lado.';
            }
        }
    } catch (PDOException $e) {
        if ((int) $e->getCode() === 23000) {
            $erro = 'Este e-mail já está cadastrado.';
        } else {
            $erro = 'Erro ao processar a solicitação no banco de dados.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — Tráfico do Conhecimento</title>
    <style>
        :root {
            --bg-dark: #081b33;
            --primary: #2563eb;
            --accent: #3b82f6;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
        }
        * { box-sizing: border-box; font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; }
        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--bg-dark), #0f294a);
            color: var(--text);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }
        .container {
            width: 100%;
            max-width: 860px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }
        .card {
            background: var(--surface);
            border-radius: 14px;
            padding: 28px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.25);
        }
        h1, h2 { margin-top: 0; color: var(--bg-dark); }
        label { display: block; margin-top: 14px; font-weight: 600; font-size: 0.9rem; }
        input {
            width: 100%;
            margin-top: 6px;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
        }
        button {
            width: 100%;
            margin-top: 20px;
            padding: 12px;
            border: 0;
            border-radius: 8px;
            background: var(--primary);
            color: #fff;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
        }
        button:hover { background: var(--accent); }
        .alert-err {
            grid-column: 1 / -1;
            background: #fee2e2;
            color: #991b1b;
            padding: 14px 18px;
            border-radius: 10px;
            border: 1px solid #fca5a5;
        }
        .alert-ok {
            grid-column: 1 / -1;
            background: #dcfce7;
            color: #166534;
            padding: 14px 18px;
            border-radius: 10px;
            border: 1px solid #86efac;
        }
        .demo-box {
            margin-top: 18px;
            padding: 12px 14px;
            background: #eff6ff;
            border-radius: 8px;
            font-size: 0.88rem;
            color: #1e3a8a;
        }
        .top-link { display: inline-block; margin-bottom: 14px; color: var(--primary); text-decoration: none; font-weight: 600; }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($erro !== ''): ?>
            <div class="alert-err"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <?php if ($sucesso !== ''): ?>
            <div class="alert-ok"><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="card">
            <a class="top-link" href="index.php">← Voltar à vitrine</a>
            <h1>Acessar o Sistema</h1>
            <p style="color: var(--muted); margin-top: 0;">Entre com suas credenciais de Cliente ou Funcionário.</p>
            <form method="POST">
                <input type="hidden" name="acao" value="login">
                <label for="email">E-mail</label>
                <input id="email" type="email" name="email" placeholder="seu@email.com" required>

                <label for="senha">Senha</label>
                <input id="senha" type="password" name="senha" placeholder="••••••" required>

                <button type="submit">Entrar</button>
            </form>
            <div class="demo-box">
                <strong>Credenciais de Teste (Senha: 123456):</strong>
                <ul style="margin: 6px 0 0; padding-left: 18px;">
                    <li><strong>Funcionário:</strong> admin@traficoconhecimento.com</li>
                    <li><strong>Cliente:</strong> cliente@traficoconhecimento.com</li>
                </ul>
            </div>
        </div>

        <div class="card">
            <h2>Criar Conta de Leitor</h2>
            <p style="color: var(--muted); margin-top: 0;">Cadastre-se para comprar na loja.</p>
            <form method="POST">
                <input type="hidden" name="acao" value="cadastro">
                <label for="nome">Nome completo</label>
                <input id="nome" type="text" name="nome" placeholder="Seu nome" required>

                <label for="cad-email">E-mail</label>
                <input id="cad-email" type="email" name="email" placeholder="leitor@exemplo.com" required>

                <label for="cad-senha">Senha (mín. 6 caracteres)</label>
                <input id="cad-senha" type="password" name="senha" minlength="6" required>

                <button type="submit">Cadastrar como Cliente</button>
            </form>
        </div>
    </div>
</body>
</html>
