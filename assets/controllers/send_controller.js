import { Controller } from "@hotwired/stimulus"

export default class extends Controller {

    // Modifier le bouton d'envoi du formulaire pour éviter les envois multiples
    submit(event) {
        const submitButton = event.target.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.disabled = true;
            submitButton.textContent = 'Envoi en cours...';
            submitButton.classList.add('cursor-not-allowed', 'bg-gray-400');
            submitButton.classList.remove('bg-sky-500', 'hover:bg-sky-600');
        }
    }

}