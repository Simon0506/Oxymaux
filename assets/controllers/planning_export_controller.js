import { Controller } from '@hotwired/stimulus';
import html2canvas from 'html2canvas';

export default class extends Controller {

    // On remplace 'back' par 'notExported'
    static targets = ['planning', 'header', 'notExported'];

    static values = {
        month: String
    }

    async download() {
        // On adapte le layout pour recentrer le titre du mois
        this.headerTarget.classList.add('justify-center');
        this.headerTarget.classList.remove('justify-between');
        this.planningTarget.classList.add('exporting');

        try {
            const canvas = await html2canvas(this.planningTarget, {
                scale: 2,
                useCORS: true,
                backgroundColor: null,
                
                // Utilisation simplifiée grâce au multi-target de Stimulus
                ignoreElements: (element) => {
                    // Si l'élément inspecté fait partie de tes "notExportedTargets", on l'ignore
                    return this.notExportedTargets.includes(element);
                }
            });

            const link = document.createElement('a');
            const filename = `planning-${this.monthValue}.png`;
            link.download = filename;
            link.href = canvas.toDataURL('image/png');
            link.click();

        } finally {
            // Restauration du layout initial
            this.headerTarget.classList.remove('justify-center');
            this.headerTarget.classList.add('justify-between');
            this.planningTarget.classList.remove('exporting');
        }
    }
}