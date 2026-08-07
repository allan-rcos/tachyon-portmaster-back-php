# 0007. Keep the PHPStan baseline limited to generated code

**Status:** Accepted · 2026-07-26

## Context

PHPStan runs at level 9 over `src`. The flatc-generated FlatBuffers tables do
not pass it, and cannot be made to: they are rewritten on every
`composer flatbuffers`, so an annotation added to one is gone at the next schema
change.

A baseline is the usual answer to "existing code does not pass yet". It is also
the usual way a codebase quietly stops being analysed, one suppression at a
time.

## Decision

`phpstan-generated-baseline.neon` holds findings from flatc-generated files
**and nothing else**.

Everything hand-written stays fully analysed at level 9. Since
[ADR 0009](0009-abstract-factory-e-strategy-na-negociacao.md) moved the
hand-written half out of `src/API/Fbs/`, that directory and this baseline are
simply the same set — the boundary that used to run *through* a directory now
runs between two.

A finding inside a generated file is not fixed in place. The fix belongs in
`scripts/patch-flatbuffers.php`, which already normalises builder class casing,
`create*()` return docblocks, child-table sentinels, scalar type names and
string accessor types. That script is the only sanctioned way to change
generated output, and its transforms are idempotent so they survive
regeneration.

Regenerate the baseline after changing schemas:

```bash
scripts/generate-phpstan-baseline.php
```

## Consequences

- The baseline never becomes a place to hide a real finding, because a real
  finding is in a hand-written file and hand-written files are not in it.
- Fixing a generated-code problem is more work: it means writing a transform
  rather than an annotation. That cost is what keeps the fix durable.
- The baseline is large and churns when schemas change. It is regenerated
  wholesale, never edited.
- The same file-level boundary is drawn in `phpdoc.dist.xml`, so generated code
  is excluded from the rendered documentation too.

## Revisit if

flatc's PHP codegen starts emitting level-9-clean output, at which point the
baseline and most of `patch-flatbuffers.php` can be deleted together.
