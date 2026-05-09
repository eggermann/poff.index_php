<?php

require_once __DIR__ . '/layout-files.php';
require_once __DIR__ . '/layout-view.php';
require_once __DIR__ . '/layout-collections.php';

trait PoffConfigLayoutPersistenceHelpers
{
    use PoffConfigLayoutFileHelpers;
    use PoffConfigLayoutViewHelpers;
    use PoffConfigLayoutCollectionHelpers;
}
