function calcularPrecoMedio() {
    const linhas = document.querySelectorAll('tbody.bn-table-tbody tr');
    let totalQuantidade = 0;
    let totalValor = 0;

    linhas.forEach(linha => {
        const quantidadeCell = linha.querySelector('td:nth-child(7)'); 
        const precoCell = linha.querySelector('td:nth-child(8)'); 

        if (quantidadeCell && precoCell) {
            const quantidade = parseFloat(quantidadeCell.textContent);
            const preco = parseFloat(precoCell.textContent.split('≈')[1]);

            totalQuantidade += quantidade;
            totalValor += quantidade * preco; // Adiciona o valor total
        }
    });

    const precoMedio = totalValor / totalQuantidade;
    console.error(`Preço médio: ${precoMedio.toFixed(2)} USDT`);
    console.error(`Total adquirido: ${totalQuantidade.toFixed(8)}`);
}

calcularPrecoMedio();
