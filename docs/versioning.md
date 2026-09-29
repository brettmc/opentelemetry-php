# Versioning

This project uses [semver v2](https://semver.org/), as does the rest of OpenTelemetry.

## Packages are versioned independently

This repository is a monorepo, but it is not a single release unit. Each package is mirrored to its own read-only
repository in the [opentelemetry-php](https://github.com/opentelemetry-php) organization via
[git subtree splits](https://github.com/splitsh/lite), and each is tagged and released independently there. No
`composer.json` in this repository has a `version` field — Composer derives every package version from its mirror's tags.

There is no relationship between the version numbers of any two packages: `api` may be at 1.2.3 while `sdk` is at 2.3.1.
The API and SDK are separate release lines and are **not** expected to share a major version.

## Guarantees

**API.** No existing method name or signature changes in a patch release; signatures may change in a backwards
compatible way in a minor. An API package works with any SDK version permitted by that SDK's constraint on the API.

**SDK.** The public surface — constructors, configuration, end-user interfaces — stays backwards compatible on the same
terms. Internal interfaces are allowed to break; see the
[backwards compatibility readme](../src/SDK/Common/Dev/Compatibility/README.md) for how deprecations are signalled.

**PHP versions.** Raising the minimum PHP version is a **minor** release, for every package including the API. It
changes no API surface, and Composer enforces it at resolution time, so a user on the dropped version is simply not
offered the new release rather than being broken by it. Tying the floor to majors would force an annual major of every
package, for a reason that has nothing to do with the API. Announce the drop ahead of time, put it in the release notes,
and never raise the floor in a patch. See [Versioning in the README](../README.md#versioning) for the current support
window.

## Where to open a pull request

**Target `main`** — features, bug fixes, refactoring, documentation, dependency updates and breaking changes alike.
`main` is always the newest version of every package.

| Your change | Open the PR against |
|---|---|
| Anything new — feature, fix, refactor, docs, dependency bump | `main` |
| A bug fix that also affects a still-supported older major | `main` first, then [backport](#backporting-a-fix) |
| A bug fix for code that only exists on a maintenance branch | that maintenance branch |

If you are unsure, target `main`. A maintainer will ask for a backport if one is needed.

## Major versions

A major version is scoped to the packages that actually break — it is not a repository-wide event. Breaking changes are
developed on `main`; there is no long-lived branch for the next major.

`open-telemetry/api` is expected to stay on `1.x`. An API major is a last resort requiring a deliberate SIG-wide
decision, not the symmetric counterpart of an SDK major — prefer additive interfaces, opt-in extension interfaces and
deprecation.

### Release process for a major

1. Check that no maintenance branch is live — [only one may exist at a time](#maintenance-branches).
2. Decide the full set of packages in scope, including any other package with a major pending. Majors are batched into
   one release rather than shipped in sequence.
3. Announce a feature freeze date for the current major.
4. Land the breaking work on `main` behind that date, prereleasing as needed. The tag convention here has no hyphen:
   `2.0.0beta1`, `2.0.0RC1`.
5. Update the inter-package constraints on `main` for every package in scope: each dependent's `require` entry, the
   matching root `replace` entry, and any `conflict` range referring to a package whose major changed.
6. At the freeze, cut a maintenance branch for the **outgoing** major, named after the outgoing `sdk` major. Check the
   name is unused here *and* on every mirror first — pushing onto an existing branch of unrelated history is the failure
   mode to avoid. Record the version of every package the branch carries, and
   [enable mirroring](#enabling-mirroring-for-a-new-branch) for it.
7. Release the new major from `main`, and maintain the outgoing one from its branch for its support window.

Note the direction: the maintenance branch is for the outgoing major and is cut at the *end* of development. It is
short-lived and shrinking in scope, not long-lived and growing.

## Maintenance branches

A maintenance branch keeps a superseded major version receiving bug fixes after `main` has moved on. Apart from `main`
it is the only long-lived branch in this repository, and if no major has been superseded there are none — the normal
state.

**At most one may be live at a time, so no new major may begin while one is.** A branch is repository-wide while
versions are per-package: two concurrent branches would each carry the same eleven packages, with no answer to "which
branch does a fix to `context` belong on". The consequence is deliberate — majors get batched, which is one migration
for users instead of two. If a branch is live, either wait for its support window to close or fold the work into the
next major of the line it serves.

### The branch name is a label, not a manifest

A maintenance branch is named after the outgoing major of `open-telemetry/sdk` — `1.x`, then `2.x`. The SDK is the
anchor because it is the package users install.

The name is not a claim about the version of every package on the branch. Packages that took no major bump stay where
they were, so a `1.x` branch cut for an SDK major carries `api`, `context` and `sem-conv` exactly as they were on
`main`. Packages below `1.0.0` are not at `1.x` at all — `sdk-configuration` and `extension-propagator-cloudtrace` sit
on a `1.x` branch at `0.x` versions. That is cosmetic rather than functional: Composer's caret treats a `0.x` minor as a
major, so `^0.9` resolves to `>=0.9.0 <0.10.0` and users stay on the line automatically
([Composer version docs](https://getcomposer.org/doc/articles/versions.md)).

The gap widens with each cut, because the API stays on `1.x` while the SDK climbs. A branch named `3.x` still carrying
an `api` at `1.x` is the design working as intended, not drift to be tidied up. Because the name explains less of the
branch each time, **record the version of every package in that branch's copy of this document when it is cut.**

### What may land on a maintenance branch

| Allowed | Not allowed |
|---|---|
| Bug fixes | New features |
| Security fixes | Breaking changes of any kind |
| CI fixes that keep the branch releasable | Raising the minimum PHP version — it would cut users off from the fixes |
| Dependency updates needed to keep it installable | Refactoring, or changes that only tidy code |

The test is whether a user on the old major could be surprised by upgrading to the patch release. Anything that would
surprise them belongs on `main`.

### Backporting a fix

**Land the fix on `main` first, then cherry-pick it onto each supported maintenance branch.** Always in that order: a
fix applied only to the maintenance branch and never forward ported silently reintroduces the bug in the current major.

```bash
# after the fix has merged to main
git switch 1.x
git switch -c backport-1.x-fix-the-thing
git cherry-pick -x <sha-from-main>
```

`-x` records the originating commit, which is what tells a later reader where a commit on a maintenance branch came
from. Open one pull request per branch so CI runs against each, and reference the original.

If a cherry-pick conflicts, do not force it — port by hand and say in the PR how the implementation differs and why.
Some fixes legitimately have no counterpart: a bug the breaking work introduced has nothing to backport, and a bug in
code that `main` has rewritten is fixed on the branch directly (say so, or it looks like a missed forward port). A
security fix may need to land everywhere at once — coordinate with maintainers, and do not open a public pull request
describing an unreleased vulnerability.

### Releasing from a maintenance branch

Each mirror has its own copy of the branch, and releases are tagged there exactly as for `main`. Tag from the branch,
not from `main`, and only tag the packages that changed — the branch carries all of them, but they are versioned
independently. Maintenance releases are patch releases; a change needing a minor bump is a feature and belongs on
`main`.

### Enabling mirroring for a new branch

A new branch is not published until it is added in both places:

* `.gitsplit.yml` — the `origins` list (currently `^main$` and `^split$`).
* `.github/workflows/split-monorepo.yaml` — the `push` trigger's branch list.

### End of life

1. Announce that the version line is end-of-life.
2. Remove the branch from `.gitsplit.yml` and the workflow trigger, so it stops being mirrored.
3. Delete the branch, here and on the split repositories.

Released tags are permanent and are never deleted — removing the branch ends maintenance, it does not withdraw anything
already published.
