<?php

declare(strict_types=1);

/**
 * Which requests stay out of System - Page Cache: only the review page.
 * Run: php tests/unit/PageCachePolicyTest.php
 */

require __DIR__ . '/../bootstrap.php';

use HKweb\Plugin\System\Feedcurator\Review\PageCachePolicy;

check('review page is excluded', PageCachePolicy::isExcluded('com_ajax', 'feedcurator'), true);
check('other ajax plugin is not excluded', PageCachePolicy::isExcluded('com_ajax', 'other'), false);
check('other component is not excluded', PageCachePolicy::isExcluded('com_content', 'feedcurator'), false);
check('no parameters is not excluded', PageCachePolicy::isExcluded(null, null), false);
check('case differs is not excluded', PageCachePolicy::isExcluded('com_ajax', 'Feedcurator'), false);

finish('PageCachePolicy');
