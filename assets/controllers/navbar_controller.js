import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["menuButton", "mobileMenu"]

    toggleMenu() {
        this.mobileMenuTarget.classList.toggle("hidden");
        if (this.mobileMenuTarget.classList.contains("hidden")) {
            this.menuButtonTarget.innerHTML = '<i class="fa-solid fa-bars"></i>';
        } else {
            this.menuButtonTarget.innerHTML = '<i class="fa-solid fa-xmark"></i>';
        }
    }
}
