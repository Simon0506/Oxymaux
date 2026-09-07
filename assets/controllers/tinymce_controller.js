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

            // 💡 Options de gratuité totale pour masquer les alertes cloud
            promotion: false,
            branding: false,
            license_key: 'gpl', 

            setup: (editor) => {
                this.editor = editor;
                
                // Optionnel : Forcer la mise à jour du textarea natif quand on tape du texte 
                // (Très utile pour éviter les formulaires soumis "vides" avec Turbo)
                editor.on('change', () => {
                    editor.save();
                });
            }
        });
    }

    disconnect() {
        // important : nettoyage Turbo / navigation back / cache
        if (this.editor) {
            this.editor.remove();
            this.editor = null;
            this.element.dataset.tinymceInitialized = 'false';
        }
    }
}