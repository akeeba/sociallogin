<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Akeeba\SocialLogin\Tests\Unit\OAuth;

use Akeeba\Plugin\System\SocialLogin\Library\Helper\BackendCallback;
use PHPUnit\Framework\TestCase;

/**
 * OAuth callbacks of backend login / link flows arrive at the frontend, which forwards them to the backend.
 */
class BackendCallbackTest extends TestCase
{
	/**
	 * What Google sends back to the callback URL.
	 */
	private const GOOGLE_CALLBACK = [
		'option'   => 'com_ajax',
		'group'    => 'sociallogin',
		'plugin'   => 'google',
		'format'   => 'raw',
		'state'    => 'admin-0123456789abcdef0123456789abcdef',
		'iss'      => 'https://accounts.google.com',
		'code'     => 'dummy',
		'scope'    => 'email profile https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email openid',
		'authuser' => '0',
		'prompt'   => 'consent',
	];

	protected function setUp(): void
	{
		require_once dirname(__DIR__, 3) . '/plugins/system/sociallogin/src/Library/Helper/BackendCallback.php';
	}

	public function testAdminCallbackIsForwardedToTheBackend(): void
	{
		$query = BackendCallback::getBackendQuery(self::GOOGLE_CALLBACK, self::GOOGLE_CALLBACK);

		$this->assertNotNull($query);
		$this->assertSame(
			['option' => 'com_ajax', 'group' => 'sociallogin', 'plugin' => 'google', 'format' => 'raw'],
			array_slice($query, 0, 4, true)
		);
		$this->assertSame(self::GOOGLE_CALLBACK['state'], $query['state']);
		$this->assertSame('dummy', $query['code']);
		$this->assertSame('https://accounts.google.com', $query['iss']);
	}

	public function testFrontendCallbackIsNotForwarded(): void
	{
		$vars          = self::GOOGLE_CALLBACK;
		$vars['state'] = '0123456789abcdef0123456789abcdef';

		$this->assertNull(BackendCallback::getBackendQuery($vars, $vars));
	}

	public function testOtherRequestsAreNotForwarded(): void
	{
		$vars           = self::GOOGLE_CALLBACK;
		$vars['option'] = 'com_content';

		$this->assertNull(BackendCallback::getBackendQuery($vars, $vars));

		$vars          = self::GOOGLE_CALLBACK;
		$vars['group'] = 'system';

		$this->assertNull(BackendCallback::getBackendQuery($vars, $vars));
	}

	/**
	 * Some server firewalls reject a request having ".profile" in any parameter (it looks like an attempt to read a
	 * shell profile file). Google's returned scope always has it. When the 403 error document is served by Joomla the
	 * query string is gone from PHP's request variables; only the request URI still has it.
	 */
	public function testCallbackWithStrippedQueryStringIsStillForwarded(): void
	{
		$query = BackendCallback::getBackendQuery([], self::GOOGLE_CALLBACK);

		$this->assertNotNull($query);
		$this->assertSame('google', $query['plugin']);
		$this->assertSame(self::GOOGLE_CALLBACK['state'], $query['state']);
		$this->assertSame('dummy', $query['code']);
	}

	/**
	 * The backend does not rebuild the request variables from the URI, so forwarding the scope would make the server
	 * firewall strip the entire backend request. Nothing uses the returned scope.
	 */
	public function testScopeIsNotForwarded(): void
	{
		$query = BackendCallback::getBackendQuery(self::GOOGLE_CALLBACK, self::GOOGLE_CALLBACK);

		$this->assertArrayNotHasKey('scope', $query);
		$this->assertStringNotContainsString('.profile', http_build_query($query));
	}

	public function testRequestVariablesTakePrecedenceOverTheUri(): void
	{
		$uriVars          = self::GOOGLE_CALLBACK;
		$uriVars['code']  = 'from-uri';
		$requestVars      = self::GOOGLE_CALLBACK;

		$this->assertSame('dummy', BackendCallback::getBackendQuery($requestVars, $uriVars)['code']);
	}

	public function testPluginAndFormatAreFiltered(): void
	{
		$vars           = self::GOOGLE_CALLBACK;
		$vars['plugin'] = 'goo<gle>"';
		unset($vars['format']);

		$query = BackendCallback::getBackendQuery($vars, $vars);

		$this->assertSame('google', $query['plugin']);
		$this->assertSame('raw', $query['format']);
	}
}
