<?php

namespace Vertilia\Filesystem;

/**
 * Filesystem-related methods
 */
class Filesystem
{
    /**
     * Normalizes path by removing empty and dot (.) dirs, resolving parent
     * (..) dirs and removing starting slash.
     *
     * @param string $path path to normalize
     * @return string
     *
     * @assert('') = ''
     * @assert('/') = ''
     * @assert('/etc/hosts') = 'etc/hosts'
     * @assert('.././/tmp/../home//admin/./.ssh') = 'home/admin/.ssh'
     */
    public static function normalizePath($path = '')
    {
        $dirs = [];
        foreach (explode('/', $path) as $d) {
            if (strlen($d) and $d != '.') {
                if ($d == '..') {
                    array_pop($dirs);
                } else {
                    $dirs[] = $d;
                }
            }
        }

        return implode('/', $dirs);
    }

    /**
     * @param string $src_symlink_dir
     * @param string $trg_symlink_dir
     * @param int $trg_symlink_ttl
     * @param int $lock_ttl
     * @return bool
     * @throws \RuntimeException
     */
    public static function syncSymlinkDir($src_symlink_dir, $trg_symlink_dir, $trg_symlink_ttl = 600, $lock_ttl = 60)
    {
        $current_time = time();

        // verify current symlink timestamp

        // quick exit path: return true if fresh symlink
        if ($current_time - $trg_symlink_ttl < filemtime($trg_symlink_dir)) {
            return true;
        }

        // lock before accessing shared symlink dir

        $lock_filename = sprintf("%s.lck", realpath($trg_symlink_dir));

        if ($lock_fh = @fopen($lock_filename, 'x')) {
            // lock file created
            // close it
            @fclose($lock_fh);
        } elseif (filemtime($lock_filename) <= $current_time - $lock_ttl) {
            // lock file exists but too old
            // delete it
            @unlink($lock_filename);
            throw new \RuntimeException("Local lock file expired TTL of $lock_ttl seconds. Deleting.");
        } else {
            // lock file exists (operation carried out by different process)
            return true;
        }

        // we are the only process at this moment to access shared symlink dir from this host

        // verify symlinks targets

        $ext_link = readlink($src_symlink_dir);
        $loc_link = readlink($trg_symlink_dir);

        // exit path: verify error codes on symlinks
        if (false === $ext_link or false === $loc_link) {
            throw new \RuntimeException('Error reading external or local symlink target');
        }

        // exit path: if external symlink == local symlink
        if ($ext_link === $loc_link) {
            touch($trg_symlink_dir, $current_time);
            clearstatcache(true, $trg_symlink_dir);
            return true;
        }

        // at this point we know that local symlink and external symlink point at different targets
        // to copy the folder locally we need a lock

        // we acquired a lock for directory copy

        // copy folder contents using shell
        $a = [];
        exec(
            sprintf(
                'cp -r %s %s',
                escapeshellarg(realpath($src_symlink_dir)),
                escapeshellarg(dirname(realpath($trg_symlink_dir)).'/')
            ),
            $a,
            $error_code
        );
        if ($error_code) {
            @unlink($lock_filename);
            throw new \RuntimeException("Error ($error_code) copying external folder to local destination");
        }

        // switch symlink
        exec(
            sprintf(
                'ln -snf %s %s',
                escapeshellarg($ext_link),
                escapeshellarg($trg_symlink_dir)
            ),
            $a,
            $error_code
        );
        clearstatcache(true, $trg_symlink_dir);
        @unlink($lock_filename);
        if ($error_code) {
            throw new \RuntimeException("Error ($error_code) changing local symlink");
        }

        return true;
    }
}
