import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal', 'reasonWrapper', 'reasonSelect'];

    openModal() {
        this.modalTarget.classList.remove('hidden');
    }

    cancel() {
        this.modalTarget.classList.add('hidden');
    }

    toggleUnlock(event) {
        const isChecked = event.target.checked;
        if (isChecked) {
            this.reasonWrapperTarget.classList.add('hidden');
            this.reasonSelectTarget.removeAttribute('name');
        } else {
            this.reasonWrapperTarget.classList.remove('hidden');
            this.reasonSelectTarget.setAttribute('name', 'type');
        }
    }
}