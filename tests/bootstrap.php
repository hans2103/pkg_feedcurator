<?php

declare(strict_types=1);

/**
 * Shared bootstrap for the plain-PHP unit tests in tests/unit/ (no PHPUnit).
 * Loads the task and system plugin classes (PSR-4) and defines check(), fixture() and finish().
 * The feed and review classes are Joomla-free, so no Joomla installation is needed.
 */

\define('_JEXEC', 1);

const TASK_DIR   = __DIR__ . '/../Src/plugins/task/feedcurator';
const SYSTEM_DIR = __DIR__ . '/../Src/plugins/system/feedcurator';

spl_autoload_register(static function (string $class): void {
	$map = [
		'HKweb\\Plugin\\Task\\Feedcurator\\'   => TASK_DIR . '/src/',
		'HKweb\\Plugin\\System\\Feedcurator\\' => SYSTEM_DIR . '/src/',
	];

	foreach ($map as $prefix => $dir) {
		if (str_starts_with($class, $prefix)) {
			$file = $dir . str_replace('\\', '/', substr($class, \strlen($prefix))) . '.php';

			if (is_file($file)) {
				require $file;
			}
		}
	}
});

$failures = 0;

function check(string $label, mixed $actual, mixed $expected): void
{
	global $failures;

	if ($actual === $expected) {
		echo "  ok   $label\n";

		return;
	}

	$failures++;
	echo "  FAIL $label\n       expected: " . var_export($expected, true) . "\n       actual:   " . var_export($actual, true) . "\n";
}

/**
 * The class of the exception the callable throws, or null when it throws nothing.
 */
function thrown(callable $callable): ?string
{
	try {
		$callable();
	} catch (\Throwable $e) {
		return $e::class;
	}

	return null;
}

function fixture(string $name): string
{
	return (string) file_get_contents(__DIR__ . '/unit/fixtures/' . $name);
}

function finish(string $name): never
{
	global $failures;

	echo $failures === 0 ? "\nAll $name assertions passed.\n" : "\n$failures FAILURE(S) in $name.\n";
	exit($failures === 0 ? 0 : 1);
}
