# Contributing to ost-quick-buttons

## Branching Strategy

```
stable              X.Y.Z           production releases (auto-update source)
  └── rc            X.Y.Z-RC.N      release candidates
       └── beta     X.Y.Z-beta.N    beta testing
            └── dev X.Y.Z-dev       active development
                 └── feature/*      new features
                 └── fix/*          bug fixes
                 └── chore/*        maintenance, docs
```

### Branch Rules

| Branch | Direct commits | Receives merges from | Deploys to |
|--------|---------------|---------------------|------------|
| `stable` | Never | `rc` only | Production servers via auto-update |
| `rc` | Version bump only | `beta` only | Staging / final QA |
| `beta` | Version bump only | `dev` only | Test environments |
| `dev` | Never | `feature/*`, `fix/*`, `chore/*` | Docker dev environment |

### Promotion Flow

```
feature/xyz ──PR──> dev ──merge──> beta ──merge──> rc ──merge──> stable
                 4.3.0-dev     4.3.0-beta.1    4.3.0-RC.1     4.3.0
```

1. Create a branch from `dev`, do your work, PR back to `dev`
2. When `dev` is feature-complete for a release → merge into `beta`, bump to `X.Y.Z-beta.1`
3. After beta validation → merge `beta` into `rc`, bump to `X.Y.Z-RC.1`
4. After RC passes → merge `rc` into `stable`, bump to `X.Y.Z`, tag `vX.Y.Z`

### Hotfix Flow

Critical production fixes bypass the normal flow:

```
fix/critical ──PR──> stable (bump patch) ──back-merge──> rc, beta, dev
```

After hotfixing stable, back-merge stable into `rc` → `beta` → `dev` to keep all branches in sync.

---

## Versioning Plan

Follows [Semantic Versioning](https://semver.org/): **MAJOR.MINOR.PATCH**

### When to Bump Each Number

#### MAJOR (X.0.0) — Breaking or architectural changes

Bump major when any of these are true:
- **Config format changes** that break existing `widget_config` JSON
- **Database schema changes** that require data migration (not just new tables)
- **API contract changes** — AJAX endpoint request/response shape changes
- **Architectural redesigns** — fundamental change to how the plugin works
- **Feature absorptions** — integrating an external plugin's functionality

Examples from history:
| Version | What changed |
|---------|-------------|
| 2.0.0 | Widget-based architecture (replaced per-button model) |
| 3.0.0 | Two-step variant + workflow builder |
| 4.0.0 | Workflow dashboard + timer system |
| 5.0.0 | *(planned)* Performance value tracking |

**Rule**: If an admin must reconfigure anything after update, or if existing JS/PHP interfaces change shape → major.

#### MINOR (X.Y.0) — New features, backward compatible

Bump minor when any of these are true:
- **New user-facing feature** that doesn't break existing config
- **New admin UI section** (tab, page, settings panel)
- **New AJAX endpoints** added (existing ones unchanged)
- **New database tables** (no changes to existing tables)
- **Significant UI redesign** of existing features (same functionality, new look)

Examples from history:
| Version | What changed |
|---------|-------------|
| 4.1.0 | Upgrade system, mobile button redesign |
| 4.2.0 | Auto-update from admin UI |
| 4.3.0 | *(next)* TBD — whatever ships next from dev |

**Rule**: If a user would say "there's a new thing I can use" → minor.

#### PATCH (X.Y.Z) — Bug fixes and small tweaks

Bump patch when:
- **Bug fixes** — something was broken, now it works
- **Visual tweaks** — alignment, spacing, color adjustments
- **Text changes** — labels, translations, error messages
- **Performance improvements** — same behavior, faster
- **Code cleanup** — refactoring with no functional change

Examples from history:
| Version | What changed |
|---------|-------------|
| 4.1.1 | Timer unit order reversed (big-then-small) |

**Rule**: If nothing new was added and nothing was restructured → patch.

### Decision Tree

```
Did you change config format, DB schema, or API contracts?
  YES → MAJOR bump
  NO  ↓

Did you add a new feature, UI section, or endpoint?
  YES → MINOR bump
  NO  ↓

Is it a bug fix, visual tweak, or refactor?
  YES → PATCH bump
```

### Pre-release Suffixes

| Branch | Suffix | PHP version_compare behavior |
|--------|--------|------------------------------|
| `dev` | `-dev` | Sorts below release (`4.3.0-dev` < `4.3.0`) |
| `beta` | `-beta.N` | Sorts below RC (`4.3.0-beta.1` < `4.3.0-RC.1`) |
| `rc` | `-RC.N` | Sorts below release (`4.3.0-RC.1` < `4.3.0`) |
| `stable` | *(none)* | Clean semver (`4.3.0`) |

**Important**: Use uppercase `RC`, not lowercase `rc`. PHP's `version_compare()` recognizes `RC` as a special pre-release string and orders it correctly: `dev < alpha < beta < RC < release`.

### Version Lifecycle Example

```
dev:    4.3.0-dev       ← development starts
beta:   4.3.0-beta.1    ← first beta cut
beta:   4.3.0-beta.2    ← beta fix
rc:     4.3.0-RC.1      ← first release candidate
rc:     4.3.0-RC.2      ← RC fix
stable: 4.3.0           ← production release, tag v4.3.0

dev:    4.4.0-dev        ← next cycle starts on dev
```

### Files to Update on Version Bump

Every version change must update these two files:

| File | Field | Example |
|------|-------|---------|
| `plugin.php` | `'version'` | `'4.3.0'` |
| `class.QuickButtonsPlugin.php` | `CURRENT_SCHEMA` | `'4.3.0'` |
| `class.QuickButtonsPlugin.php` | `@version` docblock | `4.3.0` |

**No exceptions.** Every code change, no matter how small, must bump the version.

---

## Commit Messages

```
<type>: <short description>

<optional body explaining why>
```

Types: `feat`, `fix`, `docs`, `chore`, `refactor`, `test`, `style`

### Examples

```
feat: add performance value tracking to workflow builder
fix: timer shows wrong unit order on mobile
chore: bump version to 4.3.0-beta.1
docs: update changelog for v4.3.0
```

---

## Release Checklist

When promoting `rc` → `stable`:

1. Merge `rc` into `stable`
2. Update version to clean semver (remove `-RC.N` suffix)
3. Update `CHANGELOG.md` with release date
4. Commit: `chore: release v4.3.0`
5. Tag: `git tag v4.3.0`
6. Push: `git push origin stable --tags`
7. Create GitHub Release from the tag
8. Back-merge `stable` into `rc` → `beta` → `dev`
9. On `dev`, bump to next version (`4.4.0-dev` or `5.0.0-dev`)
