import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['categorySelect', 'newCategoryInput', 'buttonAddCategory'];

    showNewCategoryInput() {
        this.newCategoryInputTarget.classList.remove('hidden');
        this.categorySelectTarget.disabled = true;
        this.categorySelectTarget.classList.remove('bg-white');
        this.categorySelectTarget.classList.add('bg-gray-200', 'cursor-not-allowed');
        this.buttonAddCategoryTarget.classList.add('hidden');
    }

    cancelNewCategory() {
        this.newCategoryInputTarget.classList.add('hidden');
        this.categorySelectTarget.disabled = false;
        this.categorySelectTarget.classList.add('bg-white');
        this.categorySelectTarget.classList.remove('bg-gray-200', 'cursor-not-allowed');
        this.buttonAddCategoryTarget.classList.remove('hidden');
    }
}
