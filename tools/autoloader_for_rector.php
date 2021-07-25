<?php
/*
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

// Define ourselves as a parent file

// Try to get the path to the Joomla! installation
$joomlaPath = $_SERVER['HOME'] . '/Sites/dev3';

if (isset($_SERVER['JOOMLA_SITE']) && is_dir($_SERVER['JOOMLA_SITE']))
{
	$joomlaPath = $_SERVER['JOOMLA_SITE'];
}

if (!is_dir($joomlaPath))
{
	echo <<< TEXT


CONFIGURATION ERROR

Your configured path to the Joomla site does not exist. Rector requires loading
core Joomla classes to operate properly.

Please set the JOOMLA_SITE environment variable before running Rector.

Example:

JOOMLA_SITE=/var/www/joomla rector process $(pwd) --config rector.yaml \
  --dry-run

I will now error out. Bye-bye!

TEXT;

	throw new InvalidArgumentException("Invalid Joomla site root folder.");
}

// Required to run the boilerplate FOF CLI code
$originalDirectory = getcwd();
chdir($joomlaPath . '/cli');

// Setup and import the base CLI script
$minphp = '7.4.0';

// Boilerplate -- START
define('_JEXEC', 1);

foreach ([__DIR__, getcwd()] as $curdir)
{
	if (file_exists($curdir . '/defines.php'))
	{
		define('JPATH_BASE', realpath($curdir . '/..'));
		require_once $curdir . '/defines.php';

		break;
	}

	if (file_exists($curdir . '/../includes/defines.php'))
	{
		define('JPATH_BASE', realpath($curdir . '/..'));
		require_once $curdir . '/../includes/defines.php';

		break;
	}
}

defined('JPATH_LIBRARIES') || die ('This script must be placed in or run from the cli folder of your site.');

require_once JPATH_LIBRARIES . '/fof40/Cli/Application.php';
// Boilerplate -- END

// Undo the temporary change for the FOF CLI boilerplate code
chdir($originalDirectory);

// Load FOF 3
if (!defined('FOF40_INCLUDED') && !@include_once(JPATH_LIBRARIES . '/fof40/include.php'))
{
	throw new RuntimeException('FOF 4.0 is not installed', 500);
}

// Other classes
$autoloader = include(__DIR__ . '/../plugins/system/sociallogin/vendor/autoload.php');
$autoloader->addClassMap([
	# Form fields
	'JFormFieldSociallogin'               => __DIR__ . '/../plugins/system/sociallogin/fields/sociallogin.php',

	# Plugins
	'plgSystemSociallogin'                => __DIR__ . '/../plugins/system/sociallogin/sociallogin.php',
	'plgSystemSocialloginInstallerScript' => __DIR__ . '/../plugins/system/sociallogin/script.plg_system_sociallogin.php',
	'plgSocialloginApple'                 => __DIR__ . '/../plugins/sociallogin/apple/apple.php',
	'plgSocialloginAppleRandomWords'      => __DIR__ . '/../plugins/sociallogin/apple/random_words.php',
	'plgSocialloginFacebook'              => __DIR__ . '/../plugins/sociallogin/facebook/facebook.php',
	'plgSocialloginGithub'                => __DIR__ . '/../plugins/sociallogin/github/github.php',
	'plgSocialloginGoogle'                => __DIR__ . '/../plugins/sociallogin/google/google.php',
	'plgSocialloginLinkedin'              => __DIR__ . '/../plugins/sociallogin/linkedin/linkedin.php',
	'plgSocialloginMicrosoft'             => __DIR__ . '/../plugins/sociallogin/microsoft/microsoft.php',
	'plgSocialloginTwitter'               => __DIR__ . '/../plugins/sociallogin/twitter/twitter.php',

	# Deprecated Joomla classes
	'JArrayHelper'                        => $joomlaPath . '/libraries/joomla/utilities/arrayhelper.php',
	'JEventDispatcher'                    => $joomlaPath . '/libraries/joomla/event/dispatcher.php',
]);

if (version_compare(JVERSION, '3.99999.99999', 'le'))
{
	JLoader::registerNamespace('Akeeba\SocialLogin\Features\\', realpath(__DIR__ . '/../plugins/system/sociallogin/Features'), false, false, 'psr4');
	JLoader::registerNamespace('Akeeba\SocialLogin\Library\\', realpath(__DIR__ . '/../plugins/system/sociallogin/Library'), false, false, 'psr4');

	JLoader::registerNamespace('Akeeba\SocialLogin\Facebook\\', realpath(__DIR__ . '/../plugins/sociallogin/facebook/Facebook'), false, false, 'psr4');
	JLoader::registerNamespace('Akeeba\SocialLogin\GitHub\\', realpath(__DIR__ . '/../plugins/sociallogin/github/GitHub'), false, false, 'psr4');
	JLoader::registerNamespace('Akeeba\SocialLogin\Google\\', realpath(__DIR__ . '/../plugins/sociallogin/google/Google'), false, false, 'psr4');
	JLoader::registerNamespace('Akeeba\SocialLogin\LinkedIn\\', realpath(__DIR__ . '/../plugins/sociallogin/linkedin/LinkedIn'), false, false, 'psr4');
	JLoader::registerNamespace('Akeeba\SocialLogin\Microsoft\\', realpath(__DIR__ . '/../plugins/sociallogin/microsoft/Microsoft'), false, false, 'psr4');
}
else
{
	JLoader::registerNamespace('Akeeba\SocialLogin\Twitter\\', realpath(__DIR__ . '/../plugins/sociallogin/twitter/Twitter'), false, false, 'psr4');
	JLoader::registerNamespace('Akeeba\SocialLogin\Features\\', realpath(__DIR__ . '/../plugins/system/sociallogin/Features'), false, false, 'psr4');
	JLoader::registerNamespace('Akeeba\SocialLogin\Library\\', realpath(__DIR__ . '/../plugins/system/sociallogin/Library'), false, false, 'psr4');

	JLoader::registerNamespace('Akeeba\SocialLogin\Facebook\\', realpath(__DIR__ . '/../plugins/sociallogin/facebook/Facebook'), false, false, 'psr4');
	JLoader::registerNamespace('Akeeba\SocialLogin\GitHub\\', realpath(__DIR__ . '/../plugins/sociallogin/github/GitHub'), false, false, 'psr4');
	JLoader::registerNamespace('Akeeba\SocialLogin\Google\\', realpath(__DIR__ . '/../plugins/sociallogin/google/Google'), false, false, 'psr4');
	JLoader::registerNamespace('Akeeba\SocialLogin\LinkedIn\\', realpath(__DIR__ . '/../plugins/sociallogin/linkedin/LinkedIn'), false, false, 'psr4');
	JLoader::registerNamespace('Akeeba\SocialLogin\Microsoft\\', realpath(__DIR__ . '/../plugins/sociallogin/microsoft/Microsoft'), false, false, 'psr4');
	JLoader::registerNamespace('Akeeba\SocialLogin\Twitter\\', realpath(__DIR__ . '/../plugins/sociallogin/twitter/Twitter'), false, false, 'psr4');
}
