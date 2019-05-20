## Release highlights
 
Dynamically add a logged in user to a user group if they have not yet linked any social media presence to their Joomla 
user account. This optional feature allows you to display a module or other interface element to ask the user to link
their social media presence with their user account, making it easier for them to log into your site if they forget
their password.

The LinkedIn integration has been removed. LinkedIn v2 API -- forced upon all users since December 2018 -- no longer
returns an email address. As a result it is impossible to create new user accounts or log in users who have not yet
linked their Joomla user account with their LinkedIn presence. In lack of a workable solution we decided to remove this
integration altogether.

We fixed a handful of bugs. Most importantly, we addressed an issue with the new Facebook API that prevented SocialLogin
from creating new Joomla user accounts.
 
For more information and documentation for administrators, users and developers please [consult the documentation Wiki](https://github.com/akeeba/sociallogin/wiki).
 
## Joomla and PHP Compatibility

Akeeba Social Login is compatible with Joomla! 3.8 and 3.9. It's also compatible with PHP 5.6, 7.0, 7.1, 7.2 and 7.3.

We strongly recommend using the latest published Joomla! version and PHP 7.2 _for optimal security of your site_.

## Changelog

**Added features**

* Dynamically add a logged in user to a user group if they have not yet linked any social media presence to their Joomla user account.

**Removed features**

* Removed Login with LinkedIn. The v2 API does not provide enough information to log in unlinked accounts or create new user accounts.

**Bug fixes**

* User profile fields do not appear when LoginGuard is also enabled
* User Profile fields not displayed correctly when using an Edit Profile menu item
* Some servers return lowercase the content-type header instead of Content-Type.
* Facebook login could not create new users
