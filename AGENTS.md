# AGENTS.md

This file provides guidance to coding agents when working with code in this repository.

## Project Overview

Akeeba Social Login is a Joomla extension package (`pkg_sociallogin`) that enables OAuth2/OpenID Connect
authentication via social providers. It supports Joomla 5.4 to 6.2, and PHP 8.1 to 8.6.

## Build Commands

Builds run through Phing. See the `akeeba-project-management:phing-build` skill for targets, `build.properties`,
and the requirement that the Akeeba Build Tools repo is cloned as a sibling directory named `buildfiles`.

## Architecture

### Two-Tier Plugin System

The **system plugin** (`plugins/system/sociallogin/`) is the infrastructure layer: it routes OAuth callbacks,
injects login buttons, handles user profile fields and dynamic user groups, and hosts the shared library under
`src/Library/` that every provider plugin builds on.

The **provider plugins** (`plugins/sociallogin/{provider}/`) are one per social network. Each extends
`AbstractPlugin` and implements `init()`, `getConnector()`, `getSocialNetworkProfileInformation()` and
`mapSocialProfileToUserData()`, plus an `Integration/OAuth.php` (provider endpoints) and `Integration/User.php`
(profile API calls).

The shared library's load-bearing pieces are `OAuth/OAuth2Client.php` (base OAuth2 flow),
`OAuth/OpenIDConnectTrait.php` (ID token validation) and `Plugin/LoginTrait.php` (user lookup, account linking,
new user creation, activation workflow).

### Authentication Flow

1. User clicks social login button (injected by `ButtonInjection` trait)
2. Button links to provider's OAuth authorization URL via com_ajax
3. System plugin's `onAfterInitialise` intercepts callback URLs (`/aksociallogin_finishLogin/{provider}/`) via "magic routing" and converts them to com_ajax requests
4. Provider plugin's `onAjax{ProviderName}` handler exchanges auth code for access token
5. Provider fetches user profile from social network API
6. Profile mapped to `UserData` object
7. `LoginTrait` matches to existing Joomla user (by linked social ID or email) or creates new account
8. User session established

### User Data Storage

Social login data is stored in Joomla's `#__user_profiles` table:
- `sociallogin.{provider}.userid` — Social network user ID (used for account matching)
- `sociallogin.{provider}.token` — JSON-encoded OAuth2 token
- `sociallogin.{provider}.pictureUrl` — Profile picture URL

## Git: commit and tag outside the sandbox

Commits and tags are always signed, with a key held in 1Password. The 1Password signing agent is reached
over a local socket that agent sandboxes do not expose, so a sandboxed `git commit` or `git tag` **always**
fails (e.g. `error: 1Password: Could not connect to socket. Is the agent running?`).

Run every `git commit` and `git tag` **outside the sandbox from the first attempt** — in Claude Code with
`dangerouslyDisableSandbox: true`, in other harnesses with their equivalent unsandboxed / escalated
execution. Do not try the sandboxed form first, do not diagnose the failure, and never work around it
with `--no-gpg-sign`, `-c commit.gpgsign=false` or unsigned tags.

## Project memory

Project memory lives in `.claude/memory/`, committed with the code, so that it is shared across machines
and across agentic harnesses (Claude Code, Codex, Qwen Code, Kimi Code, Junie, …). Read the relevant file
**before** starting work that matches its trigger:

| Before you… | Read |
|---|---|
| add, change or translate language strings, or add a new language | `.claude/memory/translations.md` |
| run, triage or act on a security audit, or touch composer dependencies / the Mozart setup | `.claude/memory/security-audit-decisions.md` |

### Recording new memories

This is the **default and only** place for project memory. Do not write memories for this project to a
harness's private memory store (such as Claude Code's auto-memory under `~/.claude/projects/`); write
them here instead:

- Add to the existing topic file when one fits; otherwise create a new kebab-case `.md` file named after
  the topic, and add a row for it to the table above with a concrete trigger.
- Plain Markdown, no frontmatter. State the rule, then **Why:** (the reason or incident behind it) and
  **How to apply:**. Link related files with relative Markdown links.
- Don't record what the code, Git history or an existing `AGENTS.md` already says — update that
  `AGENTS.md` instead when the rule belongs there. Remove or correct entries that turn out wrong.
- These files are committed: no secrets, credentials, customer data or personal details.
