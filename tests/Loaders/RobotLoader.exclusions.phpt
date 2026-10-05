<?php declare(strict_types=1);

/**
 * Test: Nette\Loaders\RobotLoader canonical exclusions and explicit scan paths.
 */

use Nette\Loaders\RobotLoader;
use Tester\Assert;


require __DIR__ . '/../bootstrap.php';


$dir = realpath(getTempDir());
foreach (['src/nested', 'src/skip', 'src/.hidden', 'src/temp', 'src/legacy.old'] as $path) {
	mkdir("$dir/$path", 0777, true);
}
foreach ([
	'src/Keep.php' => 'Keep',
	'src/nested/Nested.php' => 'Nested',
	'src/skip/Skip.php' => 'Skip',
	'src/Excluded.php' => 'Excluded',
	'src/.hidden/Hidden.php' => 'Hidden',
	'src/temp/Temp.php' => 'Temp',
	'src/legacy.old/Old.php' => 'Old',
	'src/Manual.inc' => 'Manual',
] as $path => $class) {
	file_put_contents("$dir/$path", "<?php class $class {}");
}

$check = static function (RobotLoader $loader, array $expected): void {
	$loader->rebuild();
	Assert::equal($expected, $loader->getIndexedClasses());
};
$defaults = [
	'Keep' => "$dir/src/Keep.php",
	'Nested' => "$dir/src/nested/Nested.php",
	'Skip' => "$dir/src/skip/Skip.php",
	'Excluded' => "$dir/src/Excluded.php",
];
$check((new RobotLoader)->addDirectory("$dir/src"), $defaults);
$check(
	(new RobotLoader)->addDirectory("$dir/src")->excludeDirectory("$dir/src/*.php", "$dir/missing"),
	$defaults,
);
$check(
	(new RobotLoader)->addDirectory("$dir/src")->excludeDirectory("$dir/src/nested/../skip", "$dir/src/Excluded.php"),
	['Keep' => "$dir/src/Keep.php", 'Nested' => "$dir/src/nested/Nested.php"],
);

$loader = (new RobotLoader)->addDirectory("$dir/src");
$expected = $defaults;
unset($expected['Skip']);
$loader->ignoreDirs = ['skip', '.*', '*.old', 'temp'];
$check($loader, $expected);
$loader->ignoreDirs = [];
$check($loader, $defaults + [
	'Hidden' => "$dir/src/.hidden/Hidden.php",
	'Temp' => "$dir/src/temp/Temp.php",
	'Old' => "$dir/src/legacy.old/Old.php",
]);
$loader->acceptFiles = ['*.inc'];
$check($loader, ['Manual' => "$dir/src/Manual.inc"]);

// Explicit files bypass masks and exclusions, and retain their path spelling.
$cwd = getcwd();
chdir($dir);
try {
	$loader = (new RobotLoader)->addDirectory('src/Manual.inc', 'src/Manual.inc')->excludeDirectory('src/Manual.inc');
	$loader->ignoreDirs = ['Manual.inc'];
	$loader->acceptFiles = ['*.never'];
	$check($loader, ['Manual' => 'src/Manual.inc']);
} finally {
	chdir($cwd);
}

// Filters apply to descendants, not the explicitly supplied scan root.
$check(
	(new RobotLoader)->addDirectory("$dir/src/skip")->excludeDirectory("$dir/src/skip"),
	['Skip' => "$dir/src/skip/Skip.php"],
);
$check((new RobotLoader)->addDirectory("$dir/src/temp"), ['Temp' => "$dir/src/temp/Temp.php"]);
Assert::exception(
	fn() => (new RobotLoader)->addDirectory("$dir/missing")->rebuild(),
	Nette\IOException::class,
	"Directory '$dir/missing' not found.",
);
