<?php

declare(strict_types=1);

/**
 * Signed review links: signature, expiry and URL format.
 * Run: php tests/unit/ReviewTokenTest.php
 */

require __DIR__ . '/../bootstrap.php';

use HKweb\Plugin\System\Feedcurator\Review\ReviewToken;

$token   = new ReviewToken('site-secret');
$expires = 2000000000;
$sig     = $token->sign(12, 5, $expires);

check('signature is 64 hex characters', (bool) preg_match('/^[0-9a-f]{64}$/', $sig), true);
check('signature is the HMAC of "a|u|e" with the derived key', $sig, hash_hmac('sha256', '12|5|' . $expires, hash_hmac('sha256', 'feedcurator-review', 'site-secret')));
check('valid before expiry', $token->verify(12, 5, $expires, $sig, $expires - 10), true);
check('valid at the expiry second', $token->verify(12, 5, $expires, $sig, $expires), true);
check('invalid one second after expiry', $token->verify(12, 5, $expires, $sig, $expires + 1), false);
check('invalid for another user', $token->verify(12, 6, $expires, $sig, $expires - 10), false);
check('invalid for another article', $token->verify(13, 5, $expires, $sig, $expires - 10), false);
check('invalid for a changed expiry', $token->verify(12, 5, $expires + 100, $sig, $expires - 10), false);
check('invalid for another secret', (new ReviewToken('other'))->verify(12, 5, $expires, $sig, $expires - 10), false);
check('invalid for an empty signature', $token->verify(12, 5, $expires, '', $expires - 10), false);
check('invalid for an uppercased signature', $token->verify(12, 5, $expires, strtoupper($sig), $expires - 10), false);

$url = ReviewToken::url('https://example.org/', 12, 5, $expires, $sig);

check('url format', $url, "https://example.org/index.php?option=com_ajax&group=system&plugin=feedcurator&format=html&a=12&u=5&e=$expires&s=$sig");
check('no double slash after the host', str_contains($url, 'org//'), false);
check('url without trailing slash gives the same url', ReviewToken::url('https://example.org', 12, 5, $expires, $sig), $url);

finish('ReviewToken');
