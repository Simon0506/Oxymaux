import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['service', 'date'];

    static values = {
        activities: Object
    }

    connect() {
        this.updateUpcomingActivity();
    }

    updateUpcomingActivity() {
        const serviceId = this.serviceTarget.value;

        const upcomingDate = this.activitiesValue[serviceId];

        if (upcomingDate) {
            this.dateTarget.textContent = upcomingDate;
        } else {
            this.dateTarget.textContent = 'Aucune date à venir';
        }
    }
}