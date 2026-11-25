CREATE DATABASE IF NOT EXISTS trafico_conhecimento
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE trafico_conhecimento;

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    perfil ENUM('cliente', 'funcionario') NOT NULL DEFAULT 'cliente',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS livros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(150) NOT NULL,
    autor VARCHAR(120) NOT NULL,
    categoria VARCHAR(80) NOT NULL,
    isbn VARCHAR(20) NOT NULL UNIQUE,
    preco DECIMAL(10, 2) NOT NULL,
    estoque INT NOT NULL DEFAULT 0,
    descricao TEXT,
    capa_url VARCHAR(255) DEFAULT 'https://images.unsplash.com/photo-1544947950-fa07a98d237f?auto=format&fit=crop&w=400&q=80',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedidos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    total DECIMAL(10, 2) NOT NULL,
    status ENUM('Pendente', 'Confirmado', 'Cancelado') NOT NULL DEFAULT 'Pendente',
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pedido_itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pedido_id INT NOT NULL,
    livro_id INT NOT NULL,
    quantidade INT NOT NULL,
    preco_unitario DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
    FOREIGN KEY (livro_id) REFERENCES livros(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Senha padrão para ambos os usuários: 123456
INSERT INTO usuarios (nome, email, senha, perfil) VALUES
('Administrador Livraria', 'admin@traficoconhecimento.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'funcionario'),
('Leitor Assíduo', 'cliente@traficoconhecimento.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cliente')
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    senha = VALUES(senha),
    perfil = VALUES(perfil);

INSERT INTO livros (titulo, autor, categoria, isbn, preco, estoque, descricao, capa_url) VALUES
('Clean Code: Código Limpo', 'Robert C. Martin', 'Tecnologia', '978-8576082675', 89.90, 15, 'Habilidades práticas do Agile Software para desenvolvimento limpo e sustentável.', 'https://images.unsplash.com/photo-1532012197267-da84d127e765?auto=format&fit=crop&w=400&q=80'),
('O Programador Pragmático', 'Andrew Hunt & David Thomas', 'Tecnologia', '978-8577807000', 115.00, 8, 'De aprendiz a mestre: boas práticas essenciais de engenharia de software.', 'https://images.unsplash.com/photo-1589998059171-988d887df646?auto=format&fit=crop&w=400&q=80'),
('1984', 'George Orwell', 'Ficção Científica', '978-8535914849', 45.50, 22, 'Um clássico distópico sobre vigilância, controle e liberdade de pensamento.', 'https://images.unsplash.com/photo-1543002588-bfa74002ed7e?auto=format&fit=crop&w=400&q=80'),
('Sapiens: Uma Breve História da Humanidade', 'Yuval Noah Harari', 'História', '978-8525432186', 64.90, 12, 'A jornada da espécie humana desde a Idade da Pedra até a era do silício.', 'https://images.unsplash.com/photo-1512820790803-83ca734da794?auto=format&fit=crop&w=400&q=80'),
('Arquitetura Limpa', 'Robert C. Martin', 'Tecnologia', '978-8550804606', 94.00, 10, 'O guia do artesão para estrutura e design de software.', 'https://images.unsplash.com/photo-1544716278-ca5e3f4abd8c?auto=format&fit=crop&w=400&q=80'),
('Dom Casmurro', 'Machado de Assis', 'Literatura Brasileira', '978-8535910667', 38.90, 18, 'Clássico da literatura brasileira que explora memória, ciúme e ambiguidade.', 'https://images.unsplash.com/photo-1519682337058-a94d519337bc?auto=format&fit=crop&w=400&q=80')
ON DUPLICATE KEY UPDATE
    titulo = VALUES(titulo),
    autor = VALUES(autor),
    categoria = VALUES(categoria),
    preco = VALUES(preco),
    estoque = VALUES(estoque),
    descricao = VALUES(descricao),
    capa_url = VALUES(capa_url);
