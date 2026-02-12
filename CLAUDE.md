# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Akeeba Social Login is a Joomla extension package (`pkg_sociallogin`) that enables OAuth2/OpenID Connect authentication via social providers (Facebook, Google, GitHub, Apple, Microsoft, Amazon, LinkedIn, Discord, Spotify, Twitch, Yahoo, Auth0, Synology).

License: GPL-3.0-or-later. Requires PHP 8.1+, Joomla 4/5/6.

## Build Commands

**Build release package** (run from repo root):
```bash
phing git
```
This creates an installable ZIP in the `release/` directory. Requires Phing installed globally and the [Akeeba Build Tools](https://github.com/akeeba/buildfiles) repo cloned as a sibling directory named `buildfiles`.

**Compile JavaScript** (ES6 to minified ES5 via Babel):
```bash
phing compile-javascript
```
Source files: `plugins/system/sociallogin/media/js/*.js` (non-`.min.js`). Output: corresponding `.min.js` files. Babel config is in `.babelrc.json`. The Babel binary comes from `../buildfiles/node_modules/.bin/babel`.

**Vendor dependencies** (Mozart namespacing):
```bash
composer install
```
Runs Mozart automatically via post-install script to namespace third-party libraries under `Akeeba\Plugin\System\SocialLogin\Dependencies\` into `plugins/system/sociallogin/src/Dependencies/`.

## Architecture

### Two-Tier Plugin System

**System Plugin** (`plugins/system/sociallogin/`) — the infrastructure layer:
- `src/Extension/SocialLogin.php`: Main entry point, composes feature traits
- `src/Features/Ajax.php`: Routes OAuth callbacks via com_ajax
- `src/Features/ButtonInjection.php`: Injects login buttons into Joomla login forms
- `src/Features/DynamicUsergroups.php`: Assigns user groups based on linked accounts
- `src/Features/UserFields.php`: Adds social login fields to user profile forms
- `src/Library/`: Shared library code used by all provider plugins

**Provider Plugins** (`plugins/sociallogin/{provider}/`) — one per social network:
- Each extends `AbstractPlugin` (`src/Library/Plugin/AbstractPlugin.php`)
- Standard structure per provider:
  - `services/provider.php` — Joomla DI service provider
  - `src/Extension/Plugin.php` — Main class, implements `init()`, `getConnector()`, `getSocialNetworkProfileInformation()`, `mapSocialProfileToUserData()`
  - `src/Integration/OAuth.php` — Extends `OAuth2Client` with provider-specific endpoints
  - `src/Integration/User.php` — Wraps provider API calls for user profile data

### Shared Library (`plugins/system/sociallogin/src/Library/`)

- `OAuth/OAuth2Client.php`: Base OAuth2 flow (authorization URL, token exchange, refresh)
- `OAuth/OpenIDConnectTrait.php`: OpenID Connect JWT ID token validation
- `Plugin/AbstractPlugin.php`: Base class for all provider plugins
- `Plugin/LoginTrait.php`: Core login logic — user lookup, account linking, new user creation, activation workflow
- `Data/UserData.php`: Value object for social profile data (id, name, email, verified, timezone)
- `Data/PluginConfiguration.php`: Login behavior flags (canLoginUnlinked, canCreateNewUsers, canBypassValidation)
- `Helper/Integrations.php`: Public API for retrieving button definitions from providers
- `Helper/Ajax.php`: AJAX request routing to provider plugins

### Authentication Flow

1. User clicks social login button (injected by `ButtonInjection` trait)
2. Button links to provider's OAuth authorization URL via com_ajax
3. System plugin's `onAfterInitialise` intercepts callback URLs (`/aksociallogin_finishLogin/{provider}/`) via "magic routing" and converts them to com_ajax requests
4. Provider plugin's `onAjax{ProviderName}` handler exchanges auth code for access token
5. Provider fetches user profile from social network API
6. Profile mapped to `UserData` object
7. `LoginTrait` matches to existing Joomla user (by linked social ID or email) or creates new account
8. User session established

### Key Joomla Events

- `onAfterInitialise`: Magic URL routing for OAuth callbacks
- `onUserLoginButtons`: Button injection into login forms
- `onAjax{ProviderName}`: Provider-specific OAuth callback handling
- `onSocialLoginGetLoginButton` / `onSocialLoginGetLinkButton`: Button definition retrieval
- `onSocialLoginUnlink`: Account unlinking
- `onContentPrepareForm` / `onContentPrepareData` / `onUserAfterSave` / `onUserAfterDelete`: User profile integration

### User Data Storage

Social login data is stored in Joomla's `#__user_profiles` table:
- `sociallogin.{provider}.userid` — Social network user ID (used for account matching)
- `sociallogin.{provider}.token` — JSON-encoded OAuth2 token
- `sociallogin.{provider}.pictureUrl` — Profile picture URL

## Adding a New Provider

1. Create `plugins/sociallogin/{provider}/` following the structure of an existing provider (Facebook is a good template)
2. Implement `Plugin.php` extending `AbstractPlugin` with the four required methods
3. Create `Integration/OAuth.php` extending `OAuth2Client` with the provider's endpoint URLs
4. Create `Integration/User.php` for API user profile calls
5. Add language files in `language/en-GB/`
6. Add provider logo SVG in `media/images/`
7. Create the plugin XML manifest with configuration fields
8. Add the new plugin to `pkg_sociallogin.xml`
