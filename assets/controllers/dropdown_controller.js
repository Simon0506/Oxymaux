import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ['menu', 'arrow'];

    // 1. Version Bureau : Fade & Scale Flottant
    toggle() {
        this.menuTarget.classList.toggle('opacity-0');
        this.menuTarget.classList.toggle('opacity-100');
        this.menuTarget.classList.toggle('scale-95');
        this.menuTarget.classList.toggle('scale-100');
        this.menuTarget.classList.toggle('pointer-events-none');

        if (this.hasArrowTarget) {
            this.arrowTarget.classList.toggle('rotate-180');
        }
    }

    // 2. Version Mobile : Accordéon Coulissant (Slide)
    toggleAccordion() {
        const isOpen = this.menuTarget.style.height !== '0px' && this.menuTarget.style.height !== '';

        if (isOpen) {
            this.menuTarget.style.height = '0px';
            this.menuTarget.style.opacity = '0';
        } else {
            // Calcule la hauteur exacte requise par les liens enfants
            this.menuTarget.style.height = `${this.menuTarget.scrollHeight}px`;
            this.menuTarget.style.opacity = '1';
        }

        if (this.hasArrowTarget) {
            this.arrowTarget.classList.toggle('rotate-180');
        }
    }
}