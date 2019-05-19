# 3.0.1

**Added features**

* Dynamically add a logged in user to a user group if they have not yet linked any social media presence to their Joomla user account.

**Removed features**

* Removed Login with LinkedIn. The v2 API does not provide enough information to log in unlinked accounts or create new user accounts.

**Bug fixes**

* User profile fields do not appear when LoginGuard is also enabled
* User Profile fields not displayed correctly when using an Edit Profile menu item
* Some servers return lowercase the content-type header instead of Content-Type.
* Facebook login could not create new users

# 3.0.0

**New features**

* Login with Google using Google OpenID instead of the now defunct Google+ API
* Debug logs for all Social Login plugins
* Social Login buttons CSS is now loaded through .css files instead of inline styling for better performance
* Social Login buttons can now be relocated next to the Login button in the login module using a bit of Javascript magic
* Social Login buttons in the frontend login page (rendered by Joomla's com_users)

# 2.0.3

**New features**

* Login with LinkedIn
* Login with Microsoft Account

# 2.0.2

**New features**

* Login with GitHub

**Bug fixes**

* Removed "Add buttons to login page" option since it doesn't do anything useful. You MUST use template overrides for the login page. 
* Missing file from the plugin manifest causes installation failure

# 2.0.1

**Bug fixes**

* Language files were not included in the package
* SocialLogin buttons disappear on update if you are using template overrides becayse the SocialLoginHelperIntegrations class was renamed.

# 2.0.0

**New features**

* Compatible with Joomla! 3.4 to 3.8 (inclusive) and, now, also Joomla! 4.0.

**Bug fixes**

* gh-16 The buttons should not appear in the back-end of the site by default 
* gh-24 Validated user accounts still result in Joomla! email validation email being sent  
* Fatal error displaying the site's error page

# 1.0.2

**Bug fixes**

* gh-15 Twitter and Google plugins are not installed

# 1.0.1

**Bug fixes**

* Cannot create user account from social network login
* Email verification sent with unusable, untranslated strings
* Cannot log in under Joomla! 3.7

# 1.0.0

**New features**

* Login to Joomla! using your Facebook account
* Login to Joomla! using your Google account
* Login to Joomla! using your Twitter account
