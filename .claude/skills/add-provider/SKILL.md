---
name: add-provider
description: Use when adding a new social network provider plugin (a new OAuth2/OpenID Connect login button) to Akeeba Social Login — the required file structure, the methods to implement, and the manifest and package registration steps.
---

# Adding a New Social Login Provider

Providers live in `plugins/sociallogin/{provider}/`. Facebook is the best template to copy: it is a plain
OAuth2 provider with no unusual quirks. Apple and Google are the templates to copy for OpenID Connect
providers, since they use `OpenIDConnectTrait` for ID token validation.

## Steps

1. Create `plugins/sociallogin/{provider}/`, following the structure of an existing provider.
2. Implement `src/Extension/Plugin.php` extending `AbstractPlugin` with the four required methods:
   `init()`, `getConnector()`, `getSocialNetworkProfileInformation()`, `mapSocialProfileToUserData()`.
3. Create `src/Integration/OAuth.php` extending `OAuth2Client` with the provider's endpoint URLs.
4. Create `src/Integration/User.php` for the API user profile calls.
5. Add language files in `language/en-GB/`.
6. Add the provider logo SVG in `media/images/`.
7. Create the plugin XML manifest with the configuration fields (App ID / App Secret and the login
   behaviour flags).
8. Add the new plugin to `pkg_sociallogin.xml` so it ships in the package.

## Reference file layout

Copying Facebook gives you:

```
plugins/sociallogin/facebook/
  services/provider.php                     Joomla DI service provider
  src/Extension/Plugin.php                  main class
  src/Integration/OAuth.php                 endpoints, extends OAuth2Client
  src/Integration/User.php                  profile API wrapper
  src/Integration/AbstractFacebookObject.php  provider-specific helper (optional)
```

## Notes

- `mapSocialProfileToUserData()` must return a `UserData` object; the `verified` flag on it decides whether
  the account can bypass Joomla's email validation, so only set it when the provider genuinely asserts a
  verified email address.
- The OAuth callback reaches the plugin as `onAjax{ProviderName}` — the name segment must match what the
  system plugin's magic routing produces from the URL, so keep the plugin's element name, the button's
  provider slug and the AJAX method name consistent.
