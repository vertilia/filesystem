<?php

namespace Vertilia\Filesystem;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass Filesystem
 */
class FilesystemTest extends TestCase
{
    /**
     * @dataProvider normalizePathProvider
     * @covers ::normalizePath
     */
    public function testNormalizePath($path, $expected)
    {
        $this->assertEquals($expected, Filesystem::normalizePath($path));
    }

    /** data provider */
    public function normalizePathProvider()
    {
        return [
            ['/', ''],
            ['///', ''],
            ['/index.php', 'index.php'],
            ['//b/../a//b/c/./d//', 'a/b/c/d'],
            ['//b/../a//b/c/./d//index.php', 'a/b/c/d/index.php'],
        ];
    }
}
