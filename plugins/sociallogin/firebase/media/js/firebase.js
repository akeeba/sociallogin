/**
 * Firebase Authentication Client Script for Akeeba Social Login
 * Supports OAuth providers (Google, Facebook, etc.) and Email/Password & Magic Link sign-in.
 *
 * @package   AkeebaSocialLogin
 * @copyright Copyright (c)2016-2026 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    const options = (typeof Joomla !== 'undefined' && Joomla.getOptions)
      ? Joomla.getOptions('plg_sociallogin_firebase', {})
      : {};

    const projectId = options.projectId || '';
    const apiKey = options.apiKey || '';
    const authDomain = options.authDomain || (projectId ? projectId + '.firebaseapp.com' : '');
    const ajaxUrl = options.ajaxUrl || '';
    const enabledProviders = (options.providers && Array.isArray(options.providers) && options.providers.length)
      ? options.providers
      : ['google', 'facebook', 'password'];

    if (!projectId || !apiKey) {
      return;
    }

    // 1. Initialize Firebase App
    if (typeof firebase !== 'undefined' && (!firebase.apps || !firebase.apps.length)) {
      firebase.initializeApp({
        apiKey: apiKey,
        authDomain: authDomain,
        projectId: projectId
      });
    }

    // 2. Check for redirect result on page load
    if (typeof firebase !== 'undefined' && firebase.auth) {
      firebase.auth().getRedirectResult().then(async function (result) {
        if (result && result.user) {
          openModal();
          showModalAlert('Finalizing sign-in...', 'info');
          await completeFirebaseLogin(result.user, null);
        }
      }).catch(function (err) {
        console.error('Firebase redirect error:', err);
        openModal();
        showModalAlert(err.message || 'Redirect authentication failed.', 'error');
      });

      // Check if user is returning from a passwordless Email Sign-In Link
      if (firebase.auth().isSignInWithEmailLink(window.location.href)) {
        let email = window.localStorage.getItem('emailForSignIn');
        if (!email) {
          email = window.prompt('Please enter the email address you used to request the sign-in link:');
        }
        if (email) {
          openModal();
          showModalAlert('Verifying email sign-in link...', 'info');
          firebase.auth().signInWithEmailLink(email, window.location.href)
            .then(async function (result) {
              window.localStorage.removeItem('emailForSignIn');
              await completeFirebaseLogin(result.user, null);
            })
            .catch(function (err) {
              showModalAlert(err.message || 'Failed to sign in with link.', 'error');
            });
        }
      }
    }

    // 3. Modal Elements & Configuration
    let modalBackdrop = null;
    let modalAlert = null;
    let modalBody = null;

    const PROVIDER_CONFIGS = {
      google: {
        name: 'Google',
        label: 'Sign in with Google',
        btnClass: 'btn-google',
        iconSvg: '<svg width="20" height="20" viewBox="0 0 48 48"><path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/><path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.78 7.18l7.73 6c4.51-4.18 7.09-10.36 7.09-17.65z"/><path fill="#FBBC05" d="M10.53 28.59c-.48-1.45-.76-2.99-.76-4.59s.27-3.14.76-4.59l-7.98-6.19C.92 16.46 0 20.12 0 24c0 3.88.92 7.54 2.56 10.78l7.97-6.19z"/><path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.89-5.81l-7.73-6c-2.15 1.45-4.92 2.3-8.16 2.3-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/></svg>',
        createProvider: function () {
          const provider = new firebase.auth.GoogleAuthProvider();
          provider.setCustomParameters({ prompt: 'select_account' });
          return provider;
        }
      },
      facebook: {
        name: 'Facebook',
        label: 'Sign in with Facebook',
        btnClass: 'btn-facebook',
        iconSvg: '<svg width="20" height="20" viewBox="0 0 24 24" fill="#ffffff"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>',
        createProvider: function () {
          const provider = new firebase.auth.FacebookAuthProvider();
          provider.addScope('email');
          return provider;
        }
      },
      apple: {
        name: 'Apple',
        label: 'Sign in with Apple',
        btnClass: 'btn-apple',
        iconSvg: '<svg width="20" height="20" viewBox="0 0 24 24" fill="#ffffff"><path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.81-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M15.97 6.37c.61-.75 1.04-1.8 1.01-2.87-.96.04-2.17.65-2.84 1.43-.59.68-1.12 1.76-1.01 2.8.08.01.16.02.24.02 1 0 2-.63 2.6-1.38z"/></svg>',
        createProvider: function () {
          const provider = new firebase.auth.OAuthProvider('apple.com');
          provider.addScope('email');
          provider.addScope('name');
          return provider;
        }
      },
      github: {
        name: 'GitHub',
        label: 'Sign in with GitHub',
        btnClass: 'btn-github',
        iconSvg: '<svg width="20" height="20" viewBox="0 0 24 24" fill="#ffffff"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>',
        createProvider: function () {
          const provider = new firebase.auth.GithubAuthProvider();
          provider.addScope('user:email');
          return provider;
        }
      },
      microsoft: {
        name: 'Microsoft',
        label: 'Sign in with Microsoft',
        btnClass: 'btn-microsoft',
        iconSvg: '<svg width="20" height="20" viewBox="0 0 24 24"><path fill="#f25022" d="M1 1h10v10H1z"/><path fill="#00a4ef" d="M1 13h10v10H1z"/><path fill="#7fba00" d="M13 1h10v10H13z"/><path fill="#ffb900" d="M13 13h10v10H13z"/></svg>',
        createProvider: function () {
          return new firebase.auth.OAuthProvider('microsoft.com');
        }
      }
    };

    function ensureModalCreated() {
      if (modalBackdrop) {
        return;
      }

      const existing = document.getElementById('firebase-auth-modal');
      if (existing) {
        modalBackdrop = existing;
        modalAlert = modalBackdrop.querySelector('.firebase-modal-alert');
        modalBody = modalBackdrop.querySelector('.firebase-modal-body');
        return;
      }

      modalBackdrop = document.createElement('div');
      modalBackdrop.id = 'firebase-auth-modal';
      modalBackdrop.className = 'firebase-modal-backdrop';
      modalBackdrop.setAttribute('role', 'dialog');
      modalBackdrop.setAttribute('aria-modal', 'true');
      modalBackdrop.setAttribute('aria-hidden', 'true');

      const card = document.createElement('div');
      card.className = 'firebase-modal-card';

      // Close button
      const closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'firebase-modal-close';
      closeBtn.setAttribute('aria-label', 'Close dialog');
      closeBtn.innerHTML = '&times;';
      closeBtn.addEventListener('click', closeModal);
      card.appendChild(closeBtn);

      // Header
      const header = document.createElement('div');
      header.className = 'firebase-modal-header';
      header.innerHTML = [
        '<div class="firebase-modal-logo">',
        '  <svg viewBox="0 0 24 24" fill="none">',
        '    <path fill="#FFCA28" d="M4.686 16.536L8.03 2.69a.9.9 0 0 1 1.696-.134l2.42 4.532-7.46 9.448z"/>',
        '    <path fill="#FFA000" d="M14.28 9.07l-2.134-4.04a.9.9 0 0 0-1.602.046L3.924 18.232l10.356-9.162z"/>',
        '    <path fill="#F57C00" d="M12.784 21.636a11.135 11.135 0 0 1-8.86-3.404L12.56 2.37a.9.9 0 0 1 1.637.382l2.673 13.918-4.086 4.966z"/>',
        '    <path fill="#FFCA28" d="M20.076 18.232a11.173 11.173 0 0 1-7.292 3.404l4.086-4.966 3.206 1.562z"/>',
        '  </svg>',
        '</div>',
        '<h3 class="firebase-modal-title">Sign in</h3>',
        '<p class="firebase-modal-subtitle">Choose a sign-in method to continue</p>'
      ].join('');
      card.appendChild(header);

      // Alert container
      modalAlert = document.createElement('div');
      modalAlert.className = 'firebase-modal-alert';
      modalAlert.style.display = 'none';
      card.appendChild(modalAlert);

      // Body (Social Buttons and/or Email Form)
      modalBody = document.createElement('div');
      modalBody.className = 'firebase-modal-body';

      const hasPassword = enabledProviders.includes('password') || enabledProviders.includes('email');
      const socialProviders = enabledProviders.filter(function (p) {
        return p !== 'password' && p !== 'email';
      });

      // Render Social Providers
      socialProviders.forEach(function (providerKey) {
        const config = PROVIDER_CONFIGS[providerKey.toLowerCase()];
        if (!config) return;

        const pBtn = document.createElement('button');
        pBtn.type = 'button';
        pBtn.className = 'firebase-provider-btn ' + config.btnClass;
        pBtn.dataset.provider = providerKey.toLowerCase();
        pBtn.dataset.defaultLabel = config.label;
        pBtn.innerHTML = config.iconSvg + '<span class="firebase-provider-text">' + config.label + '</span>';

        pBtn.addEventListener('click', function () {
          onSocialProviderClicked(providerKey.toLowerCase(), pBtn);
        });

        modalBody.appendChild(pBtn);
      });

      // Render Email & Password Form if enabled
      if (hasPassword) {
        if (socialProviders.length > 0) {
          const divider = document.createElement('div');
          divider.className = 'firebase-modal-divider';
          divider.innerHTML = '<span>or sign in with email & password</span>';
          modalBody.appendChild(divider);
        }

        const form = document.createElement('form');
        form.id = 'firebase-email-auth-form';
        form.className = 'firebase-auth-form';
        form.innerHTML = [
          '<div class="firebase-form-group">',
          '  <label for="firebase-identifier-input" class="firebase-form-label">Email or Username</label>',
          '  <input type="text" id="firebase-identifier-input" class="firebase-input" placeholder="you@example.com or username" required autocomplete="username">',
          '</div>',
          '<div class="firebase-form-group" id="firebase-password-group">',
          '  <div class="firebase-form-label-row">',
          '    <label for="firebase-password-input" class="firebase-form-label">Password</label>',
          '    <button type="button" id="firebase-forgot-btn" class="firebase-forgot-link">Forgot password?</button>',
          '  </div>',
          '  <input type="password" id="firebase-password-input" class="firebase-input" placeholder="••••••••" autocomplete="current-password">',
          '</div>',
          '<div class="firebase-checkbox-group">',
          '  <input type="checkbox" id="firebase-magic-checkbox">',
          '  <label for="firebase-magic-checkbox" class="firebase-checkbox-label">Email me a sign-in link instead (no password)</label>',
          '</div>',
          '<button type="submit" id="firebase-email-submit-btn" class="firebase-submit-btn">Sign In</button>'
        ].join('');

        // Form Event Handlers
        const identifierInput = form.querySelector('#firebase-identifier-input');
        const passwordGroup = form.querySelector('#firebase-password-group');
        const passwordInput = form.querySelector('#firebase-password-input');
        const magicCheckbox = form.querySelector('#firebase-magic-checkbox');
        const submitBtn = form.querySelector('#firebase-email-submit-btn');
        const forgotBtn = form.querySelector('#firebase-forgot-btn');

        // Toggle Magic Link vs Password Mode
        magicCheckbox.addEventListener('change', function () {
          clearModalAlert();
          if (magicCheckbox.checked) {
            passwordGroup.style.display = 'none';
            passwordInput.value = '';
            submitBtn.textContent = 'Send Sign-In Link';
          } else {
            passwordGroup.style.display = 'flex';
            submitBtn.textContent = 'Sign In';
          }
        });

        // Forgot Password Handler
        forgotBtn.addEventListener('click', async function () {
          clearModalAlert();
          const rawIdentifier = (identifierInput.value || '').trim();
          if (!rawIdentifier) {
            showModalAlert('Please enter your email address or username first, then click "Forgot password?".', 'warning');
            identifierInput.focus();
            return;
          }

          try {
            forgotBtn.disabled = true;
            const email = await resolveEmailIdentifier(rawIdentifier);
            await firebase.auth().sendPasswordResetEmail(email);
            showModalAlert('Password reset email sent to ' + email + '. Please check your inbox.', 'info');
          } catch (err) {
            console.error('Password reset error:', err);
            showModalAlert(err.message || 'Failed to send password reset email.', 'error');
          } finally {
            forgotBtn.disabled = false;
          }
        });

        // Form Submit Handler
        form.addEventListener('submit', async function (e) {
          e.preventDefault();
          clearModalAlert();

          const rawIdentifier = (identifierInput.value || '').trim();
          if (!rawIdentifier) {
            showModalAlert('Please enter your email address or username.', 'warning');
            identifierInput.focus();
            return;
          }

          if (typeof firebase === 'undefined' || !firebase.auth) {
            showModalAlert('Authentication service is initializing. Please try again in a moment.', 'warning');
            return;
          }

          if (magicCheckbox.checked) {
            // Send Passwordless Magic Link
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="firebase-spinner"></span> Sending link...';

            try {
              const email = await resolveEmailIdentifier(rawIdentifier);
              const actionCodeSettings = {
                url: window.location.origin + window.location.pathname + window.location.search,
                handleCodeInApp: true
              };

              await firebase.auth().sendSignInLinkToEmail(email, actionCodeSettings);
              window.localStorage.setItem('emailForSignIn', email);
              showModalAlert('Sign-in link sent to ' + email + '! Please check your inbox and click the link to sign in.', 'info');
              submitBtn.textContent = 'Link Sent';
            } catch (err) {
              console.error('Magic link error:', err);
              showModalAlert(err.message || 'Failed to send sign-in link.', 'error');
              submitBtn.disabled = false;
              submitBtn.textContent = 'Send Sign-In Link';
            }
          } else {
            // Password Sign In
            const password = passwordInput.value;
            if (!password) {
              showModalAlert('Please enter your password.', 'warning');
              passwordInput.focus();
              return;
            }

            setAllControlsDisabled(true);
            submitBtn.innerHTML = '<span class="firebase-spinner"></span> Signing in...';

            try {
              const email = await resolveEmailIdentifier(rawIdentifier);
              const result = await firebase.auth().signInWithEmailAndPassword(email, password);

              if (!result || !result.user) {
                throw new Error('No user returned from Firebase authentication.');
              }

              submitBtn.innerHTML = '<span class="firebase-spinner"></span> Connecting...';
              await completeFirebaseLogin(result.user, submitBtn);
            } catch (err) {
              console.error('Password sign in error:', err);
              let errorMsg = err.message || 'Sign in failed.';
              if (err.code === 'auth/wrong-password' || err.code === 'auth/invalid-credential') {
                errorMsg = 'Incorrect password. Click "Forgot password?" if you need to reset it, or check the box below to sign in with an email link.';
              } else if (err.code === 'auth/user-not-found') {
                errorMsg = 'No Firebase account found for this user. You can sign in with Google or Facebook, or request a sign-in link below.';
              } else if (err.code === 'auth/too-many-requests') {
                errorMsg = 'Access to this account has been temporarily disabled due to many failed login attempts. Please reset your password or try again later.';
              }

              showModalAlert(errorMsg, 'error');
              setAllControlsDisabled(false);
              submitBtn.textContent = 'Sign In';
            }
          }
        });

        modalBody.appendChild(form);
      }

      card.appendChild(modalBody);
      modalBackdrop.appendChild(card);
      document.body.appendChild(modalBackdrop);

      // Click on backdrop to dismiss
      modalBackdrop.addEventListener('click', function (e) {
        if (e.target === modalBackdrop) {
          closeModal();
        }
      });
    }

    // Resolves username to email via Joomla AJAX if needed
    async function resolveEmailIdentifier(identifier) {
      if (identifier.indexOf('@') !== -1) {
        return identifier;
      }

      const targetUrl = ajaxUrl || (window.location.origin + '/index.php?option=com_ajax&group=sociallogin&plugin=firebase&format=raw');
      const response = await fetch(targetUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'resolve_identifier', identifier: identifier })
      });

      const data = await response.json();
      if (data.success && data.email) {
        return data.email;
      }

      throw new Error(data.message || 'No user account found matching username "' + identifier + '". Please use your email address.');
    }

    function openModal() {
      ensureModalCreated();
      clearModalAlert();
      resetControls();
      modalBackdrop.classList.add('active');
      modalBackdrop.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';

      const emailInput = document.getElementById('firebase-identifier-input');
      if (emailInput) {
        setTimeout(function () { emailInput.focus(); }, 150);
      }
    }

    function closeModal() {
      if (!modalBackdrop) return;
      modalBackdrop.classList.remove('active');
      modalBackdrop.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      clearModalAlert();
      resetControls();
    }

    function showModalAlert(message, type) {
      if (!modalAlert) return;
      modalAlert.className = 'firebase-modal-alert alert-' + (type || 'error');
      modalAlert.textContent = message;
      modalAlert.style.display = 'flex';
    }

    function clearModalAlert() {
      if (!modalAlert) return;
      modalAlert.style.display = 'none';
      modalAlert.textContent = '';
      modalAlert.className = 'firebase-modal-alert';
    }

    function resetControls() {
      if (!modalBody) return;
      setAllControlsDisabled(false);

      const btns = modalBody.querySelectorAll('.firebase-provider-btn');
      btns.forEach(function (btn) {
        btn.classList.remove('loading');
        const textSpan = btn.querySelector('.firebase-provider-text');
        if (textSpan && btn.dataset.defaultLabel) {
          textSpan.textContent = btn.dataset.defaultLabel;
        }
      });

      const emailSubmitBtn = modalBody.querySelector('#firebase-email-submit-btn');
      if (emailSubmitBtn) {
        const magicCheckbox = modalBody.querySelector('#firebase-magic-checkbox');
        emailSubmitBtn.textContent = (magicCheckbox && magicCheckbox.checked) ? 'Send Sign-In Link' : 'Sign In';
      }
    }

    function setAllControlsDisabled(disabled) {
      if (!modalBody) return;
      const inputs = modalBody.querySelectorAll('button, input');
      inputs.forEach(function (el) {
        if (!el.classList.contains('firebase-modal-close')) {
          el.disabled = disabled;
        }
      });
    }

    // Escape key listener
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && modalBackdrop && modalBackdrop.classList.contains('active')) {
        closeModal();
      }
    });

    // 4. Intercept clicks on the Joomla Firebase Social Login button
    document.addEventListener('click', function (e) {
      const btn = e.target.closest(
        '.akeeba-sociallogin-link-button-firebase, .akeeba-sociallogin-link-button-j4-firebase, [data-socialurl*="plugin=firebase"]'
      );
      if (!btn) return;

      // Ignore buttons inside our modal
      if (btn.closest('#firebase-auth-modal')) return;

      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();

      openModal();
    }, true);

    // 5. Social Provider Authentication Handler
    async function onSocialProviderClicked(providerKey, btnEl) {
      if (typeof firebase === 'undefined' || !firebase.auth) {
        showModalAlert('Firebase Authentication SDK is loading. Please try again in a moment.', 'warning');
        return;
      }

      clearModalAlert();
      setAllControlsDisabled(true);
      btnEl.classList.add('loading');
      const textSpan = btnEl.querySelector('.firebase-provider-text');
      if (textSpan) {
        textSpan.innerHTML = '<span class="firebase-spinner"></span> Signing in...';
      }

      const config = PROVIDER_CONFIGS[providerKey];
      if (!config || !config.createProvider) {
        showModalAlert('Provider ' + providerKey + ' is not configured.', 'error');
        resetControls();
        return;
      }

      const provider = config.createProvider();

      try {
        let user;
        try {
          const result = await firebase.auth().signInWithPopup(provider);
          user = result.user;
        } catch (popupErr) {
          if (popupErr.code === 'auth/popup-blocked') {
            await firebase.auth().signInWithRedirect(provider);
            return;
          }
          throw popupErr;
        }

        if (!user) {
          throw new Error('No user returned from authentication.');
        }

        if (textSpan) {
          textSpan.innerHTML = '<span class="firebase-spinner"></span> Connecting...';
        }

        await completeFirebaseLogin(user, btnEl);
      } catch (err) {
        console.error('Firebase Auth Error:', err);
        let errorMsg = err.message || 'Login failed. Please try again.';
        if (err.code === 'auth/popup-closed-by-user') {
          errorMsg = 'Sign-in window was closed before completion.';
        } else if (err.code === 'auth/account-exists-with-different-credential') {
          errorMsg = 'An account already exists with this email under a different sign-in method.';
        } else if (err.code === 'auth/cancelled-popup-request') {
          resetControls();
          return;
        }

        showModalAlert(errorMsg, 'error');
        resetControls();
      }
    }

    // 6. Complete Login with Joomla AJAX Backend
    async function completeFirebaseLogin(user, btnEl) {
      const idToken = await user.getIdToken();

      // Determine return URL from form or query parameters
      let returnUrl = '';
      const returnInput = document.querySelector('form input[name="return"][value]:not([value=""])');
      if (returnInput && returnInput.value) {
        returnUrl = returnInput.value;
      } else {
        const urlParams = new URLSearchParams(window.location.search);
        returnUrl = urlParams.get('return') || '';
      }

      const targetUrl = ajaxUrl || (window.location.origin + '/index.php?option=com_ajax&group=sociallogin&plugin=firebase&format=raw');

      try {
        const response = await fetch(targetUrl, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify({ token: idToken, return: returnUrl })
        });

        const data = await response.json();

        if (data.success) {
          if (btnEl) {
            const textSpan = btnEl.querySelector('.firebase-provider-text');
            if (textSpan) {
              textSpan.textContent = '✓ Success!';
            } else {
              btnEl.textContent = '✓ Success!';
            }
          }
          window.location.href = data.redirect || window.location.href;
        } else {
          throw new Error(data.message || 'Authentication failed on server.');
        }
      } catch (err) {
        console.error('Joomla Social Login Error:', err);
        showModalAlert(err.message || 'Server authentication failed.', 'error');
        resetControls();
      }
    }
  });
})();
