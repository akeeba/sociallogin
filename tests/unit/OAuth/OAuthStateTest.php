<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

declare(strict_types=1);

namespace Akeeba\SocialLogin\Tests\Unit\OAuth;

use Akeeba\Plugin\System\SocialLogin\Library\OAuth\OAuth2Client;
use Akeeba\SocialLogin\Tests\Unit\Fixtures\FakeInput;
use Akeeba\SocialLogin\Tests\Unit\Fixtures\FakeSession;
use Joomla\CMS\Application\CMSApplication;
use PHPUnit\Framework\TestCase;
use RuntimeException;

require_once __DIR__ . '/../Fixtures/JoomlaStubs.php';

/**
 * The OAuth2 `state` parameter protects the callback against CSRF. It must be bound to the user's session, single-use,
 * and robust against a page rendering several login / link buttons (each of which gets its own state).
 */
class OAuthStateTest extends TestCase
{
	private FakeSession $session;

	private FakeInput $input;

	private CMSApplication $app;

	protected function setUp(): void
	{
		require_once dirname(__DIR__, 3) . '/plugins/system/sociallogin/src/Library/OAuth/OAuth2Client.php';

		$this->session = new FakeSession();
		$this->input   = new FakeInput();
		$this->app     = new CMSApplication();

		$this->app->session = $this->session;
	}

	private function makeClient(bool $admin = false): OAuth2Client
	{
		$this->app->admin = $admin;

		return new OAuth2Client(
			['authurl' => 'https://example.com/auth', 'clientid' => 'abc', 'admin' => $admin],
			new \stdClass(),
			$this->input,
			$this->app
		);
	}

	private function stateOf(string $url): string
	{
		parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

		return $query['state'];
	}

	private function validate(OAuth2Client $client, string $state): void
	{
		$this->input->request['state'] = $state;

		$method = new \ReflectionMethod($client, 'validateOAuthState');
		$method->setAccessible(true);
		$method->invoke($client);
	}

	public function testEveryRenderedButtonStateRemainsValid(): void
	{
		// The user edit page renders the buttons more than once; the user may click any of them.
		$client = $this->makeClient();
		$first  = $this->stateOf($client->createUrl());
		$second = $this->stateOf($client->createUrl());

		$this->assertNotSame($first, $second);

		$this->validate($client, $first);
		$this->validate($client, $second);
		$this->addToAssertionCount(1);
	}

	public function testStateIsSingleUse(): void
	{
		$client = $this->makeClient();
		$state  = $this->stateOf($client->createUrl());

		$this->validate($client, $state);

		$this->expectException(RuntimeException::class);
		$this->validate($client, $state);
	}

	public function testUnknownStateIsRejected(): void
	{
		$client = $this->makeClient();
		$client->createUrl();

		$this->expectException(RuntimeException::class);
		$this->validate($client, bin2hex(random_bytes(16)));
	}

	public function testMissingStateIsRejected(): void
	{
		$client = $this->makeClient();
		$client->createUrl();

		$this->expectException(RuntimeException::class);
		$this->validate($client, '');
	}

	public function testStateFromAnotherSessionIsRejected(): void
	{
		$state = $this->stateOf($this->makeClient()->createUrl());

		// A different browser session has no pending states.
		$this->app->session = new FakeSession();

		$this->expectException(RuntimeException::class);
		$this->validate($this->makeClient(), $state);
	}

	public function testBackendStatesCarryTheAdminRoutingPrefix(): void
	{
		// The frontend callback cannot read the backend session; it routes using the prefix of the state alone.
		$this->assertStringStartsWith('admin-', $this->stateOf($this->makeClient(true)->createUrl()));
		$this->assertStringStartsNotWith('admin-', $this->stateOf($this->makeClient(false)->createUrl()));
	}

	public function testBackendStateIsStillValidatedInFull(): void
	{
		$client = $this->makeClient(true);
		$client->createUrl();

		// The prefix alone is only a routing hint; it must not be enough to pass validation.
		$this->expectException(RuntimeException::class);
		$this->validate($client, 'admin-' . bin2hex(random_bytes(16)));
	}

	public function testPendingStatesAreBounded(): void
	{
		$client = $this->makeClient();
		$states = [];

		for ($i = 0; $i < 40; $i++)
		{
			$states[] = $this->stateOf($client->createUrl());
		}

		$this->assertLessThanOrEqual(25, count($this->session->get('plg_system_sociallogin.oauthStates')));

		// The newest is always valid.
		$this->validate($client, end($states));
		$this->addToAssertionCount(1);
	}
}
