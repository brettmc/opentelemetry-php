# Versioning

This project uses [semver v2](https://semver.org/), as does the rest of OpenTelemetry.

## Packages are versioned independently

This repository is a monorepo, but packages are released separately. Each one is mirrored to a read-only repository in
the [opentelemetry-php](https://github.com/opentelemetry-php) organization using
[git subtree splits](https://github.com/splitsh/lite), then tagged and released there. No `composer.json` here has a
`version` field. Composer takes each package's version from its mirror's tags.

The version numbers of different packages are unrelated. `api` might be at 1.2.3 while `sdk` is at 2.3.1. The API and
SDK are separate release lines, and are not expected to share a major version.

## Compatibility guarantees

### API

- A patch release doesn't change any existing method name or signature.
- A minor release may change signatures, but only in a backwards compatible way.
- An API package works with any SDK version whose constraint allows it.

### SDK

The public parts of the SDK (constructors, configuration and end-user interfaces) follow the same rules as the API.
Internal interfaces may break in any release.

### Deprecation

- Mark deprecated code with `@deprecated`, and name the replacement.
- Keep it working until the next major of that package.
- Optionally raise an `E_USER_DEPRECATED` notice when it's used.

Semantic conventions are the exception. See the [sem-conv readme](../src/SemConv/README.md).

### Raising the minimum PHP version

Raising the minimum PHP version is a minor release, for every package including the API.

It changes no API, and Composer enforces it during dependency resolution. A user on the dropped PHP version is simply
not offered the new release, so nothing breaks for them. Tying the minimum to major versions would mean a major release
of every package each year, for a reason that has nothing to do with the API.

Announce the change in advance, and put it in the release notes. See [Versioning in the README](../README.md#versioning)
for the current support window.

## Where to open a pull request

Open pull requests against `main`, including breaking changes. `main` always has the newest version of every package.

A bug fix that also affects the previous major goes to `main` first, and is then [backported](#backport-a-fix). A fix
for code that only exists on the maintenance branch goes to that branch.

If you're unsure, target `main`. A maintainer will ask for a backport if one is needed.

## Major versions

A major version applies only to the packages that break. It isn't a release of the whole repository. Breaking changes
are developed on `main`, and there is no long-lived branch for the next major.

`open-telemetry/api` should stay on 1.x. An API major is a last resort, and needs a deliberate decision by the SIG. An
SDK major doesn't imply one. Prefer additive interfaces, opt-in extension interfaces and deprecation.

### Release a new major version

1. Check that no maintenance branch is live. [Only one can exist at a time](#only-one-maintenance-branch-at-a-time).
2. Decide which packages are in scope, including any other package with a major pending. Release them together, not
   one after another.
3. Announce a feature freeze date for the current major.
4. At the freeze, cut a maintenance branch for the outgoing major, named after the outgoing `sdk` major. First check
   the name isn't already used, here or on any mirror. Pushing onto an existing branch with unrelated history is the
   mistake to avoid. Record the version of every package on the branch, and
   [enable mirroring](#enable-mirroring-for-a-new-branch) for it.
5. On `main`, update every package in scope:
   - its `dev-main` branch alias
   - each dependent's `require` entry for it
   - its `replace` entry in the root `composer.json`
   - any `conflict` range that names it

   Do this before the first prerelease, so the prereleases can be installed together.
6. Land the breaking changes on `main`, with prereleases as needed. Prerelease tags have no hyphen: `2.0.0beta1`,
   `2.0.0RC1`.
7. Release the new major from `main`. Maintain the outgoing major from its branch until its support window ends.

## Maintenance branches

A maintenance branch lets a superseded major keep getting bug fixes after `main` moves on. It's the only kind of
long-lived branch besides `main`. Usually there isn't one.

### Only one maintenance branch at a time

A new major can't begin while a maintenance branch is live.

A branch covers the whole repository, but versions are per package. With two branches, each would carry every package,
and there'd be no good answer to which branch a fix to `context` belongs on.

So majors get batched. That's deliberate: users get one migration instead of two.

### Branch names follow the SDK major

A maintenance branch is named after the outgoing major of `open-telemetry/sdk`: `1.x`, then `2.x`. The SDK is the
anchor because it's the package users install.

The name doesn't describe the version of every package on the branch. Packages that didn't get a major bump stay as
they were, so a `1.x` branch cut for an SDK major carries `api`, `context` and `sem-conv` as they were on `main`, and
`sdk-configuration` at 0.x. Users install from tags, not branch names, so this is only a label.

The gap grows with each new branch, because the API stays on 1.x while the SDK moves on. A `3.x` branch carrying `api`
1.x is expected, not drift to tidy up.

### What can go on a maintenance branch

Maintenance releases are patch releases. A change that would need a minor or major release belongs on `main`.

| Allowed                                          | Not allowed                                                      |
|--------------------------------------------------|------------------------------------------------------------------|
| Bug fixes                                        | New features                                                     |
| Security fixes                                   | Breaking changes of any kind                                     |
| CI fixes that keep the branch releasable         | Raising the minimum PHP version, which cuts users off from fixes |
| Dependency updates needed to keep it installable | Refactoring, or changes that only tidy code                      |

### Backport a fix

Land the fix on `main` first, then cherry-pick it onto the maintenance branch. A fix that only goes to the maintenance
branch leaves the bug in the current major.

```bash
# after the fix has merged to main
git fetch upstream
git switch -c backport-1.x-fix-the-thing upstream/1.x
git cherry-pick -x <sha-from-main>
```

`-x` records the original commit, so a later reader can see where the backport came from. Open the backport as its
own pull request so CI runs against the branch, and link the original.

If the cherry-pick conflicts, port the fix by hand. Say in the pull request how it differs and why.

Some fixes have no counterpart:

- A bug introduced by the breaking changes has nothing to backport.
- A bug in code that `main` has since rewritten is fixed on the branch directly. Say so in the pull request, or it
  looks like a missed forward port.

A security fix may need to land on both branches at once. Coordinate with the maintainers, and don't open a public pull
request that describes an unreleased vulnerability.

### Release from a maintenance branch

Each mirror has its own copy of the branch, and releases are tagged there, the same as for `main`. Tag from the branch,
not from `main`, and only tag the packages that changed.

### Enable mirroring for a new branch

A new branch isn't published or tested until it's added to all of these:

- `.gitsplit.yml`: the `origins` list.
- `.github/workflows/split-monorepo.yaml`: the branch list in the `push` trigger.
- `.github/workflows/php.yml`: the branch lists in the `push` and `pull_request` triggers.

### End a maintenance branch

1. Announce that the version line has reached end of life.
2. Remove the branch from the files listed in [enable mirroring](#enable-mirroring-for-a-new-branch).
3. Delete the branch, here and on the mirrors.

Tags are permanent and never deleted. Removing the branch ends maintenance. It doesn't withdraw anything already
released.
