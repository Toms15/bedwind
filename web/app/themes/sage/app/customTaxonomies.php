<?php

require_once ABSPATH.'wp-admin/includes/file.php';
$customPostTypes = get_template_directory().'/app/CustomTaxonomies';
$customPostTypes = list_files($customPostTypes);
if (! empty($customPostTypes)) {
    foreach ($customPostTypes as $singleCustomPostType) {
        if (str_ends_with($singleCustomPostType, '.php')) {
            include $singleCustomPostType;
        }
    }
}
