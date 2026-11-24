# Twig 1.34.0 with security fixes, for EC-CUBE 3.0.x

EC-CUBE 3.0.18 pins `twig/twig` to 1.34.0. The last 1.x release needs PHP 7.2.5, and 1.x no longer receives fixes. This fork keeps 1.34.0 and backports the published security fixes.

## Fixed

| Advisory | What |
|---|---|
| CVE-2026-46633 | PHP code injection through the template name of `{% use %}` |
| CVE-2022-39261 | Filesystem loader could load a template outside the configured directories |
| CVE-2019-9942, CVE-2024-51754, CVE-2026-47732, CVE-2026-48806, CVE-2026-48807 | Sandbox: `__toString()` reached without being allowed (filter and function arguments, concatenation, comparisons, `in`, arrays and Traversables, dynamic keys, template names) |
| CVE-2024-51755 | Sandbox: `ArrayAccess` and `__isset()` ran before the policy check |
| CVE-2024-45411, CVE-2026-46638, CVE-2026-46636, CVE-2026-49981 | Sandbox: the tag, filter and function allow-list was not checked again for a template that was already loaded, or when the sandbox was enabled between renders |

## PHP 7.4

1.34.0 drops the whitespace before every tag on PHP 7.4 (`<input type="hidden" {{ a }}>` renders as `<input type="hidden"A>`), because PHP 7.4 reports unmatched regex groups differently. Fixed in 1.34.0-p2: the output of Twig's own fixture templates is identical on PHP 7.1 and 7.4.

Not applicable to 1.34.0 (the code does not exist): CVE-2026-46628 (`spaceless` filter), CVE-2026-46635 and CVE-2026-48808 (`column` filter), CVE-2026-48805 (a 3.x file). CVE-2026-46627 is a documentation change upstream.

The sandbox fixes follow the final upstream design rather than each upstream patch, because 1.34 is built differently: the allow-list is checked each time a template's display method, block or macro runs. Clear the Twig cache after installing.

## Install

Requires Composer 2. In the shop's project root:

```
composer require nobuhiko/twig:^1.34.0
```

The package `replace`s `twig/twig` 1.34.0, so Composer removes the original and every other package keeps working against the same version.
EC-CUBE 3.0.18's own `composer.json` lists `mikey179/vfsStream` in `require-dev`; Composer 2 only accepts it in lowercase (`mikey179/vfsstream`), so fix that line first.

## What this is, and is not

- The base is exactly the version EC-CUBE 3.0.18 ships. Nothing is upgraded; only security fixes are added, each as its own commit on the `eccube3/1.34` branch.
- Each fix whose weakness could be reproduced on the base version was checked with a script that uses the weakness before the fix and fails to use it after, on PHP 5.6, 7.1 and 7.3. A few could not be reproduced there (Windows-only code, and cases the old code happened not to reach); those are ported as hardening. PHP 5.3 to 5.5 were not run (the changes avoid syntax newer than 5.3).
- This is not an official release of the upstream project and is not supported by its maintainers. Upstream: https://github.com/twigphp/Twig
- EC-CUBE 3 と、その部品の古い系列には、もう公式の修正が出ません。3.0.18 が使っているバージョンのまま、上流の修正だけを移したものです。保守: うみうしラボ
