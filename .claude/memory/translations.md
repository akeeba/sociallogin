# Translations

## Machine-translation workflow for the INI language files

The source language is **en-GB**. It is canonical: never modify it as part of a translation run.

Target languages: el-GR, fr-FR, de-DE, es-ES, it-IT, pt-PT (set up under GitHub issue gh-144).

Glossaries live in `build/glossaries/{LANG_CODE}.md`. Consult the glossary **before** any translation
run, and add any missing terms to it **after** each run.

Language file locations:

- Package: `build/templates/language/{LANG}/pkg_sociallogin.sys.ini`
- System plugin: `plugins/system/sociallogin/language/{LANG}/plg_system_sociallogin.{ini,sys.ini}`
- Provider plugins: `plugins/sociallogin/{provider}/language/{LANG}/plg_sociallogin_{provider}.{ini,sys.ini}`
  (amazon, apple, auth0, discord, facebook, github, google, linkedin, microsoft, spotify, synology,
  twitch, yahoo)

After creating the language files for a new language, add `<language tag="{LANG}">` entries inside the
`<languages folder="language">` block of every plugin manifest:

- `plugins/sociallogin/{provider}/{provider}.xml` (one per provider)
- `plugins/system/sociallogin/sociallogin.xml`

INI format rules:

- Keys stay unchanged; translate only the values.
- Values are always in double quotes. Internal double quotes are always escaped as `\"` (never the legacy `"_QQ_"` constant).
- Preserve HTML tags, `%s` / `%d` placeholders, `;;` comment lines and blank lines.
- Do **not** translate brand names (Joomla!, Akeeba, the provider names), technical terms (OAuth2, SSO,
  OIDC, Client ID, Client Secret, etc.) or URLs.

**Why:** gh-144 asked for machine translations into these six languages so the extension is usable by
non-English speakers; the glossaries keep terminology consistent across runs, and a language that is not
listed in the manifests is never installed.

**How to apply:** on any translation run (a new language, or updating existing ones): read the glossary →
translate → update the glossary → for a new language, update the XML manifests. Keep the provider list
above in sync with `plugins/sociallogin/` if providers are added or removed.
