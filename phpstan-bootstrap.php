<?php

/**
 * PHPStan bootstrap.
 *
 * Runs inside the analyser, never inside the application. Nothing here changes
 * what the code does; it only makes the code reflectable.
 *
 * @license {@link https://opensource.org/licenses/MIT MIT}
 * @copyright 2026 Tachyon
 */

declare(strict_types=1);

// PHP 8.4 removed pcntl_setitimer() and with it the ITIMER_* constants, but
// OpenSwoole still declares `Process::alarm(int $intervalUsec, int $type =
// ITIMER_REAL)`. Reflecting that method therefore tries to resolve a constant
// that no longer exists anywhere in the runtime, and PHPStan aborts the entire
// analysis — not the one file — with `Internal error: Undefined constant
// "ITIMER_REAL"` as soon as any source file names OpenSwoole\Process.
// src/Infra/Cache/Interno/OpenSwooleCacheProcessAdapter.php does.
//
// Defining it here is the narrowest fix available. The alternatives were worse:
// installing ext-pcntl does not help, because the constant is gone from PHP
// itself rather than from the extension; and stubbing the whole class through
// `stubFiles` would replace the real extension's signatures with the ide-helper's,
// which do not match it — the helper advertises `Process::name()`, a method the
// installed extension does not have, so a call to it would start passing analysis
// and killing the process at runtime.
//
// The value is the historical ITIMER_REAL, and it is never read: nothing calls
// Process::alarm().
if (!defined('ITIMER_REAL')) {
    define('ITIMER_REAL', 0);
}
