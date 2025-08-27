<?php

namespace App;

/**
 * ACF Color Picker Restriction
 * Limita il color picker ACF a una palette personalizzata
 */
class ACF_Color_Picker_Restriction
{
    private static $instance = null;
    private $color_palette;

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        // Definisci la tua palette di colori (da Tailwind o custom)
        $this->color_palette = [
            '#CBE0EF',
            '#F5EB78',
            '#F864F8',
            '#78EB78',
            '#000000',
            '#0D0D0D',
            '#292929',
            '#424242',
            '#5E5E5E',
            '#787878',
            '#949494',
            '#ADADAD',
            '#C9C9C9',
            '#E3E3E3',
            '#FFFFFF',
        ];

        add_action('acf/input/admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Carica CSS e JS per la restrizione del color picker
     */
    public function enqueue_assets()
    {
        // Localizza la palette per JavaScript
        wp_add_inline_script('jquery', '
        window.acfColorPalette = '.json_encode(['colors' => $this->color_palette]).';
    ', 'before');

        // Usa Vite per caricare gli asset con hash
        add_action('admin_footer', function () {
            ?>
            <script type="module"
                    src="<?php echo get_template_directory_uri(); ?>/resources/js/acf-color-picker.js"></script>
            <link rel="stylesheet"
                  href="<?php echo get_template_directory_uri(); ?>/resources/css/acf-color-picker.css">
            <?php
        });
    }

    /**
     * Ottieni la palette di colori del tema da theme.json
     */
    private function get_theme_colors()
    {
        $theme_json = get_theme_support('editor-color-palette');
        $colors = [];

        if ($theme_json && is_array($theme_json[0])) {
            foreach ($theme_json[0] as $color) {
                if (isset($color['color'])) {
                    $colors[] = $color['color'];
                }
            }
        }

        return $colors;
    }

    /**
     * Aggiungi colori dal theme.json alla palette
     */
    public function include_theme_colors()
    {
        $theme_colors = $this->get_theme_colors();
        $this->color_palette = array_merge($this->color_palette, $theme_colors);
        $this->color_palette = array_unique($this->color_palette);
    }
}

// Inizializza solo se ACF è attivo
add_action('init', function () {
    if (function_exists('acf_register_field_type')) {
        ACF_Color_Picker_Restriction::getInstance();
    }
});
