/**
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

/**
 * Unlinking a social account must be a POST request. The unlink buttons are displayed inside the user edit form, and
 * HTML does not allow nested forms (the browser silently drops the inner one, turning our button into a submit button
 * of the user edit form). So the button is a plain button, and we submit a separate, detached form here.
 */
((document) =>
{
    'use strict';

    document.addEventListener('click', (e) =>
    {
        const button = e.target.closest('[data-sociallogin-unlink]');

        if (!button)
        {
            return;
        }

        e.preventDefault();

        const form  = document.createElement('form');
        form.method = 'post';
        form.action = button.dataset.socialloginUnlink;

        const token = document.createElement('input');
        token.type  = 'hidden';
        token.name  = button.dataset.socialloginToken;
        token.value = '1';

        form.appendChild(token);
        document.body.appendChild(form);
        form.submit();
    });
})(document);
