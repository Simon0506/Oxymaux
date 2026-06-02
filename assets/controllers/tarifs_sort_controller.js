import { Controller } from '@hotwired/stimulus';
import Sortable from 'sortablejs';

export default class extends Controller {
    static targets = ['container'];

    connect() {
        this.sortable = Sortable.create(this.containerTarget, {
            animation: 150,
            handle: '.drag-handle',

            draggable: '.tarif-item', // 🔥 OBLIGATOIRE

            ghostClass: 'opacity-50',

            onEnd: () => {
                this.updatePositions();
            }
        });
    }

    updatePositions() {
        const items = this.containerTarget.querySelectorAll('.tarif-item');

        items.forEach((item, index) => {
            const input = item.querySelector('input[name*="[position]"]');

            if (input) {
                input.value = index + 1;
            }
        });
    }
}