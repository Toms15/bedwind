<?php

/**
 * Theme helpers.
 */

function custom_color_radio_field($field) {
    $field['choices'] = array(
        '#FF0000' => 'Rosso',
        '#00FF00' => 'Verde',
        '#0000FF' => 'Blu',
        '#FFFF00' => 'Giallo'
    );
    return $field;
}
add_filter('acf/load_field/name=prova_colore', 'custom_color_radio_field');

function custom_acf_color_choices($field) {
    $field['choices'] = array(
        '#FF0000' => 'Rosso',
        '#00FF00' => 'Verde',
        '#0000FF' => 'Blu',
        '#FFFF00' => 'Giallo',
        '#FF00FF' => 'Magenta',
        '#00FFFF' => 'Ciano'
    );
    return $field;
}
add_filter('acf/load_field/name=prova_colore_select', 'custom_acf_color_choices');
