# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Akeeba Social Login is a Joomla extension package (`pkg_sociallogin`) that enables OAuth2/OpenID Connect
authentication via social providers. It supports Joomla 4, 5 and 6.

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
