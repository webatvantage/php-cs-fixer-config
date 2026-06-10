---
name: github-release
description: Create a GitHub release for this repo. Use when the user wants to publish a new version, cut a release, or tag a release. Title is the bare version (no v prefix); body is grouped under "## Updates" and "## Fixes" listing commit subjects since the previous tag. Always dumps a preview and requires explicit confirmation before publishing.
---

# github-release

Cut a GitHub release for this repo with a consistent shape.

**Non-negotiables:**

- The release title is the bare version number, no `v` prefix (e.g. `1.0.0`).
- The git tag is the same bare version (no `v` prefix).
- The body has at most two H2 sections: `## Updates` and `## Fixes`. Skip a section entirely if it would be empty.
- Always render a preview and wait for explicit user confirmation. Never publish silently.

The skill may be invoked with an optional version argument, e.g. `/github-release 1.0.0`. If no argument is given, prompt the user.

## Playbook

Follow these steps in order. If any preflight check fails, stop and report — do not try to repair the user's environment.

### 1. Preflight

```bash
gh auth status
git status --porcelain
git rev-parse --abbrev-ref HEAD
```

- If `gh auth status` reports not authenticated: stop and tell the user to run `gh auth login`.
- If `git status --porcelain` is non-empty: stop. Releasing with a dirty tree silently bakes uncommitted changes into the world. Tell the user to commit or stash first.
- If the current branch is not `main`: warn the user but continue if they confirm.

### 2. Determine the since-ref

```bash
git tag --sort=-version:refname | head -1
```

- If a tag is returned, use it as `<since-ref>`.
- If empty (no prior releases), use the initial commit as the since-ref: `git rev-list --max-parents=0 HEAD | tail -1`. In the preview, note "first release".

### 3. Collect the new version

- If a version argument was passed, use it.
- Otherwise ask the user via `AskUserQuestion` or a plain prompt.
- Validate: must match `^\d+\.\d+\.\d+$`. Pre-releases (`1.0.0-rc.1`) are not supported by this skill — reject them and ask the user to use `gh release create` directly if they need that.
- Reject if the tag already exists:

  ```bash
  git tag -l <version>          # must be empty
  git ls-remote --tags origin <version>   # must be empty
  ```

### 4. Collect commits

```bash
git log --no-merges --pretty=format:"%s" <since-ref>..HEAD
```

Drop these lines:

- Exact match `Fix styling` — the CI auto-format commit from `.github/workflows/php-cs-fixer.yml`'s `stefanzweifel/git-auto-commit-action` step.
- Empty lines.

### 5. Group commits

- Subject matches `^[Ff]ix\b` → **Fixes**.
- Otherwise → **Updates**.
- Preserve the original git log order within each section (newest first).

### 6. Render preview

Output as plain text in the chat — not a tool call, just text the user can read. Format:

```
Title:  <version>
Tag:    <version>
Target: HEAD (<short-sha>)

## Updates
- <commit subject>
- <commit subject>

## Fixes
- <commit subject>
```

Skip the `## Updates` or `## Fixes` header entirely if its list is empty. If both sections are empty, stop and tell the user there's nothing to release since `<since-ref>`.

### 7. Confirm

Use `AskUserQuestion` with these options:

- **Publish** — proceed to step 8.
- **Edit notes** — ask the user what to change, apply edits to the in-memory body, then re-render the preview and ask again.
- **Cancel** — stop. Print "Cancelled, nothing published."

### 8. Publish

```bash
gh release create <version> \
    --title "<version>" \
    --notes "<body>" \
    --target HEAD
```

`gh` creates the tag and pushes it as part of release creation, so no separate `git tag` / `git push --tags` is needed.

Pass the body via a heredoc to avoid quoting issues:

```bash
gh release create <version> --title "<version>" --target HEAD --notes "$(cat <<'EOF'
## Updates
- ...

## Fixes
- ...
EOF
)"
```

### 9. Report

```bash
gh release view <version> --json url -q .url
```

Print the URL so the user can open the release in the browser.

## Example output

For a first release at `1.0.0` cut from the current `main` (where commits include `Add PropertyHookBracesFixer`, `Expand PHP version matrix in php-cs-fixer workflow`, and `Fix wrong php cs fixer version number`):

```
Title:  1.0.0
Tag:    1.0.0
Target: HEAD (fd076c4)
First release — since-ref is the initial commit.

## Updates
- Add PropertyHookBracesFixer
- Expand PHP version matrix in php-cs-fixer workflow
- Add function opening brace position
- Update composer lock file
- Update workflow: remove windows
- Update workflow
- Update workflow
- Add format workflow
- Update readme
- Initial commit

## Fixes
- Fix wrong php cs fixer version number
```

## Failure modes

| Situation                                                   | Action                                                                                                                    |
|-------------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------------|
| `gh` not authenticated                                      | Stop. Suggest `gh auth login`.                                                                                            |
| Working tree dirty                                          | Stop. Suggest committing or stashing.                                                                                     |
| Version doesn't match `^\d+\.\d+\.\d+$`                     | Stop. Show the regex; suggest re-invoking with a valid version.                                                           |
| Tag already exists locally or on remote                     | Stop. Suggest picking a higher version or deleting the existing tag if it was a mistake (don't do the deletion yourself). |
| Zero commits between `<since-ref>` and HEAD after filtering | Stop. Tell the user there's nothing to release.                                                                           |
| User cancels at confirmation                                | Stop quietly. Print "Cancelled, nothing published."                                                                       |