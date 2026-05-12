<?php
/**
 * Worktype helper for media layout defaults and overrides.
 */

require_once __DIR__ . '/../../vendor/autoload.php';
require_once dirname(__DIR__, 2) . '/src/includes/Worktype/State.php';
require_once dirname(__DIR__, 2) . '/src/includes/Worktype/Definitions.php';
require_once dirname(__DIR__, 2) . '/src/includes/Worktype/Context.php';
require_once dirname(__DIR__, 2) . '/src/includes/Worktype/Layout.php';
require_once dirname(__DIR__, 2) . '/src/includes/Worktype/Render.php';

class Worktype
{
    use WorktypeStateTrait;
    use WorktypeDefinitionsTrait;
    use WorktypeContextTrait;
    use WorktypeLayoutTrait;
    use WorktypeRenderTrait;
}
