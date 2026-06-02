import { Controller } from '@hotwired/stimulus';
import html2canvas from 'html2canvas';

export default class extends Controller {

    static targets = ['planning', 'header', 'back'];

    static values = {
        month: String
    }

    async download() {
        // On ne garde que les modifications de structure/layout nécessaires
        this.headerTarget.classList.add('justify-center');
        this.headerTarget.classList.remove('justify-between');
        this.planningTarget.classList.add('exporting');

        try {
            const canvas = await html2canvas(this.planningTarget, {
                scale: 2,
                useCORS: true,
                backgroundColor: null,
                
                // Centralisation de toutes les optimisations de masquage
                ignoreElements: (element) => {
                    // 1. Ignorer le bouton "back"
                    const isBackTarget = element.getAttribute('data-planning-export-target') === 'back';
                    
                    // 2. Ignorer les liens (<a>) qui se trouvent dans le header
                    const isHeaderLink = element.tagName === 'A' && this.headerTarget.contains(element);
                    
                    // 3. Ignorer les éléments avec la classe 'absolute'
                    const isAbsoluteTag = element.classList.contains('absolute');

                    // Si l'une de ces conditions est vraie, html2canvas ignore l'élément
                    return isBackTarget || isHeaderLink || isAbsoluteTag;
                }
            });

            const link = document.createElement('a');
            const filename = `planning-${this.monthValue}.png`;
            link.download = filename;
            link.href = canvas.toDataURL('image/png');
            link.click();

        } finally {
            // Le bloc finally est maintenant ultra léger !
            this.headerTarget.classList.remove('justify-center');
            this.headerTarget.classList.add('justify-between');
            this.planningTarget.classList.remove('exporting');
        }
    }
}