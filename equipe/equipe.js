document.addEventListener("DOMContentLoaded", function () {
    carregarEquipe();
});

function getCargoTheme(cargoTitle) {
    const title = (cargoTitle || "").toLowerCase().trim();

    // Fundador: Azul
    if (title.includes("fundad")) {
        return {
            gradient: "linear-gradient(to right, rgb(75, 179, 245), rgb(39, 172, 255))",
            color: "#27acff"
        };
    }
    // Gerente / Diretor: Amarelo
    if (title.includes("gerent") || title.includes("diretor")) {
        return {
            gradient: "linear-gradient(to right, #F1C40F, #F39C12)",
            color: "#F1C40F"
        };
    }
    // Coordenador: Azul bebê
    if (title.includes("coord")) {
        return {
            gradient: "linear-gradient(to right, #89CFF0, #5DADE2)",
            color: "#89CFF0"
        };
    }
    // Administrador: Vermelho
    if (title.includes("admin")) {
        return {
            gradient: "linear-gradient(to right, #E74C3C, #C0392B)",
            color: "#E74C3C"
        };
    }
    // Moderador: Verde
    if (title.includes("moder")) {
        return {
            gradient: "linear-gradient(to right, #2ECC71, #27AE60)",
            color: "#2ECC71"
        };
    }
    // Designer: Laranja
    if (title.includes("design")) {
        return {
            gradient: "linear-gradient(to right, #FF8C00, #E67E22)",
            color: "#FF8C00"
        };
    }
    // Desenvolvedor / outros: Roxo
    if (title.includes("desenvolv") || title.includes("dev")) {
        return {
            gradient: "linear-gradient(to right, #B971DA, #8E44AD)",
            color: "#B971DA"
        };
    }

    // Padrão: Azul
    return {
        gradient: "linear-gradient(to right, rgb(75, 179, 245), rgb(39, 172, 255))",
        color: "#27acff"
    };
}

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
            const theme = getCargoTheme(cargo.categoryTitle);
            const FALLBACK_SKIN = "https://vzge.me/bust/FreehandCargo95.png";
            const members = Array.isArray(cargo.members) ? cargo.members : [];
            const isCarousel = members.length > 3;

            // Para cada nick no array de membros, gera a tag de imagem e a nametag personalizada
            const membrosHtml = members.map(nick => {
                const skinUrl = `https://vzge.me/bust/${encodeURIComponent(nick)}.png`;

                return `
                    <div class="skin" style="--hover-color: ${theme.color};">
                        <img src="${skinUrl}" alt="Skin de ${nick}" loading="lazy" onerror="this.onerror=null; this.src='${FALLBACK_SKIN}';">
                        <div class="nametag-box" style="background: ${theme.gradient};">
                            <p class="nametag">${nick}</p>
                        </div>
                    </div>
                `;
            }).join("");

            const navButtonsHtml = isCarousel ? `
                <div class="carousel-nav-buttons" aria-label="Navegação do carrossel">
                    <button type="button" class="btn-carousel-scroll prev" aria-label="Membro anterior"><i class="fa-solid fa-chevron-left"></i></button>
                    <button type="button" class="btn-carousel-scroll next" aria-label="Próximo membro"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            ` : '';

            section.innerHTML = `
                <div class="equipe-header">
                    <h3>${cargo.categoryTitle}</h3>
                    <div class="equipe-header-color" style="background: linear-gradient(90deg, ${theme.color}, rgba(0, 0, 0, 0));"></div>
                    ${navButtonsHtml}
                </div>
                <div class="skins-wrapper ${isCarousel ? 'is-carousel' : ''}">
                    ${membrosHtml}
                </div>
            `;

            // Ativa o scroll suave pelos botões do carrossel
            if (isCarousel) {
                const prevBtn = section.querySelector(".btn-carousel-scroll.prev");
                const nextBtn = section.querySelector(".btn-carousel-scroll.next");
                const wrapper = section.querySelector(".skins-wrapper");

                if (prevBtn && wrapper) {
                    prevBtn.addEventListener("click", () => {
                        wrapper.scrollBy({ left: -wrapper.clientWidth * 0.7, behavior: "smooth" });
                    });
                }
                if (nextBtn && wrapper) {
                    nextBtn.addEventListener("click", () => {
                        wrapper.scrollBy({ left: wrapper.clientWidth * 0.7, behavior: "smooth" });
                    });
                }
            }

            containerEquipe.appendChild(section);
        });

    } catch (error) {
        console.error("Erro ao carregar a equipe:", error);
        containerEquipe.innerHTML = "<p style='color: white; text-align: center;'>Erro ao carregar os membros da equipe.</p>";
    }
}
