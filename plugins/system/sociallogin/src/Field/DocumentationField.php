<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialLogin\Field;

use Joomla\CMS\Form\FormField;

class DocumentationField extends FormField
{
	protected $_name = 'documentation';

	public function getInput()
	{
		$docSlug = $this->element['slug'] ?? 'The-Social-Login-plugins';
		$baseUri = 'https://github.com/akeeba/sociallogin/wiki';
		$docUri  = sprintf("%s/%s", $baseUri, $docSlug);

		return <<< HTML
<a href="$docUri" target="_blank">$docUri</a>
HTML;
	}
}