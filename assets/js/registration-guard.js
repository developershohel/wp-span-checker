/**
 * VMS Elements Form Guard - Registration Guard Frontend
 * Adds validation button and reCAPTCHA protection to WordPress registration forms.
 */
(function($) {
    'use strict';

    if (typeof VEFGRegistrationGuard === 'undefined') {
        return;
    }

    var config = VEFGRegistrationGuard;
    var ajaxUrl = config.ajaxUrl || '';
    var nonce = config.nonce || '';
    var frontendEnabled = config.frontendEnabled || false;
    var recaptchaEnabled = config.recaptchaEnabled || false;
    var recaptchaSiteKey = config.recaptchaSiteKey || '';
    var recaptchaVersion = config.recaptchaVersion || 'v2';
    var formSelector = config.formSelector || '';
    var i18n = config.i18n || {};
    var recaptchaWidgetId = null;
    var registrationGuardAttached = false;

    function t(key, fallback) {
        return i18n[key] || fallback || key;
    }

    function getSubmitButtonText($btn, fallback) {
        var raw = $btn.val() || $btn.text() || fallback || '';
        return String(raw).replace(/\s+/g, ' ').trim();
    }

    var Toast = null;
    if (typeof Swal !== 'undefined') {
        Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
        });
    }

    function showToast(icon, title) {
        if (Toast) {
            Toast.fire({ icon: icon, title: title });
        } else if (icon === 'error') {
            alert(title);
        }
    }

    function showFieldError($field, message) {
        clearFieldError($field);
        $field.addClass('vefg-field-invalid');
        var $error = $('<span class="vefg-field-error vefg-reg-error" style="color:#d63638;display:block;margin-top:5px;">' + message + '</span>');
        $field.after($error);
    }

    function clearFieldError($field) {
        $field.removeClass('vefg-field-invalid');
        $field.siblings('.vefg-reg-error').remove();
        $field.parent().find('.vefg-reg-error').remove();
    }

    function clearAllErrors($form) {
        $form.find('.vefg-field-invalid').removeClass('vefg-field-invalid');
        $form.find('.vefg-reg-error').remove();
    }

    function isButtonVisible($btn) {
        if (!$btn.length) return false;
        if ($btn.attr('aria-hidden') === 'true') return false;
        if ($btn.attr('tabindex') === '-1') {
            var styles = $btn.attr('style') || '';
            if (styles.indexOf('display: none') !== -1 || 
                styles.indexOf('visibility: hidden') !== -1 ||
                (styles.indexOf('position: absolute') !== -1 && styles.indexOf('left: -9999') !== -1)) {
                return false;
            }
        }
        try {
            var computed = window.getComputedStyle($btn[0]);
            if (computed.display === 'none' || computed.visibility === 'hidden' || computed.opacity === '0') {
                return false;
            }
        } catch(e) {}
        return true;
    }

    function findVisibleSubmit($form, selector) {
        var $buttons = $form.find(selector);
        for (var i = 0; i < $buttons.length; i++) {
            var $btn = $buttons.eq(i);
            if (isButtonVisible($btn)) return $btn;
        }
        return $();
    }

    function findSubmitButton($form) {
        // 1. WordPress registration specific submit
        var $btn = $form.find('#wp-submit').first();
        if ($btn.length && isButtonVisible($btn)) return $btn;
        
        // 2. input[type="submit"] - visible
        $btn = findVisibleSubmit($form, 'input[type="submit"]');
        if ($btn.length) return $btn;
        
        // 3. button[type="submit"] - visible
        $btn = findVisibleSubmit($form, 'button[type="submit"]:not(.quform-default-submit)');
        if ($btn.length) return $btn;
        
        // 4. Common registration submit classes - visible
        $btn = findVisibleSubmit($form, '.submit, .registration-submit, .btn-submit, .wp-submit, .register-submit');
        if ($btn.length) return $btn;
        
        // 5. Any element with type="submit" - visible
        $btn = findVisibleSubmit($form, '[type="submit"]');
        if ($btn.length) return $btn;
        
        // 6. Button without type - visible
        $btn = findVisibleSubmit($form, 'button:not([type]):not([aria-hidden="true"])');
        if ($btn.length) return $btn;
        
        // 7. Button not explicitly button/reset - visible
        $btn = findVisibleSubmit($form, 'button:not([type="button"]):not([type="reset"]):not([aria-hidden="true"])');
        if ($btn.length) return $btn;
        
        // 8. Last visible button as fallback
        var $allButtons = $form.find('button');
        for (var i = $allButtons.length - 1; i >= 0; i--) {
            var $b = $allButtons.eq(i);
            if (isButtonVisible($b)) return $b;
        }
        
        // 9. Last input[type="button"]
        $btn = $form.find('input[type="button"]').last();
        if ($btn.length && isButtonVisible($btn)) return $btn;
        
        return $();
    }

    function copyButtonStyles($source, $target) {
        if (!$source.length) return;
        
        var computed = window.getComputedStyle($source[0]);
        $target.css({
            'background': computed.background,
            'background-color': computed.backgroundColor,
            'color': computed.color,
            'font-size': computed.fontSize,
            'font-weight': computed.fontWeight,
            'font-family': computed.fontFamily,
            'padding': computed.padding,
            'margin': computed.margin,
            'border': computed.border,
            'border-radius': computed.borderRadius,
            'width': 'auto',
            'height': computed.height,
            'line-height': computed.lineHeight,
            'cursor': 'pointer',
            'display': 'inline-block',
            'text-align': computed.textAlign
        });
    }

    function hideOriginalSubmit($btn) {
        $btn.css({
            'display': 'none',
            'position': 'absolute',
            'left': '-9999px',
            'visibility': 'hidden',
            'pointer-events': 'none',
            'opacity': '0',
            'width': '0',
            'height': '0'
        });
    }

    function validateRegistration(email, recaptchaToken) {
        return new Promise(function(resolve, reject) {
            var data = {
                action: 'vefg_validate_registration',
                nonce: nonce,
                email: email
            };
            
            if (recaptchaToken) {
                data.recaptcha_token = recaptchaToken;
            }

            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: data,
                dataType: 'json',
                success: function(response) {
                    if (response && response.success && response.data) {
                        resolve(response.data);
                    } else {
                        resolve({ status: false, message: t('validationFailed', 'Validation failed.') });
                    }
                },
                error: function() {
                    reject(new Error(t('serverError', 'Server error. Please try again.')));
                }
            });
        });
    }

    function getRecaptchaToken() {
        return new Promise(function(resolve, reject) {
            if (!recaptchaEnabled || !recaptchaSiteKey) {
                resolve(null);
                return;
            }

            if (recaptchaVersion === 'v3') {
                if (typeof grecaptcha !== 'undefined' && grecaptcha.execute) {
                    grecaptcha.ready(function() {
                        grecaptcha.execute(recaptchaSiteKey, { action: 'registration' })
                            .then(function(token) {
                                resolve(token);
                            })
                            .catch(function(err) {
                                console.error('[Registration Guard] reCAPTCHA v3 error:', err);
                                resolve(null);
                            });
                    });
                } else {
                    resolve(null);
                }
            } else {
                var response = typeof grecaptcha !== 'undefined' ? grecaptcha.getResponse(recaptchaWidgetId) : '';
                if (response) {
                    resolve(response);
                } else {
                    reject(new Error(t('recaptchaRequired', 'Please complete the reCAPTCHA verification.')));
                }
            }
        });
    }

    function resolveFormElement(el) {
        var $el = $(el);
        if ($el.is('form')) {
            return el;
        }
        var $inner = $el.find('form').first();
        return $inner.length ? $inner[0] : null;
    }

    function setupRegistrationGuard() {
        if (registrationGuardAttached) {
            return;
        }

        var $form = null;
        
        // Use provided form selector if available
        if (formSelector && formSelector.trim() !== '') {
            var matches = $(formSelector);
            for (var i = 0; i < matches.length; i++) {
                var formEl = resolveFormElement(matches[i]);
                if (formEl) {
                    $form = $(formEl);
                    break;
                }
            }
        }
        
        // Fall back to default WordPress registration selectors if no custom selector
        if (!$form || !$form.length) {
            $form = $('#registerform, form[name="registerform"], .woocommerce-form-register, .register form, form.registration-form').first();
        }
        
        // If still not found, try auto-detection as last resort
        if (!$form || !$form.length) {
            $('form').each(function() {
                var $f = $(this);
                var formId = ($f.attr('id') || '').toLowerCase();
                var formClass = ($f.attr('class') || '').toLowerCase();
                var formAction = ($f.attr('action') || '').toLowerCase();
                
                if (formId.indexOf('register') !== -1 || 
                    formClass.indexOf('register') !== -1 ||
                    formAction.indexOf('register') !== -1 ||
                    formAction.indexOf('signup') !== -1) {
                    $form = $f;
                    return false;
                }
            });
        }

        if (!$form || !$form.length) {
            console.log('[VMS Elements Form Guard] Registration Guard: No registration form found.');
            return;
        }

        var $thisForm = $form;

        if ($thisForm.data('vefg-registration-guard')) {
            return;
        }

        // Check if form is already protected by another guard
        if ($thisForm.data('vefg-guard-protected')) {
            console.log('[VMS Elements Form Guard] Registration Guard: Form already protected by another guard, skipping');
            return;
        }

        var $emailField = $thisForm.find('input[type="email"], input[name="user_email"], input[name="email"], input[id="user_email"]').first();

        if (!$emailField.length) {
            return;
        }

        $thisForm.data('vefg-registration-guard', true);
        $thisForm.data('vefg-guard-protected', true); // Mark as protected
        registrationGuardAttached = true;
        console.log('[VMS Elements Form Guard] Registration Guard attached to form:', $thisForm[0]);

        if (!frontendEnabled) {
            if (recaptchaEnabled && recaptchaSiteKey) {
                setupRecaptchaOnly($thisForm);
            }
            return;
        }

        var $originalSubmit = findSubmitButton($thisForm);

        if (!$originalSubmit.length) {
            console.log('[VMS Elements Form Guard] Registration Guard: No submit button found');
            return;
        }

        var $actionsWrap = $('<div class="vefg-guard-actions"></div>');
        $originalSubmit.after($actionsWrap);

        var submitText = getSubmitButtonText($originalSubmit, t('register', 'Register'));
        var $validationBtn = $('<input type="button" class="vefg-validation-btn vefg-reg-validation-btn">');
        $validationBtn.val(submitText);

        copyButtonStyles($originalSubmit, $validationBtn);
        hideOriginalSubmit($originalSubmit);

        if (recaptchaEnabled && recaptchaSiteKey && recaptchaVersion === 'v2') {
            var uniqueId = 'vefg-reg-recaptcha-' + Math.random().toString(36).substr(2, 9);
            var recaptchaContainer = $('<div id="' + uniqueId + '" class="vefg-recaptcha-container" style="margin: 10px 0;"></div>');
            $actionsWrap.prepend(recaptchaContainer);

            $validationBtn.prop('disabled', true).css('opacity', '0.6');

            var checkRecaptcha = function() {
                if (typeof grecaptcha !== 'undefined' && grecaptcha.render) {
                    try {
                        recaptchaWidgetId = grecaptcha.render(recaptchaContainer[0], {
                            sitekey: recaptchaSiteKey,
                            callback: function() {
                                $validationBtn.prop('disabled', false).css('opacity', '1');
                            },
                            'expired-callback': function() {
                                $validationBtn.prop('disabled', true).css('opacity', '0.6');
                            }
                        });
                    } catch (e) {
                        console.log('[Registration Guard] reCAPTCHA already rendered:', e);
                    }
                } else {
                    setTimeout(checkRecaptcha, 100);
                }
            };
            checkRecaptcha();
        }

        $actionsWrap.append($validationBtn);

        var isValidating = false;

        $validationBtn.on('click', function(e) {
            e.preventDefault();

            if (isValidating) {
                return;
            }

            clearAllErrors($thisForm);

            var email = $emailField.val().trim();

            if (!email) {
                showFieldError($emailField, t('emailRequired', 'Email address is required.'));
                return;
            }

            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showFieldError($emailField, t('emailInvalid', 'Please enter a valid email address.'));
                return;
            }

            isValidating = true;
            $validationBtn.val(t('validating', 'Validating...')).prop('disabled', true);

            getRecaptchaToken()
                .then(function(recaptchaToken) {
                    return validateRegistration(email, recaptchaToken);
                })
                .then(function(result) {
                    isValidating = false;
                    $validationBtn.val(submitText).prop('disabled', false);

                    if (result.status) {
                        console.log('[VMS Elements Form Guard] Registration Guard: Validation passed');

                        $thisForm.append('<input type="hidden" name="vefg_validation_token" value="' + (result.token || '') + '">');

                        $originalSubmit.css({
                            'display': '',
                            'position': '',
                            'left': '',
                            'visibility': '',
                            'pointer-events': '',
                            'opacity': '',
                            'width': '',
                            'height': ''
                        });

                        $originalSubmit[0].click();

                        setTimeout(function() {
                            hideOriginalSubmit($originalSubmit);
                        }, 100);
                    } else {
                        showFieldError($emailField, result.message || t('emailInvalid', 'This email address is not accepted.'));
                        showToast('error', result.message || t('emailInvalid', 'This email address is not accepted.'));

                        if (recaptchaEnabled && recaptchaVersion === 'v2' && typeof grecaptcha !== 'undefined' && recaptchaWidgetId !== null) {
                            grecaptcha.reset(recaptchaWidgetId);
                            $validationBtn.prop('disabled', true).css('opacity', '0.6');
                        }
                    }
                })
                .catch(function(err) {
                    isValidating = false;
                    $validationBtn.val(submitText).prop('disabled', recaptchaEnabled && recaptchaVersion === 'v2');
                    console.error('[VMS Elements Form Guard] Registration Guard error:', err);
                    showToast('error', err.message || t('serverError', 'Validation error. Please try again.'));
                });
        });

        $thisForm.on('submit', function(e) {
            if ($thisForm.find('input[name="vefg_validation_token"]').length) {
                return true;
            }

            e.preventDefault();
            e.stopImmediatePropagation();
            $validationBtn.trigger('click');
            return false;
        });
    }

    function setupRecaptchaOnly($form) {
        var $submitBtn = findSubmitButton($form);
        
        if (!$submitBtn.length) {
            console.log('[VMS Elements Form Guard] Registration Guard: No submit button found for reCAPTCHA');
            return;
        }

        if (recaptchaVersion === 'v2') {
            var uniqueId = 'vefg-reg-recaptcha-only-' + Math.random().toString(36).substr(2, 9);
            var $submitParent = $submitBtn.closest('p, .submit').first();
            var $actionsWrap = $('<div class="vefg-guard-actions"></div>');
            $submitParent.before($actionsWrap);
            var recaptchaContainer = $('<div id="' + uniqueId + '" class="vefg-recaptcha-container" style="margin: 10px 0;"></div>');
            $actionsWrap.append(recaptchaContainer);
            $actionsWrap.append($submitParent.detach());

            $submitBtn.prop('disabled', true).css('opacity', '0.6');
            
            var checkRecaptcha = function() {
                if (typeof grecaptcha !== 'undefined' && grecaptcha.render) {
                    try {
                        recaptchaWidgetId = grecaptcha.render(recaptchaContainer[0], {
                            sitekey: recaptchaSiteKey,
                            callback: function(token) {
                                $submitBtn.prop('disabled', false).css('opacity', '1');
                                $form.find('input[name="vefg_recaptcha_token"]').remove();
                                $form.append('<input type="hidden" name="vefg_recaptcha_token" value="' + token + '">');
                            },
                            'expired-callback': function() {
                                $submitBtn.prop('disabled', true).css('opacity', '0.6');
                                $form.find('input[name="vefg_recaptcha_token"]').remove();
                            }
                        });
                    } catch (e) {
                        console.log('[Registration Guard] reCAPTCHA error:', e);
                    }
                } else {
                    setTimeout(checkRecaptcha, 100);
                }
            };
            checkRecaptcha();
        } else if (recaptchaVersion === 'v3') {
            $form.on('submit', function(e) {
                var $hiddenToken = $form.find('input[name="vefg_recaptcha_token"]');
                
                if ($hiddenToken.length && $hiddenToken.val()) {
                    return true;
                }
                
                e.preventDefault();
                
                if (typeof grecaptcha !== 'undefined' && grecaptcha.execute) {
                    grecaptcha.ready(function() {
                        grecaptcha.execute(recaptchaSiteKey, { action: 'registration' })
                            .then(function(token) {
                                $form.find('input[name="vefg_recaptcha_token"]').remove();
                                $form.append('<input type="hidden" name="vefg_recaptcha_token" value="' + token + '">');
                                $form[0].submit();
                            })
                            .catch(function(err) {
                                console.error('[Registration Guard] reCAPTCHA v3 error:', err);
                                $form[0].submit();
                            });
                    });
                } else {
                    $form[0].submit();
                }
                
                return false;
            });
        }
    }

    function loadRecaptchaScript() {
        if (recaptchaEnabled && recaptchaSiteKey) {
            if (typeof grecaptcha !== 'undefined') {
                setupRegistrationGuard();
                return;
            }

            var script = document.createElement('script');
            if (recaptchaVersion === 'v3') {
                script.src = 'https://www.google.com/recaptcha/api.js?render=' + recaptchaSiteKey;
            } else {
                script.src = 'https://www.google.com/recaptcha/api.js';
            }
            script.async = true;
            script.defer = true;
            script.onload = function() {
                setupRegistrationGuard();
            };
            document.head.appendChild(script);
        } else {
            setupRegistrationGuard();
        }
    }

    $(document).ready(function() {
        loadRecaptchaScript();
    });

})(jQuery);
