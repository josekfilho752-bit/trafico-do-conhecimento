document.addEventListener('DOMContentLoaded', () => {
    const inputBusca = document.getElementById('busca-termo');
    const selectCategoria = document.getElementById('busca-categoria');
    const gridLivros = document.getElementById('grid-livros');

    if (!inputBusca || !selectCategoria || !gridLivros) {
        return;
    }

    const escaparHtml = (texto) => {
        const div = document.createElement('div');
        div.textContent = texto ?? '';
        return div.innerHTML;
    };

    const formatarPreco = (valor) => {
        return Number(valor).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    };

    const carregarLivros = async () => {
        const q = encodeURIComponent(inputBusca.value.trim());
        const cat = encodeURIComponent(selectCategoria.value);

        try {
            const resposta = await fetch(`api/buscar_livros.php?q=${q}&categoria=${cat}`);
            if (!resposta.ok) {
                throw new Error('Falha ao consultar o acervo.');
            }
            const livros = await resposta.json();

            if (!Array.isArray(livros) || livros.length === 0) {
                gridLivros.innerHTML = '<p>Nenhum livro encontrado para os filtros selecionados.</p>';
                return;
            }

            gridLivros.innerHTML = livros.map((livro) => {
                const estoque = Number(livro.estoque);
                const acaoCompra = estoque > 0
                    ? `<form method="POST" action="index.php" style="margin: 0;">
                           <input type="hidden" name="acao_carrinho" value="adicionar">
                           <input type="hidden" name="livro_id" value="${Number(livro.id)}">
                           <button type="submit" class="btn">Adicionar</button>
                       </form>`
                    : `<button type="button" class="btn" disabled style="opacity: 0.5; cursor: not-allowed;">Sem estoque</button>`;

                return `
                    <article class="book-card">
                        <div class="cover-wrap">
                            <span class="badge">${escaparHtml(livro.categoria)}</span>
                            <img src="${escaparHtml(livro.capa_url)}" alt="${escaparHtml(livro.titulo)}">
                        </div>
                        <div class="book-body">
                            <h2 class="book-title">${escaparHtml(livro.titulo)}</h2>
                            <p class="book-author">por ${escaparHtml(livro.autor)}</p>
                            <p class="book-desc">${escaparHtml(livro.descricao)}</p>
                            <div class="book-footer">
                                <div>
                                    <div class="price">R$ ${formatarPreco(livro.preco)}</div>
                                    <div class="stock">${estoque} em estoque</div>
                                </div>
                                ${acaoCompra}
                            </div>
                        </div>
                    </article>
                `;
            }).join('');
        } catch (erro) {
            console.error(erro);
        }
    };

    let timer = null;
    inputBusca.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(carregarLivros, 250);
    });

    selectCategoria.addEventListener('change', carregarLivros);
});
