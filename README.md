# Akeeba Social Login

A social login solution for Joomla!

[Downloads](https://github.com/akeeba/sociallogin/releases) • [Documentation](https://github.com/akeeba/sociallogin/wiki) • [Support](https://www.akeeba.com/support/akeeba-sociallogin.html)

## What does it do?

These plugins let users link their social media (Facebook, Google, Twitter, ...) account to your site. Users can then log in using these social media accounts. A typical use case for that is Facebook login.

Moreover, it allows new users to register to your site using their social media account. For example, someone can create a user account on your site using their Facebook login, without going through Joomla's user registration process. The created user accounts can either be activated immediately (e.g. when it's a verified Facebook account, i.e. Facebook has verified the user's email and/or mobile phone number) or go through Joomla's account activation process (click on the link sent by email). This is faster for the user and easier for you, since in most cases the email address of the user has already been verified by the social network, and they don't have to go through Joomla's email address verification.
 
For more information and documentation for administrators, users and developers please [consult the documentation Wiki](https://github.com/akeeba/sociallogin/wiki).

## Download

Pre-built packages of Akeeba Social Login are available through [our GitHub repository's Releases page](https://github.com/akeeba/sociallogin/releases).

Akeeba Social Login comes with English (Great Britain) built-in, plus machine-translated German (de-DE), Greek (el-GR), Spanish (es-ES), French (fr-FR), Italian (it-IT) and Portuguese (pt-PT) language files. These machine translations are provided as-is, without a guarantee of accuracy, and we do not accept pull requests for language files. You are welcome to translate to your own language and make the translation available free of charge under the GPLv3 license which the original translation files are licensed under.

## Build instructions

Check out this repository and Akeeba Build Tools — Public Packager using the following directory names:

- `sociallogin` This repository.
- `buildfiles` [Akeeba Build Tools — Public Packager](https://github.com/akeeba/buildfiles-public)
- `build.properties` A file created as per the instructions in `buildfiles/README.md`

Then:

```bash
cd sociallogin
composer install
phing git
```

The generated package is under `sociallogin/release`.