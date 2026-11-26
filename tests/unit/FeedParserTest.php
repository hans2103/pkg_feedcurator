<?php

declare(strict_types=1);

/**
 * FeedParser and FeedItem: RSS 2.0 and Atom, dates, keys, invalid XML and XXE.
 * Run: php tests/unit/FeedParserTest.php
 */

require __DIR__ . '/../bootstrap.php';

use HKweb\Plugin\Task\Feedcurator\Feed\FeedItem;
use HKweb\Plugin\Task\Feedcurator\Feed\FeedParser;
use HKweb\Plugin\Task\Feedcurator\Feed\FeedUnavailableException;

$parser = new FeedParser;

// WordPress RSS.
$items = $parser->parse(fixture('wordpress.xml'));
check('rss item count', \count($items), 2);
check('title entities decoded', $items[0]->title, 'Fietsers & voetgangers krijgen meer ruimte');
check('link decoded once', $items[0]->link, 'https://example.org/fietsers-voetgangers/?a=1&b=2');
check('guid', $items[0]->guid, 'https://example.org/?p=101');
check('categories', $items[0]->categories, ['Onderwijs']);
check('multiple categories', $items[1]->categories, ['Vervoer', 'Onderwijs']);
check('published in UTC', $items[0]->published?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), '2026-11-25 10:45:33');
check('published with offset', $items[1]->published?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), '2026-11-24 07:00:00');
check('description keeps html', str_starts_with($items[0]->description, '<p>Een verzonnen samenvatting.</p>'), true);
check('description keeps footer', str_contains($items[0]->description, 'verscheen eerst op'), true);

// Municipality RSS: guid is a URL, items without title or without link and guid are skipped.
$items = $parser->parse(fixture('municipality.xml'));
check('invalid items skipped', \count($items), 1);
check('municipality link', $items[0]->link, 'https://gemeente.example.net/nieuws/parkeerregels');
check('municipality categories', $items[0]->categories, []);

// Atom.
$items = $parser->parse(fixture('atom.xml'));
check('atom item count', \count($items), 2);
check('atom link from rel=alternate', $items[0]->link, 'https://example.com/wandelen');
check('atom guid from id', $items[0]->guid, 'tag:example.com,2026:wandelen');
check('atom category term', $items[0]->categories, ['Sport']);
check('atom summary html decoded', $items[0]->description, '<p>Een verzonnen wandeling.</p>');
check('atom date from updated', $items[0]->published?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), '2026-11-25 08:00:00');
check('atom link without rel', $items[1]->link, 'https://example.com/open-dag');
check('atom published preferred', $items[1]->published?->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), '2026-11-20 12:00:00');

// Key: normalised link, falling back to the guid.
$a = new FeedItem('g1', 'https://Example.org/a#top', 'A', '', [], null);
$b = new FeedItem('g2', 'https://example.org/a', 'B', '', [], null);
check('key ignores case of host and fragment', $a->key(), $b->key());
check('key length', \strlen($a->key()), 64);
$c = new FeedItem('g3', '', 'C', '', [], null);
check('key falls back to guid', $c->key(), hash('sha256', 'g3'));
check('key differs for another path', (new FeedItem('g1', 'https://example.org/b', 'A', '', [], null))->key() === $b->key(), false);

// Not a feed (an error or maintenance page).
check('html throws FeedUnavailableException', thrown(static fn () => $parser->parse('<html>Onderhoud</html>')), FeedUnavailableException::class);
check('garbage throws FeedUnavailableException', thrown(static fn () => $parser->parse('Service unavailable')), FeedUnavailableException::class);

// External entities are not loaded (XXE).
$xml = '<?xml version="1.0"?><!DOCTYPE r [<!ENTITY x SYSTEM "file:///etc/passwd">]>'
	. '<rss><channel><item><title>T</title><link>https://example.org/x</link><description>&x;</description></item></channel></rss>';
$result = $parser->parse($xml);
check('external entities not loaded', str_contains($result[0]->description ?? '', 'root:'), false);

finish('FeedParser');
