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

    /**
     * @dataProvider syncSymlinkDirProvider
     * @covers ::syncSymlinkDir
     */
    public function testSyncSymlinkDir($filesystem, $src_symlink, $trg_symlink, $trg_symlink_time, $before, $after)
    {
        // create temp root dir for the filesystem
        $root = __DIR__.'/test-'.uniqid();
        mkdir($root, 0777, true) or die("Cannot create $root\n");

        // create filesystem
        create_fs($root, $filesystem);
//        printf("setting %s to %s\n", "$root$trg_symlink", date('r', $trg_symlink_time));
        touch("$root$trg_symlink", $trg_symlink_time);

        $this->assertEquals($before, readlink("$root$trg_symlink"));
        $this->assertTrue(Filesystem::syncSymlinkDir("$root$src_symlink", "$root$trg_symlink", 600, 60));
//        echo `ls -alFR $root`;
        $this->assertEquals($after, readlink("$root$trg_symlink"));

        drop_fs($root);
    }

    /** data provider */
    public function syncSymlinkDirProvider()
    {
        $filesystem = [
            'shared' => [
                'release' => [
                    'v1.0.0' => [
                        'file1.0.0.1.txt' => true,
                        'file1.0.0.2.txt' => true,
                        'file1.0.0.3.txt' => true,
                    ],
                    'v1.0.1' => [
                        'file1.0.1.1.txt' => true,
                        'file1.0.1.2.txt' => true,
                        'file1.0.1.3.txt' => true,
                    ],
                    'current' => 'v1.0.1',
                ],
            ],
            'local' => [
                'release' => [
                    'v1.0.0' => [
                        'file1.0.0.1.txt' => true,
                        'file1.0.0.2.txt' => true,
                        'file1.0.0.3.txt' => true,
                    ],
                    'current' => 'v1.0.0',
                ],
            ],
        ];

        return [
            [$filesystem, '/shared/release/current', '/local/release/current', time() - 120, 'v1.0.0', 'v1.0.0'],
            [$filesystem, '/shared/release/current', '/local/release/current', time() - 1200, 'v1.0.0', 'v1.0.1'],
        ];
    }
}

function create_fs($path, $filesystem)
{
    foreach ($filesystem as $name => $content) {
        if (is_array($content)) {
            mkdir("$path/$name", 0777) or die("Cannot create $path/$name\n");
            create_fs("$path/$name", $content);
        } elseif (is_bool($content)) {
            touch("$path/$name") or die("Cannot touch $path/$name\n");
        } elseif (is_string($content)) {
            symlink($content, "$path/$name") or die("Cannot touch $path/$name\n");
        }
    }

    return true;
}

function drop_fs($path)
{
    return `rm -rf $path`;
}
