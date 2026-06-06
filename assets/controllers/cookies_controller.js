import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['banner'];

    connect() {
        if (!this.hasConsented()) {
            this.showBanner();
        }
    }

    accept() {
        localStorage.setItem('cookies_consent', 'true');
        this.hideBanner();
    }

    hasConsented() {
        return localStorage.getItem('cookies_consent') === 'true';
    }

    showBanner() {
        this.bannerTarget.classList.remove('opacity-0', 'translate-y-4');
        this.bannerTarget.classList.add('opacity-100', 'translate-y-0');
    }

    hideBanner() {
        this.bannerTarget.classList.remove('opacity-100', 'translate-y-0');
        this.bannerTarget.classList.add('opacity-0', 'translate-y-4');
    }
}