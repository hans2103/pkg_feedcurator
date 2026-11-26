<?php

declare(strict_types=1);

/**
 * KeywordFilter and Source: term lists, word boundaries, wildcards, excludes, Unicode.
 * Run: php tests/unit/KeywordFilterTest.php
 */

require __DIR__ . '/../bootstrap.php';

use HKweb\Plugin\Task\Feedcurator\Feed\FeedItem;
use HKweb\Plugin\Task\Feedcurator\Feed\KeywordFilter;
use HKweb\Plugin\Task\Feedcurator\Feed\Source;

function item(string $title, string $description = '', array $categories = []): FeedItem
{
	return new FeedItem('g', 'https://example.org/a', $title, $description, $categories, null);
}

check('parseList trims, drops empties and duplicates', KeywordFilter::parseList("Wmo\n  toegankelijk* \n\nWmo\r\nWMO"), ['Wmo', 'toegankelijk*']);
check('parseList empty', KeywordFilter::parseList(''), []);

$f = new KeywordFilter(['wmo'], []);
check('word in compound with hyphen', $f->matches(item('Nieuws', '<p>De Wmo-consulent helpt.</p>')), true);
check('no match', $f->matches(item('Nieuws', 'Over fietsen')), false);

$f = new KeywordFilter(['blind*'], []);
check('wildcard matches blinden', $f->matches(item('Hulp voor blinden')), true);

$f = new KeywordFilter(['blind'], []);
check('word boundary: verblindend', $f->matches(item('Een verblindend gezicht')), false);
check('word boundary: exact word', $f->matches(item('Een blind paard')), true);

$f = new KeywordFilter(['Agenda 22'], []);
check('phrase case-insensitive', $f->matches(item('Over agenda 22 vandaag')), true);
check('phrase across whitespace', $f->matches(item("Over agenda\n  22 vandaag")), true);

$f = new KeywordFilter(['toegankelijk*'], []);
check('wildcard Toegankelijkheid', $f->matches(item('Toegankelijkheid van het station')), true);

$f = new KeywordFilter(['beperking'], ['snelheidsbeperking']);
check('exclude wins', $f->matches(item('Snelheidsbeperking Eindhovenseweg')), false);
check('include without exclude', $f->matches(item('Een beperking in de ruimte')), true);

check('empty include matches nothing', (new KeywordFilter([], []))->matches(item('Wmo')), false);

check('wildcard-only term matches nothing', (new KeywordFilter(['*'], []))->matches(item('Wmo')), false);
check('double wildcard matches nothing', (new KeywordFilter(['**'], []))->matches(item('Wmo')), false);
check('empty term matches nothing', (new KeywordFilter([''], []))->matches(item('Wmo')), false);
$f = new KeywordFilter(['*', 'Wmo'], []);
check('stray * does not break real term', $f->matches(item('De Wmo')), true);
check('stray * does not match unrelated', $f->matches(item('Fietsen')), false);
check('wildcard-only exclude is ignored', (new KeywordFilter(['wmo'], ['*']))->matches(item('Wmo')), true);

$f = new KeywordFilter(['ziek*'], []);
check('unicode', $f->matches(item('Chronisch zieken')), true);
$f = new KeywordFilter(['café'], []);
check('unicode accent', $f->matches(item('Het Café opent')), true);

$f = new KeywordFilter(['zorg'], []);
check('matches in description html text', $f->matches(item('Nieuws', '<p>Meer <strong>zorg</strong> nodig</p>')), true);
check('tag names are not text', (new KeywordFilter(['strong'], []))->matches(item('Nieuws', '<strong>x</strong>')), false);
check('matches in categories', $f->matches(item('Nieuws', '', ['Zorg'])), true);
check('exclude in categories', (new KeywordFilter(['nieuws'], ['sport']))->matches(item('Nieuws', '', ['Sport'])), false);
check('regex characters are literal', (new KeywordFilter(['a.b'], []))->matches(item('axb')), false);

$s = Source::fromArray([
	'name' => 'Iederin', 'url' => 'https://example.org/feed', 'filter' => '1',
	'keywords' => "Wmo\nblind*", 'exclude' => "sport\n", 'enabled' => '1',
]);
check('source name', $s->name, 'Iederin');
check('source url', $s->url, 'https://example.org/feed');
check('source filter', $s->filter, true);
check('source keywords', $s->keywords, ['Wmo', 'blind*']);
check('source exclude', $s->exclude, ['sport']);
check('source enabled', $s->enabled, true);

$s = Source::fromArray(['name' => ' X ', 'url' => ' https://e.org/f ', 'filter' => '0', 'enabled' => 0]);
check('source trims', [$s->name, $s->url], ['X', 'https://e.org/f']);
check('source filter off', $s->filter, false);
check('source missing keywords', $s->keywords, []);
check('source enabled off', $s->enabled, false);

finish('KeywordFilter');
