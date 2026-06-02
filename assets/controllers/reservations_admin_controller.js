import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static targets = ["date"];

    viewDay(event) {
        const date = event.currentTarget.dataset.reservationsAdminValue;
        this.dateTarget.value = date;
        this.dateTarget.form.submit();
    }
}
