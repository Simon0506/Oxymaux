import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    // Ajout de la target 'dogSelect' pour cibler directement le <select>
    static targets = ['dog', 'name', 'toggleButton', 'dogSelect'];

    toggleName() {
        // On utilise la nouvelle target dogSelectTarget à la place du querySelector
        const dogInput = this.dogSelectTarget;
        const nameInput = this.nameTarget.querySelector('input');
        
        this.nameTarget.classList.toggle('hidden');
        this.dogTarget.classList.toggle('hidden');
        
        this.toggleButtonTarget.textContent = this.nameTarget.classList.contains('hidden') 
            ? 'Entrer le nom du participant' 
            : 'Choisir un utilisateur existant';
            
        if (this.nameTarget.classList.contains('hidden')) {
            nameInput.disabled = true;
            dogInput.disabled = false;
        } else {
            nameInput.disabled = false;
            dogInput.disabled = true;
        }
    }

    // Nouvelle méthode pour filtrer les chiens, races ou propriétaires instantanément
    filterDogs(event) {
        const query = event.target.value.toLowerCase().trim();
        const options = this.dogSelectTarget.options;
        let firstVisibleOption = null;

        for (let i = 0; i < options.length; i++) {
            const option = options[i];
            
            // On ignore l'option éventuellement vide du genre "Choisir un participant"
            if (option.value === "") continue;

            const text = option.text.toLowerCase();

            if (text.includes(query)) {
                option.style.display = ""; // Réaffiche l'option
                option.disabled = false;   // Permet sa sélection
                
                // On garde en mémoire la première option valide trouvée pour le comportement auto
                if (!firstVisibleOption) {
                    firstVisibleOption = option;
                }
            } else {
                option.style.display = "none"; // Cache l'option
                option.disabled = true;        // Désactive pour éviter la sélection via les flèches du clavier
            }
        }

        // Optionnel : si l'élément actuellement sélectionné finit par être caché par la recherche,
        // on sélectionne automatiquement le premier résultat visible restant.
        if (firstVisibleOption && this.dogSelectTarget.selectedOptions[0]?.style.display === "none") {
            this.dogSelectTarget.value = firstVisibleOption.value;
        }
    }
}
