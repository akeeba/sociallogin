<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialLogin\Library\Plugin;

// Protect from unauthorized access
defined('_JEXEC') || die();

use Joomla\Event\Event;
use Joomla\Event\ResultAwareInterface;

/**
 * Helper trait for returning results from plugin event handlers in a forward-compatible way.
 *
 * Joomla 6.0 made many core events immutable. Writing a plugin result with
 * `$event->setArgument('result', …)` (equivalent to the legacy `$event['result'][] = …`) is
 * deprecated and throws on immutable events; the support for the old style has only been extended
 * up to Joomla 7.0. Events implementing `ResultAwareInterface` must instead receive their results
 * through `$event->addResult()`.
 *
 * This trait implements Joomla's officially recommended migration pattern: use `addResult()` when
 * the event supports it and only fall back to the legacy `setArgument()` approach for older event
 * objects. As a result the same code keeps working from Joomla 4.4 all the way to Joomla 7.0.
 *
 * @since 4.11.0
 */
trait EventResultTrait
{
	/**
	 * Add a result to a plugin event in a forward-compatible manner.
	 *
	 * @param   Event  $event  The event to add the result to.
	 * @param   mixed  $value  The result value to add.
	 *
	 * @return  void
	 * @since   4.11.0
	 */
	protected function addEventResult(Event $event, $value): void
	{
		// Joomla 5.0+ result-aware (and immutable) events: append through the supported API.
		if ($event instanceof ResultAwareInterface)
		{
			$event->addResult($value);

			return;
		}

		// Legacy fallback for Joomla 4.x generic events which are not result-aware.
		$result   = $event->getArgument('result') ?: [];
		$result   = is_array($result) ? $result : [$result];
		$result[] = $value;

		$event->setArgument('result', $result);
	}
}
