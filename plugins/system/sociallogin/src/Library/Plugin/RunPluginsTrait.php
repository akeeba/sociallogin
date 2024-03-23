<?php
/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2024 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialLogin\Library\Plugin;

use Joomla\CMS\Event\GenericEvent;
use Joomla\Event\Event;

trait RunPluginsTrait
{
	public function runPlugins(string $eventName, $data): array
	{
		if ($data instanceof Event)
		{
			$event = $data;
		}
		else
		{
			$data['subject'] = $data['subject'] ?? $this;

			$event = GenericEvent::create($eventName, $data);
		}

		$result = $this->getDispatcher()
		               ->dispatch($eventName, $event);

		return !isset($result['result']) ? [] : $result['result'];
	}
}