import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal'];

    openModal() {
        this.modalTarget.classList.remove('hidden');
    }

    cancel() {
        this.modalTarget.classList.add('hidden');
    }
}