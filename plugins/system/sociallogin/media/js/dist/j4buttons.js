"use strict";

/*
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2020 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

/**
 * Installs click handlers for all SocialLogin buttons under Joomla 4
 */
document.addEventListener('DOMContentLoaded', function () {
  var socialLoginButtonelements = document.querySelectorAll('.akeeba-sociallogin-link-button-j4');

  for (var i = 0; i < socialLoginButtonelements.length; i++) {
    /** @type {HTMLElement} elButton */
    var elButton = socialLoginButtonelements[i];
    elButton.addEventListener('click', function (e) {
      /** @type {MouseEvent} e */
      window.location = e.currentTarget.dataset.socialurl;
    });
  }
});
//# sourceMappingURL=j4buttons.js.map