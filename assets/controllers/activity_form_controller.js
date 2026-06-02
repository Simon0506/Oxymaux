import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['nbPlaces', 'openToAll'];

    connect() {
        this.toggle();
    }

    toggle() {
        if (this.openToAllTarget.checked) {
            this.nbPlacesTarget.value = null;
            this.nbPlacesTarget.disabled = true;
            this.nbPlacesTarget.classList.add('hidden');
        } else {
            this.nbPlacesTarget.disabled = false;
            this.nbPlacesTarget.classList.remove('hidden');
        }
    }
}
