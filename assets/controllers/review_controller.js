import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["comment", "toggle"]

    maxLength = 200

    connect() {
        this.fullText = this.commentTarget.textContent.trim();

        if (this.fullText.length > this.maxLength) {
            this.toggleTarget.classList.remove("hidden");
            this.truncate();
        }

    }

    truncate() {
        this.commentTarget.textContent =
            this.fullText.slice(0, this.maxLength) + "...";

        this.toggleTarget.textContent = "Voir plus...";
        this.expanded = false;
    }

    expand() {
        this.commentTarget.textContent = this.fullText;

        this.toggleTarget.textContent = "Voir moins...";
        this.expanded = true;
    }

    toggle() {
        if (this.expanded) {
            this.truncate();
        } else {
            this.expand();
        }
    }
}