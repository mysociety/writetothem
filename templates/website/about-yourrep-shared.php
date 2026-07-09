<?php

require_once __DIR__ . '/about-markdown.php';

/**
 * Load a yourrep markdown file from templates/website/yourrep-md.
 * Language-aware with an English fallback (see render_md).
 */
function yourrep_md($filename) {
    return render_md($filename, 'yourrep-md');
}

?>
