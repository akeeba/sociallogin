# Akeeba Social Login

A social login solution for Joomla!

## What does it do?

These plugins let users link their social media (Facebook, Google, Twitter, ...) account to your site. Users can then log in using these social media accounts. A typical use case for that is Facebook login.

Moreover, it allows new users to register to your site using their social media account. For example, someone can create a user account on your site using their Facebook login, without going through Joomla's user registration process. The created user accounts can either be activated immediately (e.g. when it's a verified Facebook account, i.e. Facebook has verified the user's email and/or mobile phone number) or go through Joomla's account activation process (click on the link sent by email). This is faster for the user and easier for you, since in most cases the email address of the user has already been verified by the social network and they don't have to go through Joomla's email address verification.
 
For more information and documentation for administrators, users and developers please [consult the documentation Wiki](https://github.com/akeeba/sociallogin/wiki).

## Download

Pre-built packages of Akeeba LoginGuard are available through [our GitHub repository's Releases page](https://github.com/akeeba/sociallogin/releases).

Akeeba Social Login comes with English (Great Britain) language built-in. We do not offer official translations for any other language nor will we accept pull requests for language files. You are welcome to translate to your own language and make the translation available free of charge under the GPLv3 license which the original translation files are licensed under.

## Support policy

See [our Support resources page](.github/SUPPORT.md) and read our [Code of Conduct](.github/CODE_OF_CONDUCT.md).

## Contributing

Please read [our Contributing page](.github/CONTRIBUTING.md).

## Prerequisites

In order to build the installation packages of this component you will need to have the following tools:

* A command line environment. Using Bash under Linux / Mac OS X works best.
* A PHP CLI binary in your path
* Phing installed account-wide on your machine
* Command line Git executables

You will also need the following path structure inside a folder on your system

* **sociallogin** This repository
* **buildfiles** [Akeeba Build Tools](https://github.com/akeeba/buildfiles)

You will need to use the exact folder names specified here.

### Useful Phing tasks

All commands are to be run from the `build` directory of this repository.

Create a dev release installation package

		phing git
		
The installable ZIP file is written in the `release` directory inside the repository's root.