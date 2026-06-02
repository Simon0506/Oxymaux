import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        if (this.element.querySelectorAll('ul').length > 0) {
            this.element.querySelectorAll('ul').forEach(ul => {
                ul.classList.add('list-disc', 'list-inside', 'ml-6');
            });
        }
    }
}