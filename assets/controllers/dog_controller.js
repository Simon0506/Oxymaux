import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['button', 'form', 'actualValue'];

    addDog() {
        this.formTarget.classList.remove('hidden');
        this.buttonTarget.classList.add('hidden');
    }

    cancelAdd() {
        this.formTarget.classList.add('hidden');
        this.buttonTarget.classList.remove('hidden');
    }

    toggle() {
        this.formTarget.classList.toggle('hidden');
        this.actualValueTarget.classList.toggle('hidden');
        this.buttonTarget.classList.toggle('hidden');
    }
}