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
     * @param string $external_symlink_dir
     * @param string $local_symlink_dir
     * @param int $local_symlink_ttl
     * @param int $lock_ttl
     * @return bool
     * @throws \RuntimeException
     */
    public static function symlinkDirectorySync($external_symlink_dir, $local_symlink_dir, $local_symlink_ttl = 600, $lock_ttl = 60)
    {
        // verify current symlink timestamp
        $local_ts = filemtime($local_symlink_dir);

        $current_time = time();

        // quick exit path: return true if fresh symlink
        if ($current_time - $local_symlink_ttl < $local_ts) {
            return true;
        }

        // exit path: if external symlink == local symlink
        $local_symlink = $local_symlink_dir;
        clearstatcache(true, $external_symlink_dir);
        clearstatcache(true, $local_symlink);
        $ext_link = readlink($external_symlink_dir);
        $loc_link = readlink($local_symlink);
        if ($ext_link === $loc_link) {
            touch($local_symlink);
            return true;
        }

        // exit path: verify error codes on symlinks
        if (false === $ext_link or false === $loc_link) {
            throw new \RuntimeException("Error reading external or local symlink target");
        }

        // at this point we know that local symlink and external symlink point at different targets
        // to copy the folder locally we need a lock

        $lock_filename = sprintf("%s.lck", realpath($local_symlink));

        if ($lock_fh = @fopen($lock_filename, 'x')) {
            // lock file created
            // close it
            @fclose($lock_fh);
        } elseif (filemtime($lock_filename) <= $current_time - $lock_ttl) {
            // lock file exists but too old
            // delete it
            @unlink($lock_filename);
            throw new \RuntimeException("Local lock file expired TTL of {$lock_ttl} seconds. Deleting.");
        } else {
            // lock file exists (operation carried out by different process)
            return true;
        }

        // we acquired a lock for directory copy

        // copy folder contents using shell
        $a = [];
        exec(
            sprintf(
                'cp -r %s %s',
                escapeshellarg(realpath($external_symlink_dir)),
                escapeshellarg(dirname(realpath($local_symlink_dir)).'/')
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
                escapeshellarg($local_symlink_dir)
            ),
            $a,
            $error_code
        );
        @unlink($lock_filename);
        if ($error_code) {
            throw new \RuntimeException("Error ($error_code) changing local symlink");
        }

        return true;
    }
}
