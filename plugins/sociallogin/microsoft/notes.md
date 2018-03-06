# Overview 

The **Akeeba Social Login - Microsoft Account integration** plugin allows user on your site to use their Microsoft Account to login or register a user account on your site.

_This feature is available since version 2.0.2._

# Setup on Microsoft 

Before you can use Microsoft Account login on your site you must create a Microsoft Live SDK "app". Even though it sounds scary, a Microsoft Live SDK App is simply a way for you to get a set of access codes which let you identify your site on Microsoft Live.

Go to the [Microsoft Application Registration Portal](https://apps.dev.microsoft.com/#/appList). Under Live SDK applications click on **Add an app** button.

Enter a Name. This what the visitors to your site will see when logging in with their Microsoft account. Click on Create Application.

On the next page copy the Application Id and Application Secret. You will need to enter them in the plugin.

Under Target Domain you must enter your site's domain e.g. `www.example.com`. Do not enter the protocol (e.g. `https://`) or the path to your site. Just the domain name.

Next to Redirect URLs click on Add URL. Enter a URL like `https://dev3.local.web/index.php` where `http://www.example.com` MUST be replaced with your site's URL. For example, if your site is accessible at `https://www.abrandnewsite.com/mysite` then the Callback URL field must be `https://www.abrandnewsite.com/mysite/index.php`. The URL _must_ start with `https://` (it's a Microsoft requirement).

Click on the Save button.

Now go back to your site and edit the plugin.

In the Microsoft Live SDK App Client ID field enter the Application ID you copied above. Likewise, in the Microsoft Live SDK App Secret field enter the Application Secret you copied above.

# Plugin options

**Microsoft Live SDK App Client ID**
Enter the _Application ID_ for your custom Microsoft Live SDK Application here. See the previous section for creating a Microsoft Live SDK Application.

**Microsoft Live SDK App Client Secret**
Enter the _Application Secret_ for your custom Microsoft Live SDK Application here. See the previous section for creating a Microsoft Live SDK Application.

**Allow social login to non-linked accounts**
When enabled allows users to log in despite not having linked their Microsoft account to their site user account. Their Microsoft account's email address must be the same as the email account they use on your site.

**Create new user accounts**
Creates a new Joomla! user when a user tries to log in via Microsoft but there is no Joomla! user account associated with that email or Microsoft User ID. If user registration is disabled no account will be created and an error will be raised. The new Joomla! user will have a username derived from the Microsoft account's name, the same email address as the Microsoft account and a long, random password (which the user can change once they have logged in). Set this to No to prevent creation of user accounts through Microsoft login.

**Ignore Joomla! setting for creating user accounts**
When both this option and the _Create new user accounts_ option above are enabled a new user will always be created, even if you have disabled user registration in the options of Joomla's Users page. This is useful to prevent anyone from registering to your site _unless_ they have a Microsoft account.

**Bypass user validation**
_Only applies when creating new user accounts._ When enabled the new user will be created active, without going through the Joomla! user account validation. This means that no account activation email will be sent to the user or the administrators of the Joomla! site. This makes perfect sense since Microsoft has already verified that the user is in control of the email address they are using with their Microsoft account.

**Button styling**
When enabled custom CSS for login, link and unlink button styling will be output to the page header. Disable this option if you intend to use your own CSS to style the buttons.

**Icon class**
The icon CSS class to use in the login, link and unlink buttons. Useful to use an icon font such as FontAwesome or Glyphicons to render the logo. If it's left empty, a PNG image with the Microsoft logo will be used instead.

# Login flow

https://login.live.com/oauth20_authorize.srf

client_id=CLIENT_ID
scope=SCOPES
response_type=code
redirect_uri=REDIRECT_URI

Scopes
wl.basic enables read access to a user's basic profile info and to the user's list of contacts.
wl.emails enables read access to a user's email addresses.
wl.signin enables single sign-in behavior. Users who are already signed in to Live Connect are also signed in to your app and therefore do not have to enter their credentials.

We get redirected back to our callback with a `code=AUTHORIZATION_CODE` URL parameter

POST https://login.live.com/oauth20_token.srf

Content-type: application/x-www-form-urlencoded

client_id=CLIENT_ID&redirect_uri=REDIRECT_URI&client_secret=CLIENT_SECRET&code=AUTHORIZATION_CODE&grant_type=authorization_code

We get back the tokens

GET https://apis.live.net/v5.0/me?access_token=ACCESS_TOKEN

{
   "id": "8c8ce076ca27823f", 
   "name": "Roberto Tamburello", 
   "first_name": "Roberto", 
   "last_name": "Tamburello", 
   "emails": {
         "preferred": "Roberto@contoso.com",
         "account": "Roberto@contoso.com", 
         "personal": "Roberto@fabrikam.com", 
         "business": "Robert@adatum.com",
         "other": "Roberto@adventure-works.com"
      },
}
