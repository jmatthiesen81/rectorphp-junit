# rectorphp-junit

Restores JUnit XML output (`--output-format=junit`) to [Rector](https://github.com/rectorphp/rector) 2.x.

Rector removed its built-in JUnit output formatter in [rectorphp/rector-src#8250](https://github.com/rectorphp/rector-src/pull/8250). This package re-implements it as a standalone Rector output formatter, using the same public `OutputFormatterInterface` extension point that Rector's own `json`/`gitlab`/`github` formatters use.

## Installation

```bash
composer require devable/rectorphp-junit --dev
```

## Usage

Register the formatter in your `rector.php`.

### Simple (writes to STDOUT)

```php
use Devable\RectorphpJunit\JunitOutputFormatter;

$rectorConfig->singleton(JunitOutputFormatter::class);
```

```bash
vendor/bin/rector process --dry-run --output-format=junit --no-progress-bar > rector-junit.xml
```

Note: Rector only silences its own progress/warning output for the built-in `json` format, so STDOUT redirection can still pick up stray warning lines for custom formats. `--no-progress-bar` covers the most common case, but the file-writing mode below is more robust for CI.

### Recommended for CI (writes directly to a file)

```php
use Devable\RectorphpJunit\JunitOutputFormatter;

$rectorConfig->singleton(
    JunitOutputFormatter::class,
    static fn () => new JunitOutputFormatter(getcwd() . '/rector-junit.xml'),
);
```

```bash
vendor/bin/rector process --dry-run --output-format=junit --no-progress-bar
```

## Output format

One `<testcase>` per file Rector would change or errored on:

- A file with a suggested diff becomes a `<testcase>` containing a `<failure>` with the full unified diff and the short names of the Rector rules that applied.
- A file Rector failed to process becomes a `<testcase>` containing an `<error>` with the error message.
- Untouched files are not listed.

## Compatibility

Tested against `rector/rector` 2.6.x. The package depends on Rector's `OutputFormatterInterface`, `ProcessResult`, `Configuration`, `FileDiff`, and `SystemError` classes, which are part of Rector's public (non-prefixed) API surface. Re-verify these getters before widening the `rector/rector` constraint to a future major version.

## License

MPL-2.0
