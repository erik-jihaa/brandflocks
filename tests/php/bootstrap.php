<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

/** HBA replay */
require_once $root . '/web/api/hba-prompt.php';
require_once $root . '/web/api/hba-repository.php';
require_once $root . '/scripts/hba-v1.12-replay-lib.php';

/** Campagne planner (templates + channel normalisatie) */
require_once $root . '/web/api/campagne-preview-templates.php';
require_once $root . '/web/api/campagne-preview-repository.php';

/** Minder ruis in CLI-tests (PHP 8.4+ deprecations in ongebruikte requires). */
error_reporting(E_ALL & ~E_DEPRECATED);
