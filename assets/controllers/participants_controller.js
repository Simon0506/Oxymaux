import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["header", "list"];

    toggle() {
        this.listTarget.classList.toggle("hidden");
        if (this.headerTarget.classList.contains("fa-chevron-down")) {
            this.headerTarget.classList.remove("fa-chevron-down");
            this.headerTarget.classList.add("fa-chevron-up");
        } else {
            this.headerTarget.classList.remove("fa-chevron-up");
            this.headerTarget.classList.add("fa-chevron-down");
        }
    }
}