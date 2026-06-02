import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static values = {
        height: { type: Number, default: 300 },
        menubar: { type: Boolean, default: false },
    };

    connect() {
        this.init();
    }

    init() {
        // sécurité anti double init (Turbo + Stimulus)
        if (this.element.dataset.tinymceInitialized === 'true') {
            return;
        }

        // protection si TinyMCE pas chargé
        if (typeof tinymce === 'undefined') {
            console.error('TinyMCE not loaded');
            return;
        }

        this.element.dataset.tinymceInitialized = 'true';

        tinymce.init({
            target: this.element,

            menubar: this.menubarValue,
            height: this.heightValue,

            plugins: 'lists link image preview code',
            toolbar:
                'undo redo | formatselect | bold italic underline | ' +
                'alignleft aligncenter alignright alignjustify | ' +
                'bullist numlist outdent indent | link image | preview code',

            branding: false,

            setup: (editor) => {
                this.editor = editor;
            }
        });
    }

    disconnect() {
        // important : nettoyage Turbo / navigation back / cache
        if (this.editor) {
            this.editor.remove();
            this.editor = null;
        }
    }
}