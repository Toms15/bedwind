/**
 * ACF Color Picker Restriction
 * Limita il color picker ACF alla palette personalizzata
 */
(function($) {
    'use strict';

    // Assicurati che ACF sia caricato
    if (typeof acf === 'undefined') {
        return;
    }

    /**
     * Ottieni la palette dai colori localizzati
     */
    function getMasterPalette() {
        if (typeof acfColorPalette !== 'undefined' && acfColorPalette.colors) {
            return acfColorPalette.colors;
        }

        // Fallback palette
        return [
            '#3B82F6', '#1E40AF', '#EF4444', '#10B981',
            '#F59E0B', '#8B5CF6', '#111827', '#6B7280',
            '#F9FAFB', '#FFFFFF'
        ];
    }

    /**
     * Applica la restrizione della palette al color picker
     */
    acf.add_filter('color_picker_args', function(args, field) {
        args.palettes = getMasterPalette();
        args.hide = true; // Nasconde il color picker principale
        args.width = 255;
        args.mode = 'hsl';

        return args;
    });

})(jQuery);
