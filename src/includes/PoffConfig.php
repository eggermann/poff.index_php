<?php
/**
 * PoffConfig
 * Model/utility for reading or creating a poff.config.json file
 * with lightweight folder metadata and a first-level tree listing.
 */

require_once __DIR__ . '/project-root.php';
require_once __DIR__ . '/PoffConfig/layout-helpers.php';
require_once __DIR__ . '/PoffConfig/core-helpers.php';
require_once __DIR__ . '/PoffConfig/layout-files.php';
require_once __DIR__ . '/PoffConfig/layout-view.php';
require_once __DIR__ . '/PoffConfig/layout-collections.php';
require_once __DIR__ . '/PoffConfig/layout-persistence.php';
require_once __DIR__ . '/PoffConfig/prompt-helpers.php';
require_once __DIR__ . '/prompt-template-sanitize.php';
require_once __DIR__ . '/viewer/link-targets.php';

class PoffConfig
{
    private const DEFAULT_LAYOUT_FOLDER = '.layout';
    private const EDIT_ONLY_TREE_ENTRIES = ['.layout', '.htaccess'];
    private const LAYOUT_TEMPLATE_FILE = 'template.hbs';
    private const LAYOUT_STYLE_FILE = 'style.css';
    private const LAYOUT_SCRIPT_FILE = 'script.js';
    private const WORK_SECTION_TEMPLATE_FILE = 'work.hbs';
    private const WORKS_SECTION_TEMPLATE_FILE = 'works.hbs';

    use PoffConfigLayoutHelpers;
    use PoffConfigCoreHelpers;
    use PoffConfigLayoutPersistenceHelpers;
    use PoffConfigPromptHelpers;
}
