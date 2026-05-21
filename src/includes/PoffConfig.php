<?php
/**
 * PoffConfig
 * Model/utility for reading or creating a poff.config.json file
 * with lightweight folder metadata and a first-level tree listing.
 */

require_once __DIR__ . '/PoffConfig/bootstrap.php';

class PoffConfig
{
    private const DEFAULT_LAYOUT_FOLDER = '.layout';
    private const EDIT_ONLY_TREE_ENTRIES = ['.layout'];
    private const LAYOUT_TEMPLATE_FILE = 'template.hbs';
    private const LAYOUT_STYLE_FILE = 'style.css';
    private const LAYOUT_SCRIPT_FILE = 'script.js';
    private const WORK_SECTION_TEMPLATE_FILE = 'work.hbs';
    private const WORKS_SECTION_TEMPLATE_FILE = 'works.hbs';

    use PoffConfigLayoutHelpers;
    use PoffConfigCoreHelpers;
    use PoffConfigLayoutFileHelpers;
    use PoffConfigLayoutViewHelpers;
    use PoffConfigLayoutCollectionHelpers;
    use PoffConfigPromptHelpers;
}
