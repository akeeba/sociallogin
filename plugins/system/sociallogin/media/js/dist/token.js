"use strict";

(function (document, Joomla) {
  var copyToClipboardFallback = function copyToClipboardFallback(input) {
    input.focus();
    input.select();

    try {
      var copy = document.execCommand('copy');

      if (copy) {
        Joomla.renderMessages({
          message: [Joomla.Text._('PLG_SYSTEM_SOCIALLOGIN_TOKEN_COPY_SUCCESS')]
        });
      } else {
        Joomla.renderMessages({
          error: [Joomla.Text._('PLG_SYSTEM_SOCIALLOGIN_TOKEN_COPY_FAIL')]
        });
      }
    } catch (err) {
      Joomla.renderMessages({
        error: [err]
      });
    }
  };

  var copyToClipboard = function copyToClipboard() {
    var button = document.getElementById('token-copy');
    button.addEventListener('click', function (_ref) {
      var currentTarget = _ref.currentTarget;
      var input = currentTarget.previousElementSibling;

      if (!navigator.clipboard) {
        copyToClipboardFallback(input);
        return;
      }

      navigator.clipboard.writeText(input.value).then(function () {
        Joomla.renderMessages({
          message: [Joomla.Text._('PLG_SYSTEM_SOCIALLOGIN_TOKEN_COPY_SUCCESS')]
        });
      }, function () {
        Joomla.renderMessages({
          error: [Joomla.Text._('PLG_SYSTEM_SOCIALLOGIN_TOKEN_COPY_FAIL')]
        });
      });
    });
  };

  var onBoot = function onBoot() {
    copyToClipboard();
    document.removeEventListener('DOMContentLoaded', onBoot);
  };

  document.addEventListener('DOMContentLoaded', onBoot);
})(document, Joomla);
//# sourceMappingURL=token.js.map