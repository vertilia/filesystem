# filesystem

Filesystem helpers implementing useful functionality.

## Usage

- `normalizePath()`

Converts path to its canonical form, removes leading and trailing slashes(/).

```php
print_r(Vertilia\Filesystem\Filesystem::normalizePath('//b/../a//b/c/./d//'));
// outputs: 'a/b/c/d'
```

- `syncSymlinkDir()`

Given the external symlink and local symlink, compares symlink targets and if different, copies external folder to local
folder and switches local symlink. Handles local symlink TTL, uses lock file to manage race conditions under heavy load
in multi-process environments.

The following example describes a folder on a shared ressource with the latest file structure that needs to be mirrored
to local filesystems. Every host is responsible for maintaining the local copy of external ressource locally. Current
release is identified by the `current` symlink inside the `release` folder.

- on shared ressource
```
/shared/release
├── v1.0.0
│   ├── file1.txt
│   ├── file2.txt
│   └── file3.txt
├── v1.0.1
│   ├── file1.txt
│   ├── file2.txt
│   └── file3.txt
├── v1.0.2
│   ├── file1.txt
│   ├── file2.txt
│   └── file3.txt
└── current -> v1.0.2
```
- on every host
```
/local/release
├── v1.0.0
│   ├── file1.txt
│   ├── file2.txt
│   └── file3.txt
├── v1.0.1
│   ├── file1.txt
│   ├── file2.txt
│   └── file3.txt
└── current -> v1.0.1
```

The following snippet executed on each host will verify the freshness of `current` symlink in `/local/release`
folder and if it is older than 600 seconds, will

- try to create a lock file or exit

  - if lockfile exists but is older than 60 seconds, delete the file before exiting

- verify target of `current` symlink in both `/local/release` and `/shared/release`

- if targets point to the same release:
  - touch local `current` symlink
  - delete lock file
  - exit

- else:
  - copy the contents of `/shared/release/v1.0.2` to `/local/release/v1.0.2`
  - switch `/local/release/current` symlink to point to `v1.0.2`
  - delete lock file
  - exit

Snippet:

```php
try {
    Vertilia\Filesystem\Filesystem::syncSymlinkDir(
        '/shared/release/current',
        '/local/release/current',
        600,
        60
    );
} catch (RuntimeException $e) {
    $logger->log($e->getMessage());
}
```
