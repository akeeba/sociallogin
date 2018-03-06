# Overview 

The **Akeeba Social Login - LinkedIn integration** plugin allows user on your site to use their LinkedIn account to login or register a user account on your site.

# Setup on LinkedIn 

Before you can use LinkedIn login on your site you must create a LinkedIn "app". Even though it sounds scary, a LinkedIn App is simply a way for you to get a set of access codes which let you identify your site on LinkedIn.

Start by logging in to LinkedIn.

Go to [My Applications](https://www.linkedin.com/secure/developer?newapp=) and click the **Create an application** button found in the page's header.

Fill in the information requested by LinkedIn. All of the fields are required. This information is shown to your site's visitors. Choose something which explains to them that they are logging into your site. Click on Save.

On the next page copy the Client ID and Client Secret. You will need to enter them in the plugin.

Check the `r_basicprofile` and `r_emailaddress` boxes under Default Application Permissions. Both are required for using SocialLogin with LinkedIn.

Under OAuth 2.0 find the the **Authorized Redirect URLs** field and enter a URL like `http://www.example.com/index.php?option=com_ajax&group=sociallogin&plugin=linkedin&format=raw` where `http://www.example.com` MUST be replaced with your site's URL. For example, if your site is accessible at `http://www.abrandnewsite.com/mysite` then the Callback URL field must be `http://www.abrandnewsite.com/mysite/index.php?option=com_ajax&group=sociallogin&plugin=linkedin&format=raw`. Remember to click on the **Add** button for LinkedIn to see the URL you have entered.

Click on the Update button.

Now go back to your site and edit the plugin.

In the LinkedIn Client ID field enter the Client ID you copied above. Likewise, in the LinkedIn Client Secret field enter the Client Secret you copied above.

# Plugin options

**LinkedIn Client ID**
Enter the _Client ID_ for your custom LinkedIn OAuth Application here. See the previous section for creating a LinkedIn Application.

**LinkedIn Client Secret**
Enter the _Consumer Secret (API Secret)_ for your custom LinkedIn OAuth Application here. See the previous section for creating a LinkedIn Application.

**Allow social login to non-linked accounts**
When enabled allows users to log in despite not having linked their LinkedIn account to their site user account. Their LinkedIn account's email address must be the same as the email account they use on your site.

**Create new user accounts**
Creates a new Joomla! user when a user tries to log in via LinkedIn but there is no Joomla! user account associated with that email or LinkedIn User ID. If user registration is disabled no account will be created and an error will be raised. The new Joomla! user will have a username derived from the LinkedIn account's name, the same email address as the LinkedIn account and a long, random password (which the user can change once they have logged in). Set this to No to prevent creation of user accounts through LinkedIn login.

**Ignore Joomla! setting for creating user accounts**
When both this option and the _Create new user accounts_ option above are enabled a new user will always be created, even if you have disabled user registration in the options of Joomla's Users page. This is useful to prevent anyone from registering to your site _unless_ they have a LinkedIn account.

**Bypass user validation**
_Only applies when creating new user accounts._ When enabled the new user will be created active, without going through the Joomla! user account validation. This means that no account activation email will be sent to the user or the administrators of the Joomla! site. This makes perfect sense since LinkedIn has already verified that the user is in control of the email address they are using with their LinkedIn account.

**Button styling**
When enabled custom CSS for login, link and unlink button styling will be output to the page header. Disable this option if you intend to use your own CSS to style the buttons.

**Icon class**
The icon CSS class to use in the login, link and unlink buttons. Useful to use an icon font such as FontAwesome or Glyphicons to render the logo. If it's left empty, a PNG image with the LinkedIn logo will be used instead.

# Login flow

## Get authorization code

GET https://www.linkedin.com/oauth/v2/authorization

Parameters
response_type=code
client_id=API Key
redirect_uri=http://www.example.com/index.php?option=com_ajax&group=sociallogin&plugin=linkedin&format=raw
state=CSRF token
scope=r_fullprofile%20r_emailaddress

## User is redirected (callback with `error` or `code` present)

If we have an `error` the login was rejected. It can be either of
* `user_cancelled_login` The user refused to login into LinkedIn account.
* `user_cancelled_authorize` The user refused to authorize permissions request from your application.

If the login was accepted we get back `code` and `state`.

POST https://www.linkedin.com/oauth/v2/accessToken

Parameters
grant_type=authorization_code
code=what we got back from LinkedIn
redirect_uri=The same redirect URI as before
client_id=API Key
client_secret=Secret Key

## We get back an access token (callback with `access_token` present)

We get an `access_token` token and `expires_in` (how long it's valid in seconds).

IMPORTANT: The access token is ~500 characters long but it may increase up to 1000 characters in the future.

## Retrieve basic profile information

GET https://api.linkedin.com/v1/people/~:(id,first-name,last-name,email-address,picture-url)?format=json

Authorization: Bearer THE_TOKEN_GOES_HERE


sample api response
{
  "emailAddress": "frodo@example.com",
  "firstName": "Frodo",
  "id": "1R2RtA",
  "lastName": "Baggins",
  "pictureUrl": "https://media.licdn.com/mpr/mprx/…"
}

The ID is specific to the user *and* our application.

Since we do not get a refresh token we can't use the pictureUrl unless we cache it to the database (in a hidden user profile field) every time the user logs in with LinkedIn..
