import { Controller } from '@hotwired/stimulus'

export default class extends Controller {

    static targets = ['input', 'filename']

    updateFilename() {

        const file = this.inputTarget.files[0]

        this.filenameTarget.textContent =
            file ? file.name : 'Aucun fichier sélectionné'
    }

    submitForm() {
        this.element.requestSubmit()
    }
}