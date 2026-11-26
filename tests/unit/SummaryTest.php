<?php

declare(strict_types=1);

/**
 * Summary: plain-text summary from feed HTML.
 * Run: php tests/unit/SummaryTest.php
 */

require __DIR__ . '/../bootstrap.php';

use HKweb\Plugin\Task\Feedcurator\Feed\Summary;

check(
	'footer removed, entities decoded',
	Summary::fromHtml('<p>Een passende plek&nbsp;is niet vanzelfsprekend.</p><p>Het bericht <a href="x">Titel</a> verscheen eerst op <a>Iederin</a>.</p>'),
	'Een passende plek is niet vanzelfsprekend.'
);

$long = trim(str_repeat('woord ', 70));
$cut  = Summary::fromHtml('<p>' . $long . '</p>');
check('long text ends with ellipsis', str_ends_with($cut, '…'), true);
check('long text length', mb_strlen($cut) <= 301, true);
check('cut at a space', str_ends_with($cut, 'woord…'), true);

check('short text unchanged', Summary::fromHtml('<p>Kort.</p>'), 'Kort.');
check('empty', Summary::fromHtml(''), '');
check('whitespace collapsed', Summary::fromHtml("<p>a\n\n  b</p>"), 'a b');
check('custom max', Summary::fromHtml('aaa bbb ccc', 8), 'aaa bbb…');
check('multibyte', mb_strlen(Summary::fromHtml(str_repeat('é', 400))) <= 301, true);

finish('Summary');
