import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["row"];

    filter(event) {
        // On récupère la valeur saisie, mise en minuscules et nettoyée d'éventuels espaces superflus
        const query = event.target.value.toLowerCase().trim();

        this.rowTargets.forEach(row => {
            // On récupère la string de recherche stockée dans la ligne
            const searchContent = row.dataset.search;

            if (searchContent.includes(query)) {
                // On affiche la ligne (on retire une éventuelle classe d'invisibilité Tailwind)
                row.classList.remove('hidden');
            } else {
                // On cache la ligne si ça ne match pas
                row.classList.add('hidden');
            }
        });
    }
}