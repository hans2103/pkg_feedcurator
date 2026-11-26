<?php

declare(strict_types=1);

/**
 * ArticleMapper: FeedItem to article fields.
 * Run: php tests/unit/ArticleMapperTest.php
 */

require __DIR__ . '/../bootstrap.php';

use HKweb\Plugin\Task\Feedcurator\Feed\ArticleMapper;
use HKweb\Plugin\Task\Feedcurator\Feed\FeedItem;
use HKweb\Plugin\Task\Feedcurator\Feed\Source;

$source = new Source('Ieder(in)', 'https://example.org/feed', false, [], [], true);
$line   = '<p class="feedcurator-source">Bron: <a href="https://example.org/a">Voorbeeld</a></p>';
$date   = new DateTimeImmutable('2026-11-25 10:45:33', new DateTimeZone('UTC'));
$mapper = new ArticleMapper();

function make(string $title, string $description, ?DateTimeImmutable $published): FeedItem
{
	return new FeedItem('g', 'https://example.org/a', $title, $description, [], $published);
}

$a = $mapper->map(make('Nieuwe regeling', '<p>Een korte &amp; <b>heldere</b> uitleg.</p>', $date), $source, $line);
check('introtext', $a['introtext'], '<p>Een korte &amp; heldere uitleg.</p>' . $line);
check('title', $a['title'], 'Nieuwe regeling');
check('metadesc short', $a['metadesc'], 'Een korte & heldere uitleg.');
check('created', $a['created'], '2026-11-25 10:45:33');
check('note', $a['note'], 'feedcurator:Ieder(in)');

$a = $mapper->map(make('x', '<p>Een < b</p>', $date), $source, $line);
check('literal < is escaped', $a['introtext'], '<p>Een &lt; b</p>' . $line);

$a = $mapper->map(make('x', '', $date), $source, $line);
check('empty summary: only source line', $a['introtext'], $line);
check('empty summary: empty metadesc', $a['metadesc'], '');

$a = $mapper->map(make('x', '<p>' . trim(str_repeat('woord ', 100)) . '</p>', $date), $source, $line);
check('metadesc max 160', mb_strlen($a['metadesc']) <= 160, true);
check('metadesc cut with ellipsis', str_ends_with($a['metadesc'], '…'), true);
check('introtext summary max 300', mb_strlen(strip_tags($a['introtext'])) <= 300 + mb_strlen(strip_tags($line)) + 1, true);

$a = $mapper->map(make('<script>alert(1)</script>Titel &amp; meer', '', $date), $source, $line);
check('title tags stripped', $a['title'], 'alert(1)Titel & meer');

$local = new DateTimeImmutable('2026-11-25 11:45:33', new DateTimeZone('Europe/Amsterdam'));
check('created converted to UTC', $mapper->map(make('x', '', $local), $source, $line)['created'], '2026-11-25 10:45:33');

$a = $mapper->map(make('x', '', null), $source, $line);
check('created without date is current UTC', abs(strtotime($a['created'] . ' UTC') - time()) < 5, true);

$long = new Source(str_repeat('a', 300), 'https://example.org/feed', false, [], [], true);
check('note max 255', mb_strlen($mapper->map(make('x', '', $date), $long, $line)['note']), 255);

finish('ArticleMapper');
