/**
 * VMS Elements Form Guard - Contact Guard
 * Validates contact forms with email and message fields using a validation button approach.
 */
(function($) {
    'use strict';

    if (typeof VEFGContactGuard === 'undefined') {
        return;
    }

    var config = VEFGContactGuard;
    var ajaxUrl = config.ajaxUrl || '';
    var nonce = config.nonce || '';
    var formSelector = config.formSelector || '';
    var submitSelector = config.submitSelector || '';
    var i18n = config.i18n || {};
    var recaptchaEnabled = config.recaptchaEnabled || false;
    var recaptchaSiteKey = config.recaptchaSiteKey || '';
    var recaptchaVersion = config.recaptchaVersion || 'v2';

    /** Only one contact form per page gets validation + reCAPTCHA (avoids duplicates when multiple forms match). */
    var contactGuardAttached = false;

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
        } else {
            alert(title);
        }
    }

    /** If the selector matches a wrapper, use the first nested form. */
    function resolveFormElement(el) {
        var $el = $(el);
        if ($el.is('form')) {
            return el;
        }
        var $inner = $el.find('form').first();
        return $inner.length ? $inner[0] : null;
    }

    function detectContactForms() {
        if (contactGuardAttached) {
            return [];
        }

        // Form selector is required - only use provided selector
        if (!formSelector || formSelector.trim() === '') {
            console.log('[VMS Elements Form Guard] Contact Guard: No form selector configured. Form selector is required.');
            return [];
        }

        var matches = $(formSelector);
        console.log('[VMS Elements Form Guard] Contact Guard: Checking selector:', formSelector, 'found:', matches.length, 'elements');
        for (var i = 0; i < matches.length; i++) {
            var formEl = resolveFormElement(matches[i]);
            if (!formEl) {
                continue;
            }
            var $form = $(formEl);
            if ($form.data('vefg-contact-guard')) {
                console.log('[VMS Elements Form Guard] Contact Guard: Form already has contact guard');
                continue;
            }
            // Check if form is already protected by another guard (Form Guard, Subscribe Guard, etc.)
            if ($form.data('vefg-guard-protected')) {
                console.log('[VMS Elements Form Guard] Contact Guard: Form already protected by another guard');
                continue;
            }
            if (formEl.id === 'adminbarsearch' || $form.closest('#wpadminbar').length > 0) {
                continue;
            }
            if (findEmailField($form).length === 0) {
                continue;
            }
            return [formEl];
        }

        return [];
    }

    function isContactForm($form) {
        var $emailInputs = $form.find('input[type="email"], input[name*="email"], input[name*="Email"]');
        var $textareas = $form.find('textarea');
        var $submitBtn = $form.find('input[type="submit"], button[type="submit"], button:not([type])');

        var formId = ($form.attr('id') || '').toLowerCase();
        var formClass = ($form.attr('class') || '').toLowerCase();
        var formAction = ($form.attr('action') || '').toLowerCase();

        var keywords = ['contact', 'inquiry', 'enquiry', 'message', 'feedback', 'support', 'wpcf7', 'wpforms', 'formidable', 'ninja'];
        var hasKeyword = keywords.some(function(kw) {
            return formId.indexOf(kw) !== -1 || 
                   formClass.indexOf(kw) !== -1 || 
                   formAction.indexOf(kw) !== -1;
        });

        if (hasKeyword && $emailInputs.length > 0) {
            return true;
        }

        if ($emailInputs.length >= 1 && $textareas.length >= 1 && $submitBtn.length > 0) {
            return true;
        }

        return false;
    }

    function findEmailField($form) {
        return $form.find('input[type="email"], input[name*="email"], input[name*="Email"]').first();
    }

    function findMessageField($form) {
        return $form.find('textarea').first();
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
        // 0. Use custom submit selector if provided
        if (submitSelector && submitSelector.trim() !== '') {
            var $btn = findVisibleSubmit($form, submitSelector);
            if ($btn.length) {
                console.log('[VMS Elements Form Guard] Contact Guard: Found submit via custom selector:', submitSelector);
                return $btn;
            }
        }
        
        // 1. input[type="submit"] - visible
        var $btn = findVisibleSubmit($form, 'input[type="submit"]');
        if ($btn.length) return $btn;
        
        // 2. button[type="submit"] - visible, exclude hidden defaults
        $btn = findVisibleSubmit($form, 'button[type="submit"]:not(.quform-default-submit)');
        if ($btn.length) return $btn;
        
        // 3. Common submit classes for contact forms - visible
        $btn = findVisibleSubmit($form, '.wpcf7-submit, .quform-submit:not(.quform-default-submit), .wpforms-submit, .sib-default-btn');
        if ($btn.length) return $btn;
        
        // 4. Any element with type="submit" - visible
        $btn = findVisibleSubmit($form, '[type="submit"]');
        if ($btn.length) return $btn;
        
        // 5. Button without type (defaults to submit in HTML) - visible
        $btn = findVisibleSubmit($form, 'button:not([type]):not([aria-hidden="true"])');
        if ($btn.length) return $btn;
        
        // 6. Button not explicitly button/reset - visible
        $btn = findVisibleSubmit($form, 'button:not([type="button"]):not([type="reset"]):not([aria-hidden="true"])');
        if ($btn.length) return $btn;
        
        // 7. Generic submit classes - visible
        $btn = findVisibleSubmit($form, '.submit, .btn-submit, .form-submit');
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

    function validateContact(email, message, recaptchaToken) {
        return new Promise(function(resolve, reject) {
            var data = {
                action: 'vefg_validate_contact',
                nonce: nonce,
                email: email,
                message: message
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
                        resolve({ status: false, errors: [{ field: 'form', message: t('validationFailed', 'Validation failed.') }] });
                    }
                },
                error: function() {
                    reject(new Error(t('serverError', 'Server error. Please try again.')));
                }
            });
        });
    }

    function showFieldError($field, message) {
        clearFieldError($field);
        $field.addClass('vefg-field-invalid');
        var $error = $('<span class="vefg-field-error vefg-contact-error">' + message + '</span>');
        $field.after($error);
    }

    function clearFieldError($field) {
        $field.removeClass('vefg-field-invalid');
        $field.siblings('.vefg-contact-error').remove();
        $field.parent().find('.vefg-contact-error').remove();
    }

    function clearAllErrors($form) {
        $form.find('.vefg-field-invalid').removeClass('vefg-field-invalid');
        $form.find('.vefg-contact-error').remove();
    }

    function copyButtonStyles($source, $target) {
        if (!$source.length) return;
        
        var computed = window.getComputedStyle($source[0]);
        var styles = {
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
            'min-width': computed.minWidth,
            'min-height': computed.minHeight,
            'line-height': computed.lineHeight,
            'text-transform': computed.textTransform,
            'letter-spacing': computed.letterSpacing,
            'box-shadow': computed.boxShadow,
            'cursor': 'pointer',
            'display': 'inline-block',
            'text-align': computed.textAlign,
            'vertical-align': computed.verticalAlign
        };
        $target.css(styles);
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

    function getRecaptchaToken() {
        return new Promise(function(resolve, reject) {
            if (!recaptchaEnabled || !recaptchaSiteKey) {
                resolve(null);
                return;
            }

            if (recaptchaVersion === 'v3') {
                if (typeof grecaptcha !== 'undefined' && grecaptcha.execute) {
                    grecaptcha.ready(function() {
                        grecaptcha.execute(recaptchaSiteKey, { action: 'contact_guard' })
                            .then(function(token) {
                                resolve(token);
                            })
                            .catch(function(err) {
                                console.error('[Contact Guard] reCAPTCHA v3 error:', err);
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

    var recaptchaWidgetId = null;

    function renderRecaptcha($actionsWrap, $validationBtn) {
        if (!recaptchaEnabled || !recaptchaSiteKey) {
            return;
        }

        if (recaptchaVersion === 'v2') {
            var uniqueId = 'vefg-recaptcha-' + Math.random().toString(36).substr(2, 9);
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
                        console.log('[Contact Guard] reCAPTCHA already rendered or error:', e);
                    }
                } else {
                    setTimeout(checkRecaptcha, 100);
                }
            };
            checkRecaptcha();
        }
    }

    function setupGuard(form) {
        var $form = $(form);
        
        if ($form.data('vefg-contact-guard')) {
            return;
        }

        // Check if form is already protected by another guard (Form Guard, Subscribe Guard, etc.)
        if ($form.data('vefg-guard-protected')) {
            console.log('[VMS Elements Form Guard] Contact Guard: Form already protected by another guard, skipping');
            return;
        }

        var $emailField = findEmailField($form);
        var $messageField = findMessageField($form);

        if ($emailField.length === 0) {
            return;
        }

        $form.data('vefg-contact-guard', true);
        $form.data('vefg-guard-protected', true); // Mark as protected
        contactGuardAttached = true;
        console.log('[VMS Elements Form Guard] Contact Guard attached to form:', form);

        var $originalSubmit = findSubmitButton($form);
        if (!$originalSubmit.length) {
            console.log('[VMS Elements Form Guard] Contact Guard: No submit button found');
            return;
        }

        var $actionsWrap = $('<div class="vefg-guard-actions"></div>');
        $originalSubmit.after($actionsWrap);

        var submitText = getSubmitButtonText($originalSubmit, t('submit', 'Submit'));
        var $validationBtn = $('<input type="button" class="vefg-validation-btn vefg-contact-validation-btn">');
        $validationBtn.val(submitText);
        
        copyButtonStyles($originalSubmit, $validationBtn);
        hideOriginalSubmit($originalSubmit);

        renderRecaptcha($actionsWrap, $validationBtn);
        $actionsWrap.append($validationBtn);

        var isValidating = false;

        $validationBtn.on('click', function(e) {
            e.preventDefault();
            
            if (isValidating) {
                return;
            }

            clearAllErrors($form);

            var email = $emailField.val().trim();
            var message = $messageField.length > 0 ? $messageField.val().trim() : '';

            var hasError = false;
            $form.find('[required]').each(function() {
                var $field = $(this);
                var val = $field.val();
                if (!val || val.trim() === '') {
                    showFieldError($field, t('fieldRequired', 'This field is required.'));
                    hasError = true;
                }
            });

            if (hasError) {
                showToast('error', t('checkFields', 'Please fill in all required fields.'));
                return;
            }

            if (!email) {
                showFieldError($emailField, t('emailRequired', 'Email address is required.'));
                showToast('error', t('emailRequired', 'Email address is required.'));
                return;
            }

            var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showFieldError($emailField, t('emailInvalid', 'Please enter a valid email address.'));
                showToast('error', t('emailInvalid', 'Please enter a valid email address.'));
                return;
            }

            isValidating = true;
            $validationBtn.val(t('validating', 'Validating...')).prop('disabled', true);

            getRecaptchaToken()
                .then(function(recaptchaToken) {
                    return validateContact(email, message, recaptchaToken);
                })
                .then(function(result) {
                    isValidating = false;
                    $validationBtn.val(submitText).prop('disabled', false);

                    if (result.blocked) {
                        var blockMessage = result.strike_message || t('userBlocked', 'You have been blocked due to repeated violations.');
                        $validationBtn.prop('disabled', true).css('opacity', '0.5');
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                icon: 'error',
                                title: t('blocked', 'Blocked'),
                                text: blockMessage,
                                confirmButtonColor: '#d33'
                            });
                        } else {
                            alert(blockMessage);
                        }
                        return;
                    }

                    if (result.status) {
                        console.log('[VMS Elements Form Guard] Contact Guard: Validation passed, submitting form');
                        
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
                        if (result.errors && result.errors.length > 0) {
                            result.errors.forEach(function(err) {
                                if (err.field === 'email') {
                                    showFieldError($emailField, err.message);
                                } else if (err.field === 'message' && $messageField.length > 0) {
                                    showFieldError($messageField, err.message);
                                }
                            });
                            showToast('error', result.errors[0].message);
                        } else if (result.message) {
                            showToast('error', result.message);
                        } else {
                            showToast('error', t('validationFailed', 'Validation failed.'));
                        }

                        if (recaptchaEnabled && recaptchaVersion === 'v2' && typeof grecaptcha !== 'undefined' && recaptchaWidgetId !== null) {
                            grecaptcha.reset(recaptchaWidgetId);
                            $validationBtn.prop('disabled', true).css('opacity', '0.6');
                        }
                    }
                })
                .catch(function(err) {
                    isValidating = false;
                    $validationBtn.val(submitText).prop('disabled', recaptchaEnabled && recaptchaVersion === 'v2');
                    console.error('[VMS Elements Form Guard] Contact Guard error:', err);
                    showToast('error', err.message || t('serverError', 'Validation error. Please try again.'));
                });
        });

        $form.on('submit', function(e) {
            e.preventDefault();
            e.stopImmediatePropagation();
            $validationBtn.trigger('click');
            return false;
        });
    }

    function loadRecaptchaScript() {
        if (!recaptchaEnabled || !recaptchaSiteKey) {
            return;
        }

        if (typeof grecaptcha !== 'undefined') {
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
        document.head.appendChild(script);
    }

    function init() {
        console.log('[VMS Elements Form Guard] Contact Guard initializing...');
        
        loadRecaptchaScript();
        
        var forms = detectContactForms();
        console.log('[VMS Elements Form Guard] Contact Guard found', forms.length, 'form(s)');
        
        forms.forEach(function(form) {
            setupGuard(form);
        });

        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function(mutations) {
                if (contactGuardAttached) {
                    return;
                }
                mutations.forEach(function(mutation) {
                    if (mutation.addedNodes.length > 0) {
                        setTimeout(function() {
                            if (contactGuardAttached) {
                                return;
                            }
                            var newForms = detectContactForms();
                            newForms.forEach(function(form) {
                                if (!$(form).data('vefg-contact-guard')) {
                                    setupGuard(form);
                                }
                            });
                        }, 100);
                    }
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    $(document).ready(function() {
        init();
    });

})(jQuery);
