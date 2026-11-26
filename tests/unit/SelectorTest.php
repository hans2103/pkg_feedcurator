<?php

declare(strict_types=1);

/**
 * Which items an import run picks: look-back, order, duplicates and invalid items.
 * Run: php tests/unit/SelectorTest.php
 */

require __DIR__ . '/../bootstrap.php';

use HKweb\Plugin\Task\Feedcurator\Feed\ArticleMapper;
use HKweb\Plugin\Task\Feedcurator\Feed\FeedItem;
use HKweb\Plugin\Task\Feedcurator\Feed\Source;
use HKweb\Plugin\Task\Feedcurator\Import\Selector;

$utc = new DateTimeZone('UTC');
$now = new DateTimeImmutable('2026-11-26 12:00:00', $utc);
$src = new Source('A', 'https://a.example/feed', true, [], [], true);
$b   = new Source('B', 'https://b.example/feed', true, [], [], true);

function item(string $n, string $date, string $title = '', string $link = ''): FeedItem
{
	return new FeedItem('g' . $n, $link !== '' ? $link : 'https://x.example/' . $n, $title !== '' ? $title : 'Titel ' . $n, '', [], new DateTimeImmutable($date, new DateTimeZone('UTC')));
}

function titles(array $rows): array
{
	return array_map(static fn (array $r): string => $r['item']->guid, $rows);
}

// Oldest first, and the maximum counts only regular items.
$s = new Selector($now, new ArticleMapper);
$s->add(item('3', '2026-11-03'), $src, false);
$s->add(item('1', '2026-11-01'), $src, false);
$s->add(item('2', '2026-11-02'), $src, false);
check('oldest first, limited to max', titles($s->select([], 2)), ['g1', 'g2']);

// Look-back items are exempt from the maximum, regular ones still limited.
$s = new Selector($now, new ArticleMapper);
$s->add(item('1', '2026-10-01'), $src, true);
$s->add(item('2', '2026-10-02'), $src, true);
$s->add(item('3', '2026-10-03'), $src, true);
$s->add(item('4', '2026-11-04'), $b, false);
$s->add(item('5', '2026-11-05'), $b, false);
check('look-back exempt from max', titles($s->select([], 1)), ['g1', 'g2', 'g3', 'g4']);

// The same message from a look-back source and a regular one stays a look-back item.
$s = new Selector($now, new ArticleMapper);
$s->add(item('1', '2026-11-01'), $b, false);
$s->add(item('9', '2026-11-09'), $b, false);
$s->add(item('1', '2026-11-01'), $src, true);
check('dedup keeps look-back flag', titles($s->select([], 0 + 1)), ['g1', 'g9']);
check('one candidate per key', \count($s->keys()), 2);

// Known keys are dropped and counted.
$s = new Selector($now, new ArticleMapper);
$a = item('1', '2026-11-01');
$s->add($a, $src, false);
$s->add(item('2', '2026-11-02'), $src, false);
check('known dropped', titles($s->select([$a->key() => true], 5)), ['g2']);
check('known counted', $s->known, 1);

// Invalid items never take a slot.
$s = new Selector($now, new ArticleMapper);
check('no link rejected', $s->add(item('1', '2026-11-01', '', 'javascript:alert(1)'), $src, false), false);
check('empty title rejected', $s->add(item('2', '2026-11-02', '<b></b>'), $src, false), false);
$s->add(item('3', '2026-11-03'), $src, false);
check('invalid items do not take a slot', titles($s->select([], 1)), ['g3']);
check('invalid counted', $s->invalid, 2);

finish('Selector');
