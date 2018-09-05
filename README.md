# Akeeba Social Login

## THIS PRODUCT IS DISCONTINUED AS OF SEPTEMBER 2018

I originally wrote this set of plugins with the intention of using it on our site to facilitate sign-up of new users and offer an alternative method for existing users to log in. In short, I wanted frictionless sign-up and log in to our site.

Then, GDPR happened. And with GDPR here comes ePrivacy to complicate things.

It is not entirely clear whether your the identified returned by the social network and stored on our site is personally identifiable information (PII) or not. If it is, we have a problem. Let's say that you linked your Facebook account to your login. At some later point you decided to revoke your consent for us using your PII. If we let you log in with Facebook we are processing your PII to log you in. We don't know about your revocation of consent before processing your PII. However, having processed your PII is illegal. So just the fact that we let you log in with a social network account might be illegal. The potential fine is too high to risk it.

Then we have ePrivacy. We can't even let you use social login to log in or, worse, create an account unless you have already _actively_ accepted the terms of service and sharing your information with third parties. In other words you need to provide consent before logging in. This nullifies the whole consept of frictionless sign-up and logging in. Filling in a regular login or signup form is easier than having you jump through hoops.

So, the reason I wrote this code no longer exists. I have no use of that code anymore.

On top of that wwe have to deal with Facebook, Twitter and Google changing their interfaces all the time. 98% of the time I spend on SocialLogin is answering posts, issues and emails regarding how these third party services' interfaces work. I am providing free support for the services of multinationals making billions using up time I should rather be spending on the software that pays the bills.

As a result I have decided to discontinue SocialLogin on September 2018. The repository on GitHub will be archived and will no longer accept issues, pull requests and code commits.
