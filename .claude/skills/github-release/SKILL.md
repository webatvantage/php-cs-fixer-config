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
git fetch --tags
git status --porcelain
git rev-parse --abbrev-ref HEAD
```

`git fetch --tags` is required — local tag list may be empty even when releases exist on the remote.

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
git log --no-merges --pretty=format:"%H %s" <since-ref>..HEAD
git remote get-url origin    # to build commit & compare URLs
```

Capture each commit as a `(sha, subject)` pair — the full SHA is needed for the links.

Drop these lines:

- Exact match `Fix styling` — the CI auto-format commit from `.github/workflows/php-cs-fixer.yml`'s `stefanzweifel/git-auto-commit-action` step.
- Empty lines.
- Anything the user later asks you to drop during "Edit notes".

Derive the repo's `https://github.com/<owner>/<repo>` URL by stripping `.git` from the origin URL (and normalising `git@github.com:owner/repo` SSH form to HTTPS).

### 5. Group commits

- Subject matches `^[Ff]ix\b` → **Fixes**.
- Otherwise → **Updates**.
- Preserve the original git log order within each section (newest first).

### 6. Render preview

Output as plain text in the chat — not a tool call, just text the user can read. Each bullet is a markdown link from the commit subject to `https://github.com/<owner>/<repo>/commit/<full-sha>`. End with a **Full Changelog** footer linking to the `compare/<since-ref>...<version>` URL.

```
Title:  <version>
Tag:    <version>
Target: main (<short-sha>)
Since:  <since-ref>

## Updates
- [<commit subject>](https://github.com/<owner>/<repo>/commit/<full-sha>)
- [<commit subject>](https://github.com/<owner>/<repo>/commit/<full-sha>)

## Fixes
- [<commit subject>](https://github.com/<owner>/<repo>/commit/<full-sha>)

**Full Changelog**: https://github.com/<owner>/<repo>/compare/<since-ref>...<version>
```

Skip the `## Updates` or `## Fixes` header entirely if its list is empty. If both sections are empty, stop and tell the user there's nothing to release since `<since-ref>`. The Full Changelog footer is always included as long as there is at least one section.

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
    --target main
```

`gh` creates the tag and pushes it as part of release creation, so no separate `git tag` / `git push --tags` is needed.

Pass the body via a heredoc to avoid quoting issues:

```bash
gh release create <version> --title "<version>" --target main --notes "$(cat <<'EOF'
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

Cutting `1.1.0` on top of `1.0.2` with two non-fix commits in between:

```
Title:  1.1.0
Tag:    1.1.0
Target: main (280d388)
Since:  1.0.2

## Updates
- [Expand PHP version matrix in php-cs-fixer workflow](https://github.com/webatvantage/php-cs-fixer-config/commit/fd076c49f0d87354498cbc8cc979e2a4ef34b142)
- [Add PropertyHookBracesFixer](https://github.com/webatvantage/php-cs-fixer-config/commit/4d0e82ab9ed3b9c3ceb82eac0c357990871418d2)

**Full Changelog**: https://github.com/webatvantage/php-cs-fixer-config/compare/1.0.2...1.1.0
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