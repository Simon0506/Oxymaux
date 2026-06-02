import { Controller } from '@hotwired/stimulus';

export default class extends Controller {

    static targets = ['pending'];

    connect() {
        if (this.hasPendingTarget) {
            this.cancelReservation();
        }
    }

    cancelReservation() {
        const reservationId = this.pendingTarget.dataset.autoCancelReservationId;

        try {
            fetch(`/reservation/${reservationId}/auto-cancel`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            });
        } catch (error) {
            console.error('Erreur lors de l\'annulation automatique de la réservation :', error);
        }
        location.reload();
    }
}

