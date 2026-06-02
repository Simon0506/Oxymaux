import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card', 'serviceSelect', 'dateInput'];

    filter() {
        // 1. Récupération des critères de recherche
        const selectedService = this.serviceSelectTarget.value;
        const selectedDate = this.dateInputTarget.value; // Renvoie une string "YYYY-MM-DD" ou ""

        this.cardTargets.forEach(card => {
            // 2. Extraction des données de la carte actuelle
            const cardService = card.dataset.service;
            const cardDate = card.dataset.date;

            // 3. Vérification des conditions (Vrai si "all/vide" OU si correspondance exacte)
            const matchesService = (selectedService === 'all' || cardService === selectedService);
            const matchesDate = (selectedDate === '' || cardDate === selectedDate);

            // 4. On affiche la carte uniquement si elle remplit les DEUX conditions
            if (matchesService && matchesDate) {
                card.classList.remove('hidden');
            } else {
                card.classList.add('hidden');
            }
        });
    }

    // Petit helper pour réinitialiser le filtre de date au clic sur la croix
    clearDate() {
        this.dateInputTarget.value = '';
        this.filter(); // Relance le filtrage global
    }
}