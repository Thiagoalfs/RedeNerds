document.addEventListener("DOMContentLoaded", function () {
    carregarEquipe();
});

async function carregarEquipe() {
    const containerEquipe = document.getElementById("equipe");
    if (!containerEquipe) return;

    try {
        const response = await fetch("/api/equipe_api.php");
        if (!response.ok) {
            throw new Error(`Erro ao carregar dados: ${response.status}`);
        }

        const cargos = await response.json();

        // Limpa o container
        containerEquipe.innerHTML = "";

        cargos.forEach(cargo => {
            const section = document.createElement("section");

            // Para cada nick no array de membros, gera a tag de imagem e a nametag
            const membrosHtml = cargo.members.map(nick => {
                const skinUrl = `https://vzge.me/bust/${nick}.png`;

                return `
                    <div class="skin">
                        <img src="${skinUrl}" alt="Skin de ${nick}" loading="lazy">
                        <div class="nametag-box">
                            <p class="nametag">${nick}</p>
                        </div>
                    </div>
                `;
            }).join("");

            section.innerHTML = `
                <div class="equipe-header">
                    <h3>${cargo.categoryTitle}</h3>
                    <div class="equipe-header-color"></div>
                </div>
                <div class="skins-wrapper">
                    ${membrosHtml}
                </div>
            `;

            containerEquipe.appendChild(section);
        });

    } catch (error) {
        console.error("Erro ao carregar a equipe:", error);
        containerEquipe.innerHTML = "<p style='color: white; text-align: center;'>Erro ao carregar os membros da equipe.</p>";
    }
}
