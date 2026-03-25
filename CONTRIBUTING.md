# Contributing to ost-quick-buttons

## Branching Strategy

This project follows **Git Flow**:

```
master          ← stable releases only (tagged)
  └── develop   ← integration branch
       └── feature/*   ← new features
       └── fix/*       ← bug fixes
       └── chore/*     ← maintenance, docs, cleanup
```

### Rules

- **Never commit directly to `master`** — always merge via PR from `develop`
- **Never commit directly to `develop`** — always merge via PR from a feature/fix branch
- **All work happens in feature branches** created from `develop`
- **Releases**: merge `develop` → `master`, tag with version, create GitHub Release

### Workflow

1. Create a branch from `develop`:
   ```bash
   git checkout develop
   git pull origin develop
   git checkout -b feature/my-feature
   ```

2. Make your changes and commit:
   ```bash
   git add <files>
   git commit -m "Add my feature"
   ```

3. Push and create a Pull Request to `develop`:
   ```bash
   git push origin feature/my-feature
   gh pr create --base develop --title "Add my feature"
   ```

4. After review, merge the PR into `develop`

5. When ready for release, create a PR from `develop` to `master`

### Versioning

Follows [Semantic Versioning](https://semver.org/):
- **MAJOR** (v3.0.0) — breaking changes to config format or API
- **MINOR** (v2.7.0) — new features, backward compatible
- **PATCH** (v2.6.1) — bug fixes only

### Commit Messages

Use clear, descriptive commit messages:
```
<type>: <short description>

<optional body explaining why>
```

Types: `feat`, `fix`, `docs`, `chore`, `refactor`, `test`, `style`
