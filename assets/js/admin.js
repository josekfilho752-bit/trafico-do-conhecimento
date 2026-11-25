document.addEventListener('DOMContentLoaded', () => {
    const formLivro = document.getElementById('form-livro');
    const alertaJs = document.getElementById('alerta-validacao-js');
    const campoBusca = document.getElementById('admin-busca-titulo');
    const corpoTabela = document.getElementById('admin-tabela-corpo');
    const paginacaoNav = document.getElementById('admin-paginacao');

    const mostrarErro = (msg) => {
        if (!alertaJs) return;
        alertaJs.textContent = msg;
        alertaJs.style.display = 'block';
        alertaJs.scrollIntoView({ behavior: 'smooth', block: 'center' });
    };

    if (formLivro) {
        formLivro.addEventListener('submit', (event) => {
            if (alertaJs) alertaJs.style.display = 'none';

            const titulo = document.getElementById('titulo').value.trim();
            const autor = document.getElementById('autor').value.trim();
            const categoria = document.getElementById('categoria').value.trim();
            const isbn = document.getElementById('isbn').value.trim();
            const preco = parseFloat(document.getElementById('preco').value);
            const estoque = parseInt(document.getElementById('estoque').value, 10);

            if (titulo.length < 2) {
                event.preventDefault();
                mostrarErro('Informe um título válido com pelo menos 2 caracteres.');
                return;
            }
            if (autor.length < 2) {
                event.preventDefault();
                mostrarErro('Informe o nome do autor.');
                return;
            }
            if (categoria === '') {
                event.preventDefault();
                mostrarErro('Informe a categoria do livro.');
                return;
            }
            if (!/^[0-9Xx-]{10,17}$/.test(isbn)) {
                event.preventDefault();
                mostrarErro('Informe um ISBN válido (ex.: 978-8576082675).');
                return;
            }
            if (Number.isNaN(preco) || preco <= 0) {
                event.preventDefault();
                mostrarErro('O preço do livro deve ser maior que R$ 0,00.');
                return;
            }
            if (Number.isNaN(estoque) || estoque < 0) {
                event.preventDefault();
                mostrarErro('O estoque deve ser zero ou um número inteiro positivo.');
            }
        });
    }

    const escaparHtml = (texto) => {
        const div = document.createElement('div');
        div.textContent = texto ?? '';
        return div.innerHTML;
    };

    if (campoBusca && corpoTabela) {
        let timer = null;
        campoBusca.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(async () => {
                const termo = campoBusca.value.trim();
                if (paginacaoNav) {
                    paginacaoNav.style.display = termo === '' ? 'flex' : 'none';
                }

                try {
                    const resp = await fetch(`api/buscar_livros.php?q=${encodeURIComponent(termo)}`);
                    if (!resp.ok) return;
                    const livros = await resp.json();

                    if (!Array.isArray(livros) || livros.length === 0) {
                        corpoTabela.innerHTML = '<tr><td colspan="8">Nenhum livro encontrado para este título.</td></tr>';
                        return;
                    }

                    corpoTabela.innerHTML = livros.map((livro) => {
                        const precoFormatado = Number(livro.preco).toLocaleString('pt-BR', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                        return `
                            <tr>
                                <td>${Number(livro.id)}</td>
                                <td><strong>${escaparHtml(livro.titulo)}</strong></td>
                                <td>${escaparHtml(livro.autor)}</td>
                                <td>${escaparHtml(livro.categoria)}</td>
                                <td>${escaparHtml(livro.isbn)}</td>
                                <td>R$ ${precoFormatado}</td>
                                <td>${Number(livro.estoque)}</td>
                                <td style="display: flex; gap: 8px;">
                                    <a class="btn" href="admin_dashboard.php?editar=${Number(livro.id)}">Editar</a>
                                    <form method="POST" onsubmit="return confirm('Deseja realmente excluir este livro?');" style="margin: 0;">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="${Number(livro.id)}">
                                        <button type="submit" class="btn btn-danger">Excluir</button>
                                    </form>
                                </td>
                            </tr>
                        `;
                    }).join('');
                } catch (e) {
                    console.error(e);
                }
            }, 250);
        });
    }
});
