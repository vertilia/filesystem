# filesystem

Filesystem helpers implementing useful functionality.

## Usage

- `normalizePath()`

  Converts path to its canonical form.

```php
print_r(Vertilia\Filesystem\Filesystem::normalizePath('//b/../a//b/c/./d//'));
// outputs: 'a/b/c/d'
```

- `symlinkDirectorySync()`<br>

  Given the external symlink and local symlink, compares symlink targets and if different, copies external folder to
  local folder and switches local symlink atomically. Handles local symlink TTL, uses lock file to manage race
  conditions under heavy load in multiprocess environments.

```php
try {
    Vertilia\Filesystem\Filesystem::symlinkDirectorySync(
        '/shared/cache/Generated/current',
        '/local/cache/Generated/current'
    );
} catch (RuntimeException $e) {
    $logger->log($e->getMessage());
}
```
