<?php

declare(strict_types=1);

namespace Stejac\Zeitangaben\Tests;

use PHPUnit\Framework\TestCase;

final class NoClockTest extends TestCase
{
    private const array FORBIDDEN = [
        "'now'" => "~'now'~",
        'new DateTimeImmutable() without arguments' => '~new\s+\\\\?DateTimeImmutable\s*\(\s*\)~',
        'time()' => '~(?<![\w>:$])time\s*\(~',
        'date(' => '~(?<![\w>:$])date\s*\(~',
        'microtime(' => '~(?<![\w>:$])microtime\s*\(~',
        'Clock' => '~Clock~',
    ];

    public function testSourceNeverReadsTheSystemClock(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../src'));
        $checked = 0;
        foreach ($files as $file) {
            if (!$file instanceof \SplFileInfo || 'php' !== $file->getExtension()) {
                continue;
            }
            ++$checked;
            $code = (string) file_get_contents($file->getPathname());
            foreach (self::FORBIDDEN as $label => $pattern) {
                self::assertSame(0, preg_match($pattern, $code), sprintf('%s uses %s', $file->getFilename(), $label));
            }
        }
        self::assertGreaterThan(10, $checked);
    }
}
