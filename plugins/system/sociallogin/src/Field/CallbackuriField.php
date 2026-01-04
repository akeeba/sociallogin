<?php
/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Plugin\System\SocialLogin\Field;

use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\FormField;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

class CallbackuriField extends FormField
{
	protected $_name = 'callbackuri';

	public function getInput()
	{
		$this->prepareJavaScript();

		$pluginCode = $this->element['plugincode'] ?? 'foobar';
		$path       = $this->element['path'] ?? null;
		$baseUri    = rtrim(Uri::base(), '/');

		if (substr($baseUri, -14) === '/administrator')
		{
			$baseUri = substr($baseUri, 0, -14);
		}

		if (!empty($path))
		{
			$callbackUri = sprintf("%s/%s", $baseUri, $path);
		}
		else
		{
			$callbackUri = sprintf(
				"%s/index.php?option=com_ajax&group=sociallogin&plugin=%s&format=raw", $baseUri, urlencode($pluginCode)
			);
		}

		$description = Text::_('PLG_SYSTEM_SOCIALLOGIN_COPY_DESC');
		$label       = Text::_('PLG_SYSTEM_SOCIALLOGIN_COPY');

		return <<< HTML
<div class="input-group">
	<input type="text" class="form-control" readonly value="$callbackUri" id="socialLoginCallbackURI">
	<button
        class="btn btn-primary"
        type="button"
        id="token-copy"
        title="$description">
        <span class="fa fa-copy" aria-hidden="true"></span>
        <span class="visually-hidden">$label</span>
	</button>
</div>
HTML;
	}

	private function prepareJavaScript()
	{
		/** @var HtmlDocument $doc */
		$doc = Factory::getApplication()->getDocument();

		if (!$doc instanceof HtmlDocument)
		{
			return;
		}

		$wam = $doc->getWebAssetManager();

		if (!$wam->getRegistry()->exists('script', 'plg_system_sociallogin.token'))
		{
			$lang = Factory::getApplication()->getLanguage();
			$lang->load('plg_system_sociallogin', JPATH_ADMINISTRATOR);

			Text::script('ERROR');
			Text::script('MESSAGE');
			Text::script('PLG_SYSTEM_SOCIALLOGIN_TOKEN_COPY_SUCCESS');
			Text::script('PLG_SYSTEM_SOCIALLOGIN_TOKEN_COPY_FAIL');

			$wam->registerAndUseScript('plg_system_sociallogin.token', 'plg_system_sociallogin/token.js', [], ['defer' => true], ['core']);
		}
	}
}