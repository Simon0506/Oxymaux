import { Controller } from "@hotwired/stimulus"

export default class extends Controller {
    static targets = ["age"]

    connect() {
        this.updateAge()
    }

    updateAge() {
        const birthDate = new Date("1998-03-18")
        const currentDate = new Date()
        let age = currentDate.getFullYear() - birthDate.getFullYear()
        const monthDifference = currentDate.getMonth() - birthDate.getMonth()
        if (monthDifference < 0 || (monthDifference === 0 && currentDate.getDate() < birthDate.getDate())) {
            age--
        }
        this.ageTarget.textContent = age
    }
}
