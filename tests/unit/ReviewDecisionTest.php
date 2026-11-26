<?php

declare(strict_types=1);

/**
 * What the review page does with a request: the full matrix of signature, expiry, article state, method and action.
 * Run: php tests/unit/ReviewDecisionTest.php
 */

require __DIR__ . '/../bootstrap.php';

use HKweb\Plugin\System\Feedcurator\Review\ReviewDecision;
use HKweb\Plugin\System\Feedcurator\Review\ReviewOutcome;

// Signature.
check('invalid signature, GET', ReviewDecision::decide(false, false, 0, false, null), ReviewOutcome::Invalid);
check('invalid signature, POST publish', ReviewDecision::decide(false, false, 0, true, 'publish'), ReviewOutcome::Invalid);
check('invalid signature wins over expiry', ReviewDecision::decide(false, true, 0, false, null), ReviewOutcome::Invalid);
check('invalid signature wins over expiry on POST', ReviewDecision::decide(false, true, 0, true, 'publish'), ReviewOutcome::Invalid);
check('invalid signature wins over a done article', ReviewDecision::decide(false, false, 1, false, null), ReviewOutcome::Invalid);

// Expiry.
check('valid but expired, GET', ReviewDecision::decide(true, true, 0, false, null), ReviewOutcome::Expired);
check('valid but expired, POST publish', ReviewDecision::decide(true, true, 0, true, 'publish'), ReviewOutcome::Expired);
check('valid but expired wins over a done article', ReviewDecision::decide(true, true, 1, false, null), ReviewOutcome::Expired);

// Article no longer a concept.
foreach ([1 => 'published', -2 => 'trashed', 2 => 'archived'] as $state => $label) {
	check("$label article, GET", ReviewDecision::decide(true, false, $state, false, null), ReviewOutcome::AlreadyDone);
	check("$label article, POST publish", ReviewDecision::decide(true, false, $state, true, 'publish'), ReviewOutcome::AlreadyDone);
	check("$label article, POST discard", ReviewDecision::decide(true, false, $state, true, 'discard'), ReviewOutcome::AlreadyDone);
}

// Concept article.
check('concept, GET shows', ReviewDecision::decide(true, false, 0, false, null), ReviewOutcome::Show);
check('concept, GET ignores an action in the URL', ReviewDecision::decide(true, false, 0, false, 'publish'), ReviewOutcome::Show);
check('concept, POST publish acts', ReviewDecision::decide(true, false, 0, true, 'publish'), ReviewOutcome::Act);
check('concept, POST discard acts', ReviewDecision::decide(true, false, 0, true, 'discard'), ReviewOutcome::Act);
check('concept, POST delete is invalid', ReviewDecision::decide(true, false, 0, true, 'delete'), ReviewOutcome::Invalid);
check('concept, POST without action is invalid', ReviewDecision::decide(true, false, 0, true, null), ReviewOutcome::Invalid);
check('concept, POST empty action is invalid', ReviewDecision::decide(true, false, 0, true, ''), ReviewOutcome::Invalid);
check('concept, POST uppercased action is invalid', ReviewDecision::decide(true, false, 0, true, 'PUBLISH'), ReviewOutcome::Invalid);

// Enum values.
check('enum values', array_map(static fn (ReviewOutcome $o): string => $o->value, ReviewOutcome::cases()), ['show', 'invalid', 'expired', 'done', 'act']);

finish('ReviewDecision');
