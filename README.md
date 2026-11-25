# Tráfico do Conhecimento — Sistema Web Gerenciador de Acervo para Livraria

Aplicação Web Full-Stack desenvolvida com **HTML5, CSS3, JavaScript (Fetch API), PHP 8.2 (PDO)** e **MySQL 8.0**, executada em contêineres **Docker Compose**, com codificação **UTF-8 (`utf8mb4`)**.

## Funcionalidades Implementadas

- **Vitrine do Cliente (`index.php`):** Catálogo com busca assíncrona (`Fetch API`) por título, autor, ISBN e categoria, **carrinho de compras** na sessão com cálculo de subtotal/total e **finalização da compra** (transação em PDO com baixa de estoque e criação de pedido em `Pendente`), além de listagem de **Meus Pedidos** para o cliente.
- **Painel Administrativo Exclusivo para Funcionários (`admin_dashboard.php`):**
  - Acesso restrito a usuários com perfil `funcionario`.
  - Indicadores internos de estoque/acervo e tabela de **Pedidos de Clientes** para o funcionário confirmar ou alterar o status (`Pendente`, `Confirmado`, `Cancelado`, com devolução automática de estoque em caso de cancelamento).
  - CRUD completo de livros com validação client-side em JavaScript (`assets/js/admin.js`), busca por título via `Fetch API` e paginação.

## Como Executar

1. Extraia a pasta `trafico-do-conhecimento` para o seu diretório de trabalho.
2. No PowerShell, dentro da pasta do projeto, recrie o ambiente:
   ```powershell
   docker compose down -v
   docker compose up -d --build
   ```
3. Atualize o hash da senha `123456` nas contas padrão:
   ```powershell
   @'
   <?php
   $pdo = new PDO('mysql:host=db;dbname=trafico_conhecimento;charset=utf8mb4', 'user_livraria', 'senha_segura_123');
   $hash = password_hash('123456', PASSWORD_DEFAULT);
   $stmt = $pdo->prepare('UPDATE usuarios SET senha = ? WHERE email IN (?, ?)');
   $stmt->execute([$hash, 'admin@traficoconhecimento.com', 'cliente@traficoconhecimento.com']);
   echo 'Atualizados: ' . $stmt->rowCount() . PHP_EOL;
   '@ | docker compose exec -T web php
   ```

## Credenciais de Demonstração (Senha: `123456`)

- **Funcionário (Admin):** `admin@traficoconhecimento.com`
- **Cliente:** `cliente@traficoconhecimento.com`
