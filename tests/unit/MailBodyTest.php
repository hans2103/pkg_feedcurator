<?php

declare(strict_types=1);

/**
 * The notification mail body in HTML and plain text.
 * Run: php tests/unit/MailBodyTest.php
 */

require __DIR__ . '/../bootstrap.php';

use HKweb\Plugin\Task\Feedcurator\Mail\MailBody;

$items = [
	['title' => 'Nieuw <b>loket</b> & meer', 'source' => 'Gemeente Valkenswaard', 'url' => 'https://example.org/index.php?a=1&u=2&s=x'],
	['title' => 'Tweede bericht', 'source' => 'Platform', 'url' => 'https://example.org/index.php?a=3&u=2&s=y'],
];

$html = MailBody::html('Er zijn <nieuwe> artikelen.', $items, 'Beoordeel');
$text = MailBody::text('Er zijn nieuwe artikelen.', $items, 'Beoordeel');

check('html escapes a tag in the title', str_contains($html, 'Nieuw &lt;b&gt;loket&lt;/b&gt; &amp; meer'), true);
check('html contains no raw <b>', str_contains($html, '<b>'), false);
check('html escapes the intro', str_contains($html, 'Er zijn &lt;nieuwe&gt; artikelen.'), true);
check('html has one li per item', substr_count($html, '<li>'), 2);
check('html links to the url with an escaped ampersand', str_contains($html, '<a href="https://example.org/index.php?a=1&amp;u=2&amp;s=x">Beoordeel</a>'), true);
check('html shows the source', str_contains($html, 'Gemeente Valkenswaard'), true);

check('text has the url on its own line', in_array('https://example.org/index.php?a=1&u=2&s=x', explode("\n", $text), true), true);
check('text has the second url on its own line', in_array('https://example.org/index.php?a=3&u=2&s=y', explode("\n", $text), true), true);
check('text keeps the title unescaped', str_contains($text, 'Nieuw <b>loket</b> & meer'), true);
check('text starts with the intro', str_starts_with($text, 'Er zijn nieuwe artikelen.'), true);

finish('MailBody');
