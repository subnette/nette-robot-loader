<?php declare(strict_types=1);

/**
 * Test: Nette\Loaders\RobotLoader canonical exclusions through symlinks.
 */

use Nette\Loaders\RobotLoader;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$dir = realpath(getTempDir());
mkdir("$dir/src");
mkdir("$dir/outside/blocked", 0777, true);
file_put_contents("$dir/src/Keep.php", '<?php class Keep {}');
file_put_contents("$dir/outside/Linked.php", '<?php class Linked {}');
file_put_contents("$dir/outside/blocked/Blocked.php", '<?php class Blocked {}');

if (!@symlink("$dir/outside/Linked.php", "$dir/src/Linked.php")
	|| !@symlink("$dir/outside/blocked", "$dir/src/blocked-link")
) {
	Tester\Environment::skip('Cannot create file and directory symlinks.');
}

$loader = (new RobotLoader)->addDirectory("$dir/src");
$loader->rebuild();
Assert::equal([
	'Keep' => "$dir/src/Keep.php",
	'Linked' => "$dir/src/Linked.php",
	'Blocked' => "$dir/src/blocked-link/Blocked.php",
], $loader->getIndexedClasses());

foreach ([
	["$dir/outside/Linked.php", "$dir/outside/blocked"],
	["$dir/src/Linked.php", "$dir/src/blocked-link"],
] as $exclusions) {
	$loader = (new RobotLoader)->addDirectory("$dir/src")->excludeDirectory(...$exclusions);
	$loader->rebuild();
	Assert::same(['Keep' => "$dir/src/Keep.php"], $loader->getIndexedClasses());
}

$loader = (new RobotLoader)->addDirectory("$dir/src/Linked.php")->excludeDirectory("$dir/outside/Linked.php");
$loader->rebuild();
Assert::same(['Linked' => "$dir/src/Linked.php"], $loader->getIndexedClasses());

// A symlink scan root is canonicalized, but the root itself is not filtered.
$loader = (new RobotLoader)->addDirectory("$dir/src/blocked-link")->excludeDirectory("$dir/outside/blocked");
$loader->rebuild();
Assert::same(['Blocked' => "$dir/outside/blocked/Blocked.php"], $loader->getIndexedClasses());
