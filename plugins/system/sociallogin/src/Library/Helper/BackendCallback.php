<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialLogin\Library\Helper;

// Protect from unauthorized access
defined('_JEXEC') || die();

/**
 * Decides whether an OAuth callback which arrived at the frontend belongs to a backend login / link flow, and which
 * request parameters to forward to the backend.
 *
 * @since  4.11.1
 */
final class BackendCallback
{
	/**
	 * Get the query parameters of the backend callback URL for an admin-bound OAuth callback.
	 *
	 * @param   array  $requestVars  The request variables, as PHP parsed them.
	 * @param   array  $uriVars      The query variables parsed from the request URI.
	 *
	 * @return  array|null  The query parameters, starting with option, group, plugin and format. NULL when this is not
	 *                      an admin-bound Social Login callback.
	 *
	 * @since   4.11.1
	 */
	public static function getBackendQuery(array $requestVars, array $uriVars): ?array
	{
		/**
		 * Some server firewalls (mod_security) reject any request having ".profile" in a parameter, mistaking it for
		 * an attempt to read a shell profile file. Google's returned scope always has it ("…/auth/userinfo.profile").
		 * If the 403 error document is served by Joomla, the request still reaches us, but with an empty query string;
		 * only the request URI has the parameters. Joomla's site router uses the request URI, so the frontend flow
		 * works, but we run before routing. Hence we fall back to the request URI.
		 */
		$vars = array_merge($uriVars, $requestVars);

		if (self::cmd($vars['option'] ?? '') !== 'com_ajax' || self::cmd($vars['group'] ?? '') !== 'sociallogin')
		{
			return null;
		}

		// The frontend session knows nothing about the backend session, so the routing hint is the "admin-" prefix of
		// the state. The backend validates the full state against its own session.
		$state = $vars['state'] ?? '';

		if (!is_string($state) || !str_starts_with($state, 'admin-'))
		{
			return null;
		}

		$query = [
			'option' => 'com_ajax',
			'group'  => 'sociallogin',
			'plugin' => self::cmd($vars['plugin'] ?? ''),
			'format' => self::cmd($vars['format'] ?? '') ?: 'raw',
		];

		unset($vars['option'], $vars['group'], $vars['plugin'], $vars['format']);

		/**
		 * Do not forward the returned scope. Nothing uses it, and the backend does not rebuild the request variables
		 * from the request URI. If a firewall rejects the forwarded request, as explained above, the backend would get
		 * no request variables at all.
		 */
		unset($vars['scope']);

		return array_merge($query, $vars);
	}

	/**
	 * Filter a value the way Joomla's CMD input filter does.
	 *
	 * @param   mixed  $value  The raw value
	 *
	 * @return  string
	 *
	 * @since   4.11.1
	 */
	private static function cmd($value): string
	{
		if (!is_scalar($value))
		{
			return '';
		}

		return ltrim((string) preg_replace('/[^A-Z0-9_\.-]/i', '', (string) $value), '.');
	}
}
