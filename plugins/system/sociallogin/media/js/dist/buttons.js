"use strict";

/**
 *  @package   AkeebaSocialLogin
 *  @copyright Copyright (c)2016-2022 Nicholas K. Dionysopoulos / Akeeba Ltd
 *  @license   GNU General Public License version 3, or later
 */

/**
 * Finds the first field matching a selector inside a form
 *
 * @param   {HTMLFormElement}  elForm         The FORM element
 * @param   {String}           fieldSelector  The CSS selector to locate the field
 *
 * @returns {Element|null}  NULL when no element is found
 */
function akeeba_sociallogin_findField(elForm, fieldSelector) {
  var elInputs = elForm.querySelectorAll(fieldSelector);

  if (!elInputs.length) {
    return null;
  }

  return elInputs[0];
}
/**
 * Walks the DOM outwards (towards the parents) to find the form innerElement is located in. Then it looks inside the
 * form for the first element that matches the fieldSelector CSS selector.
 *
 * @param   {Element}  innerElement   The innerElement that's inside or adjacent to the form.
 * @param   {String}   fieldSelector  The CSS selector to locate the field
 *
 * @returns {null|Element}  NULL when no element is found
 */


function akeeba_sociallogin_lookInParentElementsForField(innerElement, fieldSelector) {
  var elElement = innerElement.parentElement;
  var elInput = null;

  while (true) {
    if (elElement === undefined) {
      return null;
    }

    if (elElement.nodeName === "FORM") {
      elInput = akeeba_sociallogin_findField(elElement, fieldSelector);

      if (elInput !== null) {
        return elInput;
      }

      break;
    }

    var elForms = elElement.querySelectorAll("form");

    if (elForms.length) {
      for (var i = 0; i < elForms.length; i++) {
        elInput = akeeba_sociallogin_findField(elForms[i], fieldSelector);

        if (elInput !== null) {
          return elInput;
        }
      }

      break;
    }

    if (!elElement.parentElement) {
      break;
    }

    elElement = elElement.parentElement;
  }

  return null;
}
/**
 * Moves the social login button next to the existing Login button in the login module, if possible. This is not a
 * guaranteed success! We will *try* to find a button that looks like the login action button. If the developer of the
 * module or the site integrator doing template overrides didn't bother including some useful information to help us
 * identify it we're probably going to fail hard.
 *
 * @param   {Element}  elSocialLoginButton  The login button to move.
 * @param   {Array}    possibleSelectors          The CSS selectors to use for moving the button.
 */


function akeeba_sociallogin_move_button(elSocialLoginButton, possibleSelectors) {
  if (elSocialLoginButton === null || elSocialLoginButton === undefined) {
    return;
  }

  var elLoginBtn = null;

  for (var i = 0; i < possibleSelectors.length; i++) {
    var selector = possibleSelectors[i];
    elLoginBtn = akeeba_sociallogin_lookInParentElementsForField(elSocialLoginButton, selector);

    if (elLoginBtn !== null) {
      break;
    }
  }

  if (elLoginBtn === null) {
    return;
  }

  elLoginBtn.parentElement.appendChild(elSocialLoginButton);
}
//# sourceMappingURL=buttons.js.map