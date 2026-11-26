<?php

declare(strict_types=1);

/**
 * Splitting the introtext of an imported article into summary, source line and link for the review page.
 * Run: php tests/unit/ReviewArticleTest.php
 */

require __DIR__ . '/../bootstrap.php';

use HKweb\Plugin\System\Feedcurator\Review\ReviewArticle;

$intro = '<p>Heb je een laag inkomen? Dan kun je &amp; wil je de Gemeentepolis gebruiken.</p>'
	. '<p class="feedcurator-source">Bron: Gemeente Valkenswaard, 26 november 2026 — '
	. '<a href="https://www.valkenswaard.nl/polis?a=1&amp;b=2" rel="noopener" target="_blank">Lees het volledige bericht</a></p>';

$parts = ReviewArticle::parse($intro);

check('summary is the text before the source line, entities decoded', $parts['summary'], 'Heb je een laag inkomen? Dan kun je & wil je de Gemeentepolis gebruiken.');
check('source is the text of the source line without the link and dash', $parts['source'], 'Bron: Gemeente Valkenswaard, 26 november 2026');
check('link is the decoded href', $parts['link'], 'https://www.valkenswaard.nl/polis?a=1&b=2');
check('link text', $parts['linkText'], 'Lees het volledige bericht');

check('a source ending in an accented letter keeps it', ReviewArticle::parse('<p>x</p><p class="feedcurator-source">Bron: Café — <a href="https://e.test/">y</a></p>')['source'], 'Bron: Café');

$plain = ReviewArticle::parse('<p>Alleen <strong>tekst</strong>.</p>');

check('no source line: summary is the whole text', $plain['summary'], 'Alleen tekst.');
check('no source line: empty source', $plain['source'], '');
check('no source line: empty link', $plain['link'], '');

$evil = ReviewArticle::parse('<p>X<script>alert(1)</script></p><p class="feedcurator-source">Bron: Y — <a href="javascript:alert(1)">klik</a></p>');

check('tags are stripped from the summary', $evil['summary'], 'Xalert(1)');
check('a non-http link is dropped', $evil['link'], '');

check('empty introtext', ReviewArticle::parse(''), ['summary' => '', 'source' => '', 'link' => '', 'linkText' => '']);

finish('ReviewArticle');
