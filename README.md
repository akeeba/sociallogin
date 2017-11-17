# Akeeba Social Login

A social login solution for Joomla!

## What does it do?

These plugins let users link their social media (Facebook, Google, Twitter, ...) account to your site. Users can then log in using these social media accounts. A typical use case for that is Facebook login.

Moreover, it allows new users to register to your site using their social media account. For example, someone can create a user account on your site using their Facebook login, without going through Joomla's user registration process. The created user accounts can either be activated immediately (e.g. when it's a verified Facebook account, i.e. Facebook has verified the user's email and/or mobile phone number) or go through Joomla's account activation process (click on the link sent by email). This is faster for the user and easier for you, since in most cases the email address of the user has already been verified by the social network and they don't have to go through Joomla's email address verification.
 
For more information and documentation for administrators, users and developers please [consult the documentation Wiki](https://github.com/akeeba/sociallogin/wiki).

## Download

Pre-built packages of Akeeba LoginGuard are available through [our GitHub repository's Releases page](https://github.com/akeeba/sociallogin/releases).

Akeeba SocialLogin comes with English (Great Britain) language built-in. Installation packages for other languages are available [on our language download page](https://cdn.akeebabackup.com/language/sociallogin/index.html).

## No support - For developers only

This software is provided **WITHOUT ANY KIND OF END USER SUPPORT**. You are free to consult [consult the documentation Wiki](https://github.com/akeeba/sociallogin/wiki).

If you are a developer you are free to submit a pull request with your code fix, as long as there is a clear description of what was not working for you, why and how you fixed it. 
 
## Prerequisites

In order to build the installation packages of this component you will need to have the following tools:

* A command line environment. Using Bash under Linux / Mac OS X works best. On Windows you will need to run most tools through an elevated privileges (administrator) command prompt on an NTFS filesystem due to the use of symlinks. Press WIN-X and click on "Command Prompt (Admin)" to launch an elevated command prompt.
* A PHP CLI binary in your path
* Command line Git executables
* Phing
* (Optional) libxml and libsxlt command-line tools, only if you intend on building the documentation PDF files

You will also need the following path structure inside a folder on your system

* **sociallogin** This repository
* **buildfiles** [Akeeba Build Tools](https://github.com/akeeba/buildfiles)
* **translations** [Akeeba Translations](https://github.com/akeeba/translations)

You will need to use the exact folder names specified here.

### Useful Phing tasks

All of the following commands are to be run from the MAIN/build directory.
Lines starting with $ indicate a Mac OS X / Linux / other *NIX system commands.
Lines starting with > indicate Windows commands. The starting character ($ or >)
MUST NOT be typed!

You are advised to NOT distribute the library installation packages you have built yourselves with your components. It
is best to only use the official library packages released by Akeeba Ltd.

1. Relinking internal files

   This is only required when the buildfiles change.

		$ phing link
		> phing link

1. Creating a dev release installation package

   This creates the installable ZIP packages of the component inside the
   MAIN/release directory.

		$ phing git
		> phing git
		
   **WARNING** Do not distribute the dev releases to your clients. Dev releases, unlike regular releases, also use a
   dev version of FOF 3.

1. Build the documentation in PDF format

   This creates the documentation in PDF format

		$ phing doc-j-pdf
		> phing doc-j-pdf


Please note that all generated files (ZIP library packages, PDF files, HTML files) are written to the
`release` directory inside the repository's root.
