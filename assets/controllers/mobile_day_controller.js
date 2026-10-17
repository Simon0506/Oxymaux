import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['modal'];

    open(event) {
        if (event) {
            event.preventDefault();
        }
        this.modalTarget.classList.remove('hidden');
        document.body.classList.add('overflow-hidden');
    }

    close(event) {
        event?.stopPropagation();
        this.modalTarget.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }

    closeOnBackdrop(event) {
        if (event.target === event.currentTarget) {
            this.close(event);
        }
    }

    navigate(event) {
        event.stopPropagation();
    }
}
