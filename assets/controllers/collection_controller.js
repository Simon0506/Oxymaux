import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['container'];

    static values = {
        index: Number,
        prototype: String,
    }

    add() {
        const item = document.createElement('div');

        item.innerHTML = this.prototypeValue.replace(
            /__name__/g,
            this.indexValue
        );

        item.childNodes.forEach(child => {
            if (child.nodeType === Node.ELEMENT_NODE) {
                child.classList.add('flex', 'flex-col', 'gap-4', 'flex-1', 'w-full');
                child.childNodes.forEach(grandChild => {
                    if (grandChild.nodeType === Node.ELEMENT_NODE) {
                        grandChild.classList.add('w-full', 'flex', 'gap-6', 'w-full');
                    }
                });
            }
        });
        
        const wrapper = document.createElement('div');

        wrapper.classList.add(
            'tarif-item',
            'bg-white',
            'p-4',
            'rounded',
            'border',
            'border-sky-300',
            'shadow'
        );


        wrapper.innerHTML = `
            <div class="flex gap-4 items-start w-full">

                <button
                    type="button"
                    class="drag-handle mt-10 text-gray-400 hover:text-black cursor-move"
                >
                    ☰
                </button>

                <div class="flex-1 flex flex-col gap-4 w-full">
                    ${item.innerHTML}
                </div>

                <button
                    type="button"
                    data-action="collection#remove"
                    class="mt-10 text-red-500 hover:text-red-700"
                >
                    ✕
                </button>

            </div>
        `;

        this.containerTarget.appendChild(wrapper);

        this.indexValue++;
    }

    remove(event) {
        event.currentTarget.closest('.tarif-item').remove();
    }
}