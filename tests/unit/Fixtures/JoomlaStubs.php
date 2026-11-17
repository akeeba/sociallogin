<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Minimal stand-ins for the few Joomla classes the unit tests touch. The unit layer deliberately has no Joomla; code
 * which needs the real framework is covered by integration tests instead.
 */

declare(strict_types=1);

namespace Joomla\CMS\Application
{
	if (!class_exists(CMSApplication::class, false))
	{
		class CMSApplication
		{
			public $session;

			public bool $admin = false;

			public function getSession()
			{
				return $this->session;
			}

			public function isClient(string $client): bool
			{
				return $client === ($this->admin ? 'administrator' : 'site');
			}
		}
	}
}

namespace Akeeba\SocialLogin\Tests\Unit\Fixtures
{
	/** An in-memory stand-in for Joomla's session object. */
	class FakeSession
	{
		private array $data = [];

		public function get($name, $default = null)
		{
			return $this->data[$name] ?? $default;
		}

		public function set($name, $value = null)
		{
			$this->data[$name] = $value;
		}
	}

	/** An in-memory stand-in for Joomla's Input object. */
	class FakeInput
	{
		public array $request = [];

		public function getString($name, $default = '')
		{
			return $this->request[$name] ?? $default;
		}
	}
}
