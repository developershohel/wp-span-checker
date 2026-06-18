/**
 * Form Guard registry for tracking guarded forms.
 */
(function () {
    if (typeof window === 'undefined') {
        return;
    }
    var g = (window.vefgFormGuardState = window.vefgFormGuardState || {});
    if (!g.registry) {
        g.registry =
            typeof WeakMap !== 'undefined' ? new WeakMap() : typeof Map !== 'undefined' ? new Map() : null;
    }
})();

jQuery(function ($) {
    const vefgToast = window.vefgToast;
    console.log('[VMS Elements Form Guard] Form Guard initializing...');
    
    const I = typeof VEFGChecker !== 'undefined' && VEFGChecker.i18n ? VEFGChecker.i18n : {};
    const t = function (key, fallback) {
        return I[key] !== undefined && I[key] !== '' ? I[key] : fallback;
    };

    function getSubmitButtonText($btn, fallback) {
        const raw = $btn.val() || $btn.text() || fallback || '';
        return String(raw).replace(/\s+/g, ' ').trim();
    }

    const settings = (typeof VEFGChecker !== 'undefined' && VEFGChecker.settings) ? VEFGChecker.settings : [];
    const ajaxUrl = (typeof VEFGChecker !== 'undefined' && (VEFGChecker.ajaxUrl || VEFGChecker.ajaxurl))
        || (typeof ajaxurl !== 'undefined' ? ajaxurl : '');
    const nonce = (typeof VEFGChecker !== 'undefined' && VEFGChecker.nonce) ? VEFGChecker.nonce : '';
    const pageID = (typeof VEFGChecker !== 'undefined' && VEFGChecker.pageID) ? VEFGChecker.pageID : 0;
    const pageType = (typeof VEFGChecker !== 'undefined' && VEFGChecker.pageType) ? VEFGChecker.pageType : 'common';
    const bodyClasses = (typeof VEFGChecker !== 'undefined' && VEFGChecker.bodyClasses) ? VEFGChecker.bodyClasses : [];
    const presetClasses = (typeof VEFGChecker !== 'undefined' && VEFGChecker.presetClasses) ? VEFGChecker.presetClasses : {};

    /**
     * Check if a form should be skipped (admin bar, wp-admin forms, etc.)
     */
    function vefgShouldSkipForm($form) {
        if (!$form.length) return true;
        
        // Skip admin bar search form
        if ($form.attr('id') === 'adminbarsearch') return true;
        
        // Skip any form inside wp-admin bar
        if ($form.closest('#wpadminbar').length) return true;
        
        // Skip forms with admin-related IDs
        const formId = ($form.attr('id') || '').toLowerCase();
        if (formId.indexOf('adminbar') !== -1) return true;
        
        // Skip forms with action pointing to wp-admin
        const action = ($form.attr('action') || '').toLowerCase();
        if (action.indexOf('/wp-admin/') !== -1) return true;
        
        return false;
    }

    /**
     * Check if a form is already protected by any VMS Elements Form Guard guard.
     * This prevents multiple guards from applying to the same form.
     */
    function vefgIsFormAlreadyProtected($form) {
        if (!$form.length) return false;
        return $form.data('vefg-guard-protected') === true;
    }

    /**
     * Mark a form as protected by VMS Elements Form Guard.
     */
    function vefgMarkFormAsProtected($form) {
        if ($form.length) {
            $form.data('vefg-guard-protected', true);
        }
    }

    /**
     * Get all content forms (excluding admin bar and wp-admin forms)
     */
    function vefgGetContentForms() {
        return $('form').filter(function() {
            return !vefgShouldSkipForm($(this));
        });
    }

    console.log('VEFGChecker', VEFGChecker);
    console.log('ajaxUrl', ajaxUrl);
    console.log('nonce', nonce);
    console.log('pageType', pageType);
    console.log('bodyClasses', bodyClasses);
    console.log('[VMS Elements Form Guard] Settings loaded:', settings.length, 'mapping(s)');

    function vefgBodyHasClass(className) {
        if (!className) return false;
        return $('body').hasClass(className) || bodyClasses.indexOf(className) !== -1;
    }

    function vefgNormalizePageTargets(raw) {
        if (!raw) return [];
        if (Array.isArray(raw)) return raw.map(String).filter(Boolean);
        const s = String(raw).trim();
        if (!s) return [];
        try {
            const d = JSON.parse(s);
            if (Array.isArray(d)) return d.map(String).filter(Boolean);
        } catch (e) {}
        return [s];
    }

    function vefgSettingMatchesCurrentPage(setting) {
        const targets = vefgNormalizePageTargets(setting.page_id);
        const formSelector = String(setting.form_id || '').trim();
        
        if (targets.length === 0) {
            return { matches: true, requiresFormSelector: false };
        }
        
        for (let i = 0; i < targets.length; i++) {
            const target = targets[i];
            
            if (target === 'all-pages') {
                if (!formSelector) {
                    console.log('[VMS Elements Form Guard] Entire site target requires form selector');
                    return { matches: false, requiresFormSelector: true };
                }
                return { matches: true, requiresFormSelector: true };
            }
            
            if (/^\d+$/.test(target)) {
                const targetId = parseInt(target, 10);
                if (pageType === 'page' && vefgBodyHasClass('page-id-' + targetId)) {
                    console.log('[VMS Elements Form Guard] Matched page ID:', targetId);
                    return { matches: true, requiresFormSelector: false };
                }
                if (pageType === 'post' && vefgBodyHasClass('postid-' + targetId)) {
                    console.log('[VMS Elements Form Guard] Matched post ID:', targetId);
                    return { matches: true, requiresFormSelector: false };
                }
                if (targetId === pageID) {
                    console.log('[VMS Elements Form Guard] Matched by pageID:', targetId);
                    return { matches: true, requiresFormSelector: false };
                }
                continue;
            }
            
            const presetClass = presetClasses[target];
            if (presetClass && vefgBodyHasClass(presetClass)) {
                console.log('[VMS Elements Form Guard] Matched preset:', target, 'via class:', presetClass);
                return { matches: true, requiresFormSelector: false };
            }
            
            if (target === 'front-page' && vefgBodyHasClass('home')) {
                return { matches: true, requiresFormSelector: false };
            }
            if (target === 'home-blog' && vefgBodyHasClass('blog')) {
                return { matches: true, requiresFormSelector: false };
            }
            if (target === 'singular-page' && pageType === 'page') {
                return { matches: true, requiresFormSelector: false };
            }
            if (target === 'singular-post' && pageType === 'post') {
                return { matches: true, requiresFormSelector: false };
            }
            if (target === 'singular-any' && (pageType === 'page' || pageType === 'post' || vefgBodyHasClass('singular'))) {
                return { matches: true, requiresFormSelector: false };
            }
            if (target === 'archive-any' && vefgBodyHasClass('archive')) {
                return { matches: true, requiresFormSelector: false };
            }
            if (target === 'archive-category' && vefgBodyHasClass('category')) {
                return { matches: true, requiresFormSelector: false };
            }
            if (target === 'archive-tag' && vefgBodyHasClass('tag')) {
                return { matches: true, requiresFormSelector: false };
            }
            if (target === 'search' && vefgBodyHasClass('search')) {
                return { matches: true, requiresFormSelector: false };
            }
            if (target === '404' && vefgBodyHasClass('error404')) {
                return { matches: true, requiresFormSelector: false };
            }
        }
        
        return { matches: false, requiresFormSelector: false };
    }

    const AUTO_FIELD_NAMES = {
        email: ['email', 'user_email', 'your-email', 'mail', 'e-mail'],
        username: ['username', 'user_login', 'user_name', 'login', 'user', 'nickname'],
        password: ['password', 'user_pass', 'pass', 'pwd', 'user_password'],
        message: ['message', 'comment', 'content', 'body', 'your-message', 'description'],
        name: ['name', 'first_name', 'last_name', 'full_name', 'your-name', 'author'],
        phone: ['phone', 'tel', 'telephone', 'mobile', 'cell'],
        url: ['url', 'website', 'web', 'site', 'link', 'homepage', 'webpage', 'your-url', 'your-website']
    };

    function vefgDetectFieldType($field) {
        const type = ($field.attr('type') || '').toLowerCase();
        const name = ($field.attr('name') || '').toLowerCase();
        const tagName = ($field.prop('tagName') || '').toLowerCase();
        
        if (type === 'email' || AUTO_FIELD_NAMES.email.some(function(n) { return name.indexOf(n) !== -1; })) {
            return 'email';
        }
        if (type === 'url' || AUTO_FIELD_NAMES.url.some(function(n) { return name.indexOf(n) !== -1; })) {
            return 'url';
        }
        if (type === 'password' || AUTO_FIELD_NAMES.password.some(function(n) { return name.indexOf(n) !== -1; })) {
            return 'password';
        }
        if (tagName === 'textarea' || AUTO_FIELD_NAMES.message.some(function(n) { return name.indexOf(n) !== -1; })) {
            return 'textarea';
        }
        if (AUTO_FIELD_NAMES.username.some(function(n) { return name.indexOf(n) !== -1; })) {
            return 'username';
        }
        if (type === 'tel' || AUTO_FIELD_NAMES.phone.some(function(n) { return name.indexOf(n) !== -1; })) {
            return 'tel';
        }
        if (type === 'text') {
            return 'text';
        }
        return null;
    }

    function vefgAutoDetectFormFields($form, autoRules) {
        const fields = [];
        const rules = autoRules || {};
        
        console.log('[VMS Elements Form Guard] Auto-detecting fields with rules:', rules);
        
        $form.find('input, textarea, select').each(function() {
            const $field = $(this);
            const type = ($field.attr('type') || '').toLowerCase();
            const name = ($field.attr('name') || '').toLowerCase();
            
            // Skip hidden, submit, button, reset fields
            if (type === 'hidden' || type === 'submit' || type === 'button' || type === 'reset') {
                return;
            }
            
            // Skip fields inside admin bar
            if ($field.closest('#wpadminbar').length) {
                return;
            }
            
            const detectedType = vefgDetectFieldType($field);
            if (!detectedType) return;
            
            const isRequired = $field.prop('required') || $field.attr('required') !== undefined;
            
            const fieldConfig = {
                $el: $field,
                type: detectedType,
                name: $field.attr('name') || '',
                required: isRequired,
                rules: {}
            };
            
            switch (detectedType) {
                case 'email':
                    if (rules.email) {
                        fieldConfig.rules = {
                            validate: !!rules.email.validate,
                            mx: !!rules.email.mx,
                            disposable: !!rules.email.disposable,
                            webrisk: !!rules.email.webrisk,
                            virustotal: !!rules.email.virustotal
                        };
                    }
                    console.log('[VMS Elements Form Guard] Email field:', $field.attr('name'), 'required:', isRequired, 'rules:', fieldConfig.rules);
                    break;
                case 'url':
                    // URL fields should allow URLs - apply URL validation rules, NOT block_urls
                    if (rules.url) {
                        fieldConfig.rules = {
                            validate: !!rules.url.validate,
                            webrisk: !!rules.url.webrisk,
                            virustotal: !!rules.url.virustotal
                        };
                    }
                    console.log('[VMS Elements Form Guard] URL/Website field:', $field.attr('name'), 'required:', isRequired, 'rules:', fieldConfig.rules);
                    break;
                case 'textarea':
                    if (rules.textarea) {
                        fieldConfig.rules = {
                            block_links: !!rules.textarea.block_links,
                            ai_spam: !!rules.textarea.ai_spam
                        };
                    }
                    console.log('[VMS Elements Form Guard] Textarea field:', $field.attr('name'), 'required:', isRequired, 'rules:', fieldConfig.rules, 'autoRules.textarea:', rules.textarea);
                    break;
                case 'username':
                    if (rules.username) {
                        fieldConfig.rules = {
                            check_exists: !!rules.username.check_exists
                        };
                    }
                    console.log('[VMS Elements Form Guard] Username field:', $field.attr('name'), 'required:', isRequired, 'rules:', fieldConfig.rules);
                    break;
                case 'password':
                    if (rules.password) {
                        fieldConfig.rules = {
                            strength: !!rules.password.strength
                        };
                    }
                    console.log('[VMS Elements Form Guard] Password field:', $field.attr('name'), 'required:', isRequired, 'rules:', fieldConfig.rules);
                    break;
                case 'text':
                    // Only apply block_urls to generic text fields, NOT to URL-type fields
                    if (rules.text) {
                        fieldConfig.rules = {
                            block_urls: !!rules.text.block_urls
                        };
                    }
                    console.log('[VMS Elements Form Guard] Text field:', $field.attr('name'), 'required:', isRequired, 'rules:', fieldConfig.rules, 'rules.text:', rules.text);
                    break;
            }
            
            fields.push(fieldConfig);
        });
        
        console.log('[VMS Elements Form Guard] Total fields detected:', fields.length);
        return fields;
    }

    /**
     * Detect common spam patterns in text (client-side).
     */
    function vefgDetectSpamPatterns(text) {
        if (!text || typeof text !== 'string') {
            return { isSpam: false };
        }
        
        const lowerText = text.toLowerCase();
        
        // Spam phrases (promotional, scam, SEO spam)
        const spamPhrases = [
            // Money/earning scams
            'earn money', 'make money', 'earn \\$', 'make \\$', '\\$\\d+.*daily', '\\$\\d+.*per day',
            'work from home', 'online job', 'easy money', 'quick cash', 'get rich',
            'financial freedom', 'passive income', 'bitcoin profit', 'crypto profit',
            // Click bait
            'click here', 'click now', 'act now', 'limited time', 'hurry up',
            'don\'t miss', 'exclusive offer', 'special offer', 'free gift',
            // Pills/pharma
            'viagra', 'cialis', 'pharmacy', 'buy pills', 'weight loss', 'diet pills',
            // SEO spam
            'buy backlinks', 'seo services', 'rank #1', 'google ranking',
            'increase traffic', 'boost your', 'guaranteed results',
            // Adult/gambling
            'casino', 'poker online', 'betting', 'adult content', 'xxx',
            // Loan/credit scams
            'loan approved', 'bad credit ok', 'no credit check', 'instant loan',
            // Contact harvesting
            'contact me at', 'email me at', 'whatsapp me', 'telegram me',
            // Essay/homework services
            'essay writing', 'do your homework', 'assignment help', 'thesis writing'
        ];
        
        for (var i = 0; i < spamPhrases.length; i++) {
            var pattern = new RegExp(spamPhrases[i], 'i');
            if (pattern.test(lowerText)) {
                return { isSpam: true, message: t('spamDetected', 'Your message appears to be spam.') };
            }
        }
        
        // Check for repeated email patterns
        const emailPattern = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;
        const emails = text.match(emailPattern) || [];
        if (emails.length > 2) {
            return { isSpam: true, message: t('spamDetected', 'Your message appears to be spam.') };
        }
        if (emails.length >= 2) {
            const uniqueEmails = [...new Set(emails)];
            if (uniqueEmails.length === 1) {
                return { isSpam: true, message: t('spamDetected', 'Your message appears to be spam.') };
            }
        }
        
        // Check for excessive URLs
        const urlPattern = /https?:\/\/[^\s]+/gi;
        const urls = text.match(urlPattern) || [];
        if (urls.length > 3) {
            return { isSpam: true, message: t('tooManyLinks', 'Too many links in your message.') };
        }
        
        // Check for excessive repetition
        const words = lowerText.split(/\s+/).filter(function(w) { return w.length > 3; });
        if (words.length > 5) {
            const wordCounts = {};
            words.forEach(function(w) { wordCounts[w] = (wordCounts[w] || 0) + 1; });
            const maxRepeat = Math.max.apply(null, Object.values(wordCounts));
            if (maxRepeat > words.length * 0.5) {
                return { isSpam: true, message: t('spamDetected', 'Your message appears to be spam.') };
            }
        }
        
        // Check for all caps (shouting)
        const upperCount = (text.match(/[A-Z]/g) || []).length;
        const letterCount = (text.match(/[a-zA-Z]/g) || []).length;
        if (letterCount > 20 && upperCount / letterCount > 0.7) {
            return { isSpam: true, message: t('spamDetected', 'Your message appears to be spam.') };
        }
        
        return { isSpam: false };
    }

    /**
     * Get a readable label for a field (for error messages).
     */
    function vefgGetFieldLabel($field) {
        // Try to find associated label
        const id = $field.attr('id');
        if (id) {
            const $label = $('label[for="' + id + '"]');
            if ($label.length) {
                return $label.text().replace(/[*:]/g, '').trim();
            }
        }
        
        // Try placeholder
        const placeholder = $field.attr('placeholder');
        if (placeholder) {
            return placeholder;
        }
        
        // Try name attribute
        const name = $field.attr('name') || '';
        if (name) {
            return name.replace(/[-_\[\]]/g, ' ').replace(/\s+/g, ' ').trim();
        }
        
        return 'This field';
    }

    /**
     * Show inline error message below a field.
     */
    function vefgShowFieldError($field, message) {
        vefgClearFieldError($field);
        
        if (!message) return;
        
        const $error = $('<span class="vefg-field-error">' + message + '</span>');
        $field.addClass('vefg-field-invalid');
        
        // Try to find the best place to insert error
        const $parent = $field.parent();
        
        // Check if field is wrapped in a common form wrapper
        if ($parent.is('span, div, label, p')) {
            $parent.append($error);
        } else {
            $field.after($error);
        }
    }
    
    /**
     * Clear inline error message from a field.
     */
    function vefgClearFieldError($field) {
        $field.removeClass('vefg-field-invalid');
        
        const $parent = $field.parent();
        $parent.find('.vefg-field-error').remove();
        $field.siblings('.vefg-field-error').remove();
    }
    
    /**
     * Clear all field errors in a form.
     */
    function vefgClearAllFieldErrors($form) {
        $form.find('.vefg-field-invalid').removeClass('vefg-field-invalid');
        $form.find('.vefg-field-error').remove();
    }

    function vefgValidateAutoField(fieldConfig) {
        const $field = fieldConfig.$el;
        const value = String($field.val() || '').trim();
        const rules = fieldConfig.rules;
        const isRequired = fieldConfig.required;
        
        // Check required fields first
        if (isRequired && !value) {
            const fieldName = vefgGetFieldLabel($field);
            return { valid: false, message: t('fieldRequired', 'This field is required.'), $field: $field };
        }
        
        // If empty and not required, skip other validations
        if (!value) {
            return { valid: true, message: '', $field: $field };
        }
        
        switch (fieldConfig.type) {
            case 'email':
                if (rules.validate && !isValidEmail(value)) {
                    return { valid: false, message: t('emailInvalidFormat', 'Please enter a valid email address.'), $field: $field };
                }
                break;
            case 'url':
                if (rules.validate && !isValidUrl(value)) {
                    return { valid: false, message: t('urlInvalidFormat', 'Please enter a valid URL.'), $field: $field };
                }
                break;
            case 'password':
                if (rules.strength) {
                    const hasUpper = /[A-Z]/.test(value);
                    const hasLower = /[a-z]/.test(value);
                    const hasNumber = /\d/.test(value);
                    const hasSymbol = /[^A-Za-z0-9]/.test(value);
                    const isLong = value.length >= 8;
                    if (!(hasUpper && hasLower && hasNumber && hasSymbol && isLong)) {
                        return { valid: false, message: t('passwordWeak', 'Password must be at least 8 characters with uppercase, lowercase, number and symbol.'), $field: $field };
                    }
                }
                break;
            case 'textarea':
                if (rules.block_links) {
                    const urlPattern = /https?:\/\/[^\s<>"']+/i;
                    if (urlPattern.test(value)) {
                        return { valid: false, message: t('linksNotAllowed', 'Links are not allowed in this field.'), $field: $field };
                    }
                }
                // Basic client-side spam detection (before AI check)
                if (rules.ai_spam) {
                    const spamResult = vefgDetectSpamPatterns(value);
                    if (spamResult.isSpam) {
                        return { valid: false, message: spamResult.message || t('spamDetected', 'Your message appears to be spam.'), $field: $field };
                    }
                }
                break;
            case 'text':
                console.log('[VMS Elements Form Guard] Validating text field, block_urls:', rules.block_urls, 'value:', value);
                if (rules.block_urls) {
                    // Match URLs: http(s)://, www., or domain.ext patterns
                    const urlPatterns = [
                        /https?:\/\/[^\s<>"']+/i,                    // http:// or https://
                        /www\.[^\s<>"']+/i,                          // www.
                        /[a-zA-Z0-9][-a-zA-Z0-9]*\.[a-zA-Z]{2,}(?:\/[^\s<>"']*)?/i  // domain.tld
                    ];
                    const hasUrl = urlPatterns.some(function(pattern) {
                        const matches = pattern.test(value);
                        if (matches) console.log('[VMS Elements Form Guard] URL pattern matched:', pattern);
                        return matches;
                    });
                    if (hasUrl) {
                        console.log('[VMS Elements Form Guard] URL detected in text field, blocking');
                        return { valid: false, message: t('urlsNotAllowed', 'URLs are not allowed in this field.'), $field: $field };
                    }
                }
                break;
        }
        
        return { valid: true, message: '', $field: $field };
    }

    /**
     * Single AJAX call to validate ALL fields at once.
     */
    function vefgValidateAllFieldsServer(fields, recaptchaToken) {
        return new Promise(function(resolve, reject) {
            if (!fields || fields.length === 0) {
                resolve({ status: true });
                return;
            }
            
            // Prepare fields data (remove jQuery objects)
            const fieldsData = fields.map(function(f) {
                return {
                    type: f.type,
                    mappingId: f.mappingId,
                    fieldType: f.fieldType,
                    fieldName: f.fieldName,
                    fieldIndex: f.fieldIndex,
                    value: f.value,
                    rules: f.rules
                };
            });
            
            var requestData = {
                action: 'vefg_validate_all_fields',
                nonce: nonce,
                fields: JSON.stringify(fieldsData)
            };
            
            if (recaptchaToken) {
                requestData.recaptcha_token = recaptchaToken;
            }
            
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: requestData,
                dataType: 'json',
                success: function(response) {
                    if (response && response.success) {
                        resolve(response.data || { status: true });
                    } else {
                        resolve(response.data || { status: false, message: t('validationFailed', 'Validation failed') });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('[VMS Elements Form Guard] AJAX error:', error);
                    reject(new Error(t('serverError', 'Server error. Please try again.')));
                }
            });
        });
    }

    function vefgAutoValidateServer(mappingId, fieldConfig, value) {
        return new Promise(function(resolve, reject) {
            $.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vefg_validate_auto_field',
                    nonce: nonce,
                    mappingId: mappingId,
                    fieldType: fieldConfig.type,
                    fieldName: fieldConfig.name,
                    value: value,
                    rules: JSON.stringify(fieldConfig.rules)
                },
                success: function(response) {
                    if (response && response.success && response.data) {
                        resolve(response.data);
                    } else {
                        const msg = response && response.data && response.data.message
                            ? response.data.message
                            : t('validationFailed', 'Validation failed');
                        reject(new Error(msg));
                    }
                },
                error: function(xhr) {
                    reject(new Error(xhr.statusText || 'Request failed'));
                }
            });
        });
    }

    function vefgFindFormForSetting(setting, matchResult) {
        const formSelector = String(setting.form_id || '').trim();
        const submitSelector = String(setting.submit_selector || '').trim();
        
        // If form selector is provided, ONLY use that selector - no fallback
        if (formSelector) {
            const $form = resolveForm$(formSelector, setting.form_class);
            if ($form.length && !vefgShouldSkipForm($form)) {
                console.log('[VMS Elements Form Guard] Form found by selector:', formSelector);
                return $form;
            }
            // Form selector was specified but not found - do NOT fall back
            console.log('[VMS Elements Form Guard] Form not found for selector:', formSelector, '- NOT falling back to auto-detection');
            return $();
        }
        
        // Only auto-detect if NO form selector was provided and page doesn't require it
        if (!matchResult.requiresFormSelector) {
            if (submitSelector) {
                const $submitBtn = $(submitSelector).first();
                if ($submitBtn.length) {
                    const $form = $submitBtn.closest('form');
                    if ($form.length && !vefgShouldSkipForm($form)) {
                        console.log('[VMS Elements Form Guard] Form found via submit selector');
                        return $form;
                    }
                }
            }
            
            const $forms = vefgGetContentForms();
            if ($forms.length === 1) {
                console.log('[VMS Elements Form Guard] Single content form found on page');
                return $forms.first();
            }
            if ($forms.length > 1) {
                console.log('[VMS Elements Form Guard] Multiple content forms found, returning first one');
                return $forms.first();
            }
        }
        
        return $();
    }

    function vefgLooksCombinedSelector(fid) {
        const s = String(fid || '').trim();
        return (
            s !== '' &&
            (/^[#.[]/.test(s) || s.indexOf('#') !== -1 || s.indexOf('.') !== -1 || s.indexOf('[') !== -1)
        );
    }

    function vefgEscapeSel(seg) {
        if (typeof $.escapeSelector === 'function') {
            return $.escapeSelector(seg);
        }
        return seg.replace(/([!"#$%&'()*+,./:;<=>?@[\]^`{|}~])/g, '\\$1');
    }

    function resolveForm$(formId, formClass) {
        const fid = String(formId || '').trim();
        const fcls = String(formClass || '').trim();
        
        console.log('[VMS Elements Form Guard] resolveForm$ called with formId:', fid, 'formClass:', fcls);
        
        // Check if it's a combined/complex CSS selector (contains #, ., [, or space for descendant)
        if (vefgLooksCombinedSelector(fid) || fid.indexOf(' ') !== -1) {
            console.log('[VMS Elements Form Guard] Trying combined selector:', fid);
            
            try {
                const $hit = $(fid);
                console.log('[VMS Elements Form Guard] Selector matched elements:', $hit.length);
                
                if ($hit.length) {
                    console.log('[VMS Elements Form Guard] Matched element(s) tagName(s):', $hit.toArray().map(el => el.tagName).join(', '));
                }
                
                if (!$hit.length) {
                    console.log('[VMS Elements Form Guard] No elements found for selector:', fid);
                    return $();
                }
                
                // If the selector directly returns form elements
                const $formHit = $hit.filter('form').first();
                if ($formHit.length) {
                    console.log('[VMS Elements Form Guard] Found form via filter, form id:', $formHit.attr('id'), 'class:', $formHit.attr('class'));
                    return $formHit;
                }
                
                // If the selector returns a wrapper, find the form inside it
                const $innerForm = $hit.find('form').first();
                if ($innerForm.length) {
                    console.log('[VMS Elements Form Guard] Found form inside matched element, form id:', $innerForm.attr('id'), 'class:', $innerForm.attr('class'));
                    return $innerForm;
                }
                
                // If the selector returns an element inside a form, find the parent form
                const $inForm = $hit.closest('form').first();
                if ($inForm.length) {
                    console.log('[VMS Elements Form Guard] Found form as ancestor, form id:', $inForm.attr('id'), 'class:', $inForm.attr('class'));
                    return $inForm;
                }
                
                console.log('[VMS Elements Form Guard] No form found for selector:', fid);
                return $();
            } catch (e) {
                console.error('[VMS Elements Form Guard] Error with selector:', fid, e);
                return $();
            }
        }
        
        // Legacy handling for simple ID/class format
        const id = fid.replace(/^#/, '');
        const rawClass = fcls.replace(/^\./g, '');
        const classes = rawClass.split(/\s+/).filter(Boolean).map(function (c) {
            return c.replace(/^\./, '');
        });
        if (id && classes.length) {
            return $('#' + vefgEscapeSel(id) + '.' + classes.join('.'));
        }
        if (id) {
            return $('#' + vefgEscapeSel(id));
        }
        if (classes.length) {
            return $('.' + classes.join('.'));
        }
        return $();
    }

    /**
     * Check if a button is visible (not hidden via aria-hidden, display:none, visibility:hidden, etc.)
     */
    function isButtonVisible($btn) {
        if (!$btn.length) return false;
        
        // Skip aria-hidden buttons
        if ($btn.attr('aria-hidden') === 'true') return false;
        
        // Skip buttons with tabindex=-1 and hidden styles (like quform-default-submit)
        if ($btn.attr('tabindex') === '-1') {
            var styles = $btn.attr('style') || '';
            if (styles.indexOf('display: none') !== -1 || 
                styles.indexOf('visibility: hidden') !== -1 ||
                styles.indexOf('position: absolute') !== -1 && styles.indexOf('left: -9999') !== -1) {
                return false;
            }
        }
        
        // Check computed visibility
        try {
            var computed = window.getComputedStyle($btn[0]);
            if (computed.display === 'none' || computed.visibility === 'hidden' || computed.opacity === '0') {
                return false;
            }
        } catch(e) {}
        
        return true;
    }
    
    /**
     * Find visible submit buttons, filtering out hidden/default ones
     */
    function findVisibleSubmit($form, selector) {
        var $buttons = $form.find(selector);
        for (var i = 0; i < $buttons.length; i++) {
            var $btn = $buttons.eq(i);
            if (isButtonVisible($btn)) {
                return $btn;
            }
        }
        return $();
    }

    function resolveSubmit$($form, submitSelector) {
        const raw = String(submitSelector || '').trim();
        if (raw) {
            let $btn = $form.find(raw);
            if (!$btn.length) {
                $btn = $(raw);
            }
            if ($btn.length) {
                // If custom selector, find first visible one
                for (var i = 0; i < $btn.length; i++) {
                    var $b = $btn.eq(i);
                    if (isButtonVisible($b)) {
                        console.log('[VMS Elements Form Guard] Found visible submit button with custom selector:', raw);
                        return $b;
                    }
                }
                // Fallback to first if none visible
                console.log('[VMS Elements Form Guard] Found submit button with custom selector (no visible check):', raw);
                return $btn.first();
            }
        }
        
        // 1. input[type="submit"] - standard submit input (usually visible)
        let $btn = findVisibleSubmit($form, 'input[type="submit"]');
        if ($btn.length) {
            console.log('[VMS Elements Form Guard] Found visible input[type="submit"]');
            return $btn;
        }
        
        // 2. button[type="submit"] - explicit submit button (filter hidden ones like quform-default-submit)
        $btn = findVisibleSubmit($form, 'button[type="submit"]:not(.quform-default-submit)');
        if ($btn.length) {
            console.log('[VMS Elements Form Guard] Found visible button[type="submit"]');
            return $btn;
        }
        
        // 3. Common submit button classes (visible ones)
        $btn = findVisibleSubmit($form, '.quform-submit:not(.quform-default-submit), .wpcf7-submit, .sib-default-btn, .tnp-submit, .mc4wp-submit, .wpforms-submit');
        if ($btn.length) {
            console.log('[VMS Elements Form Guard] Found visible button by common submit class');
            return $btn;
        }
        
        // 4. Any element with type="submit" (visible)
        $btn = findVisibleSubmit($form, '[type="submit"]');
        if ($btn.length) {
            console.log('[VMS Elements Form Guard] Found visible [type="submit"]');
            return $btn;
        }
        
        // 5. Button without type attribute (defaults to submit in HTML) - visible
        $btn = findVisibleSubmit($form, 'button:not([type]):not([aria-hidden="true"])');
        if ($btn.length) {
            console.log('[VMS Elements Form Guard] Found visible button without type (defaults to submit)');
            return $btn;
        }
        
        // 6. Button that is not explicitly button or reset type - visible
        $btn = findVisibleSubmit($form, 'button:not([type="button"]):not([type="reset"]):not([aria-hidden="true"])');
        if ($btn.length) {
            console.log('[VMS Elements Form Guard] Found visible button (not button/reset type)');
            return $btn;
        }
        
        // 7. Generic submit classes - visible
        $btn = findVisibleSubmit($form, '.submit, .btn-submit, .form-submit');
        if ($btn.length) {
            console.log('[VMS Elements Form Guard] Found visible button by generic submit class');
            return $btn;
        }
        
        // 8. Last visible button in form as fallback
        var $allButtons = $form.find('button');
        for (var i = $allButtons.length - 1; i >= 0; i--) {
            var $b = $allButtons.eq(i);
            if (isButtonVisible($b)) {
                console.log('[VMS Elements Form Guard] Found last visible button as fallback');
                return $b;
            }
        }
        
        // 9. Last input[type="button"] that might act as submit
        $btn = $form.find('input[type="button"]').last();
        if ($btn.length && isButtonVisible($btn)) {
            console.log('[VMS Elements Form Guard] Found last visible input[type="button"] as fallback');
            return $btn;
        }
        
        console.log('[VMS Elements Form Guard] No submit button found in form');
        return $();
    }

    function generateFieldClassId($form, fieldId, fieldClass) {
        const id = String(fieldId || '').trim().replace(/^#/, '');
        const rawClass = String(fieldClass || '').trim().replace(/^\./g, '');
        const classes = rawClass.split(/\s+/).filter(Boolean).map(function (c) {
            return c.replace(/^\./, '');
        });

        if (vefgLooksCombinedSelector(id)) {
            const $h = $form.find(id);
            return $h.length ? $h : $(id);
        }
        if (id && classes.length) {
            return $form.find('#' + vefgEscapeSel(id) + '.' + classes.join('.'));
        }
        if (id) {
            return $form.find('#' + vefgEscapeSel(id));
        }
        if (classes.length) {
            return $form.find('.' + classes.join('.'));
        }
        return $();
    }

    function fieldServerGate(field) {
        const f = field.field_type || field.field || '';
        const iv = parseInt(field.isValidate, 10) || 0;
        const regex = String(field.regex || '').trim();
        const ai = parseInt(field.textarea_ai_spam, 10) || 0;
        if (regex !== '') {
            return true;
        }
        if ('textarea' === f) {
            const allow =
                field.textarea_allow_links === undefined ||
                field.textarea_allow_links === null ||
                String(field.textarea_allow_links) === ''
                    ? true
                    : parseInt(field.textarea_allow_links, 10) !== 0;
            if (!allow || ai || iv) {
                return true;
            }
            return false;
        }
        if ('text' === f) {
            const allowTu =
                field.text_allow_urls === undefined ||
                field.text_allow_urls === null ||
                String(field.text_allow_urls) === ''
                    ? true
                    : parseInt(field.text_allow_urls, 10) !== 0;
            if (!allowTu || iv) {
                return true;
            }
            return false;
        }
        if ('username' === f) {
            const uchkn = parseInt(field.check_username_exists, 10) || 0;
            if (uchkn || iv) {
                return true;
            }
            return false;
        }
        if (('email' === f || 'url' === f) && iv) {
            return true;
        }
        return !!iv;
    }

    function readFieldValue($el, fieldType) {
        if (!$el.length) {
            return '';
        }
        const tag = ($el.prop('tagName') || '').toLowerCase();
        if (tag === 'textarea') {
            return String($el.val() || '');
        }
        return String($el.val() || '');
    }

    function applyRequired($el, on) {
        if (!$el.length) {
            return;
        }
        if (on) {
            $el.attr('required', 'required');
        } else {
            $el.removeAttr('required');
        }
    }

    function validateUrlServer(domain, type, apiSettings) {
        return new Promise(function (resolve, reject) {
            jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vefg_validate_domain_name',
                    domain: domain,
                    type: type || 'unknown',
                    settings: apiSettings,
                    nonce: nonce,
                },
                success: function (response) {
                    if (response && response.success && response.data) {
                        resolve(response.data);
                        return;
                    }
                    const msg =
                        response && response.data && response.data.message
                            ? response.data.message
                            : t('validationFailed', 'Validation failed');
                    reject(new Error(msg));
                },
                error: function (xhr) {
                    reject(new Error(xhr.statusText || 'Request failed'));
                },
            });
        });
    }

    function validateGuardFieldServer(mappingId, fieldIndex, value) {
        return new Promise(function (resolve, reject) {
            jQuery.ajax({
                url: ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vefg_validate_form_guard_field',
                    nonce: nonce,
                    mappingId: mappingId,
                    fieldIndex: fieldIndex,
                    value: value,
                },
                success: function (response) {
                    if (response && response.success && response.data) {
                        resolve(response.data);
                        return;
                    }
                    const msg =
                        response && response.data && response.data.message
                            ? response.data.message
                            : t('validationFailed', 'Validation failed');
                    reject(new Error(msg));
                },
                error: function (xhr) {
                    let msg = xhr.statusText || t('requestFailed', 'Request failed');
                    try {
                        const j = xhr.responseJSON;
                        if (j && j.data && j.data.message) {
                            msg = j.data.message;
                        }
                    } catch (e) {
                        /* ignore */
                    }
                    reject(new Error(msg));
                },
            });
        });
    }

    function disableSubmitButton(submitButton) {
        submitButton.prop('disabled', true);
    }

    function enableSubmitButton(submitButton) {
        submitButton.prop('disabled', false);
    }

    function apiPayloadFromField(field, fallbackWr, fallbackVt) {
        const ft = field.field_type || field.field || '';
        if (ft !== 'email' && ft !== 'url') {
            return [{ is_webrisk: 0, is_virustotal: 0 }];
        }
        const wr =
            field.is_webrisk !== undefined && field.is_webrisk !== null && String(field.is_webrisk) !== ''
                ? parseInt(field.is_webrisk, 10) || 0
                : fallbackWr;
        const vt =
            field.is_virustotal !== undefined && field.is_virustotal !== null && String(field.is_virustotal) !== ''
                ? parseInt(field.is_virustotal, 10) || 0
                : fallbackVt;
        return [{ is_webrisk: wr ? 1 : 0, is_virustotal: vt ? 1 : 0 }];
    }

    function isValidEmail(email) {
        const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return regex.test(email);
    }

    function getDomainFromEmail(email) {
        const parts = String(email).split('@');
        return parts.length > 1 ? parts[1] : '';
    }

    function isValidUrl(value) {
        const pattern = /^(https?:\/\/)?([a-zA-Z0-9-]+\.)+[a-zA-Z]{2,}(\/\S*)?$/;
        return pattern.test(value);
    }

    function addChangeEventUrl(element, submitButton, apiSettings) {
        element.on('change', function () {
            let inputVal = String($(this).val() || '').trim();
            if (!inputVal.length) {
                disableSubmitButton(submitButton);
                return;
            }
            if (!isValidUrl(inputVal)) {
                vefgToast.fire({
                    icon: 'error',
                    title: t('urlNotValid', 'URL not valid'),
                });
                disableSubmitButton(submitButton);
                return;
            }
            if (!/^https?:\/\//i.test(inputVal)) {
                inputVal = 'https://' + inputVal;
            }
            validateUrlServer(inputVal, 'url', apiSettings)
                .then(function (result) {
                    if (result.status) {
                        vefgToast.fire({
                            icon: 'success',
                            title: result.message || t('urlValid', 'URL is valid'),
                        });
                        enableSubmitButton(submitButton);
                    } else {
                        vefgToast.fire({
                            icon: 'error',
                            title: result.message || t('urlNotValid', 'URL not valid'),
                        });
                        disableSubmitButton(submitButton);
                    }
                })
                .catch(function (err) {
                    vefgToast.fire({
                        icon: 'error',
                        title: err.message || t('validationFailed', 'Validation failed'),
                    });
                    disableSubmitButton(submitButton);
                });
        });
    }

    function addChangeEvent(element, fieldType, submitButton, apiSettings) {
        element.on('change', function () {
            const inputVal = String($(this).val() || '').trim();
            if (!inputVal.length) {
                disableSubmitButton(submitButton);
                return;
            }
            if (fieldType === 'email') {
                if (!isValidEmail(inputVal)) {
                    vefgToast.fire({
                        icon: 'error',
                        title: t('emailInvalid', 'Email address is invalid'),
                    });
                    disableSubmitButton(submitButton);
                    return;
                }
                const domainName = getDomainFromEmail(inputVal);
                if (!domainName) {
                    vefgToast.fire({
                        icon: 'error',
                        title: t('emailInvalid', 'Email address is invalid'),
                    });
                    disableSubmitButton(submitButton);
                    return;
                }
                validateUrlServer(domainName, 'email', apiSettings)
                    .then(function (result) {
                        if (result.status) {
                            vefgToast.fire({
                                icon: 'success',
                                title: result.message,
                            });
                            enableSubmitButton(submitButton);
                        } else {
                            vefgToast.fire({
                                icon: 'error',
                                title: result.message || t('emailInvalid', 'Email address is invalid'),
                            });
                            disableSubmitButton(submitButton);
                        }
                    })
                    .catch(function (err) {
                        vefgToast.fire({
                            icon: 'error',
                            title: err.message || t('validationFailed', 'Validation failed'),
                        });
                        disableSubmitButton(submitButton);
                    });
            }
        });
    }

    function addInputEvent(element, fieldType, submitButton, apiSettings) {
        element.on('input', function () {
            const inputVal = String($(this).val() || '').trim();
            if (fieldType === 'email' && inputVal && isValidEmail(inputVal)) {
                const domainName = getDomainFromEmail(inputVal);
                if (domainName) {
                    validateUrlServer(domainName, 'email', apiSettings).catch(function () {
                        /* optional debounce later */
                    });
                }
                return;
            }
            if (fieldType === 'url' && inputVal && isValidUrl(inputVal)) {
                let u = inputVal;
                if (!/^https?:\/\//i.test(u)) {
                    u = 'https://' + u;
                }
                validateUrlServer(u, 'url', apiSettings).catch(function () {
                    /* optional debounce */
                });
            }
        });
    }

    function bindUsernameLiveCheck(field$, submitButton, mappingId, fieldIndex) {
        let timer = null;
        function schedule(val) {
            if (timer) {
                clearTimeout(timer);
            }
            timer = setTimeout(function () {
                timer = null;
                const trimmed = String(val || '').trim();
                if (!trimmed.length) {
                    enableSubmitButton(submitButton);
                    return;
                }
                validateGuardFieldServer(mappingId, fieldIndex, trimmed)
                    .then(function (data) {
                        if (data && data.status) {
                            enableSubmitButton(submitButton);
                        } else {
                            disableSubmitButton(submitButton);
                            if (data && data.message) {
                                vefgToast.fire({
                                    icon: 'error',
                                    title: data.message,
                                });
                            }
                        }
                    })
                    .catch(function (err) {
                        disableSubmitButton(submitButton);
                        vefgToast.fire({
                            icon: 'error',
                            title: err.message || t('validationFailed', 'Validation failed'),
                        });
                    });
            }, 450);
        }
        field$.off('.vefgUsernameLive').on('input.vefgUsernameLive change.vefgUsernameLive', function () {
            schedule($(this).val());
        });
    }

    /**
     * Form Guard registry lives on window.
     */
    function vefgGuardRegistry() {
        return window.vefgFormGuardState && window.vefgFormGuardState.registry;
    }

    function vefgGetFormGuardEntry(formEl) {
        const reg = vefgGuardRegistry();
        if (!formEl || !reg) {
            return null;
        }
        let entry = reg.get(formEl);
        if (!entry) {
            entry = {
                configs: [],
                validating: false,
                validationBtnCreated: false,
                $originalSubmit: null,
                $validationBtn: null,
                originalBtnText: '',
            };
            reg.set(formEl, entry);
        }
        return entry;
    }

    function vefgRegisterFormGuardConfig(formEl, config) {
        const entry = vefgGetFormGuardEntry(formEl);
        if (!entry) {
            return;
        }
        entry.configs.push(config);
    }

    /**
     * reCAPTCHA state management.
     */
    var recaptchaLoaded = false;
    var recaptchaCallbacks = [];
    
    function vefgLoadRecaptchaScript(callback) {
        if (recaptchaLoaded && typeof grecaptcha !== 'undefined') {
            if (callback) callback();
            return;
        }
        
        if (callback) {
            recaptchaCallbacks.push(callback);
        }
        
        if (document.getElementById('vefg-recaptcha-script')) {
            return;
        }
        
        var recaptchaConfig = VEFGChecker.recaptcha || {};
        if (!recaptchaConfig.siteKey) return;
        
        var script = document.createElement('script');
        script.id = 'vefg-recaptcha-script';
        script.src = 'https://www.google.com/recaptcha/api.js?onload=vefgRecaptchaReady&render=' + 
            (recaptchaConfig.version === 'v3' ? recaptchaConfig.siteKey : 'explicit');
        script.async = true;
        script.defer = true;
        document.head.appendChild(script);
    }
    
    window.vefgRecaptchaReady = function() {
        recaptchaLoaded = true;
        console.log('[VMS Elements Form Guard] reCAPTCHA loaded');
        recaptchaCallbacks.forEach(function(cb) { cb(); });
        recaptchaCallbacks = [];
    };
    
    function vefgRenderRecaptcha($form, entry, callback) {
        var recaptchaConfig = VEFGChecker.recaptcha || {};
        if (!recaptchaConfig.siteKey) {
            if (callback) callback(null);
            return;
        }
        
        // Check if already rendered
        if (entry.recaptchaRendered) {
            if (callback) callback(entry.recaptchaWidgetId);
            return;
        }
        
        var $submitBtn = entry.$validationBtn || entry.$originalSubmit;
        if (!$submitBtn || !$submitBtn.length) {
            if (callback) callback(null);
            return;
        }
        
        // Create reCAPTCHA container
        var containerId = 'vefg-recaptcha-' + Math.random().toString(36).substr(2, 9);
        var $container = $('<div id="' + containerId + '" class="vefg-recaptcha-container" style="margin: 10px 0;"></div>');
        $container.insertBefore($submitBtn);
        
        if (recaptchaConfig.version === 'v3') {
            // v3 is invisible, no widget to render
            entry.recaptchaRendered = true;
            entry.recaptchaVersion = 'v3';
            if (callback) callback(null);
        } else {
            // v2 checkbox
            vefgLoadRecaptchaScript(function() {
                try {
                    var widgetId = grecaptcha.render(containerId, {
                        sitekey: recaptchaConfig.siteKey,
                        callback: function(token) {
                            entry.recaptchaToken = token;
                            entry.recaptchaVerified = true;
                            if (entry.$validationBtn) {
                                entry.$validationBtn.prop('disabled', false);
                            }
                            console.log('[VMS Elements Form Guard] reCAPTCHA v2 verified');
                        },
                        'expired-callback': function() {
                            entry.recaptchaToken = null;
                            entry.recaptchaVerified = false;
                            if (entry.$validationBtn) {
                                entry.$validationBtn.prop('disabled', true);
                            }
                        }
                    });
                    entry.recaptchaWidgetId = widgetId;
                    entry.recaptchaRendered = true;
                    entry.recaptchaVersion = 'v2';
                    // Disable submit until reCAPTCHA verified
                    if (entry.$validationBtn) {
                        entry.$validationBtn.prop('disabled', true);
                    }
                    if (callback) callback(widgetId);
                } catch (e) {
                    console.error('[VMS Elements Form Guard] reCAPTCHA render error:', e);
                    if (callback) callback(null);
                }
            });
        }
    }
    
    function vefgGetRecaptchaToken(entry, callback) {
        var recaptchaConfig = VEFGChecker.recaptcha || {};
        
        if (!recaptchaConfig.siteKey || !entry.recaptchaRendered) {
            callback(null);
            return;
        }
        
        if (entry.recaptchaVersion === 'v3') {
            // v3: get token via execute
            grecaptcha.ready(function() {
                grecaptcha.execute(recaptchaConfig.siteKey, { action: 'submit' }).then(function(token) {
                    callback(token);
                });
            });
        } else {
            // v2: token already available from callback
            callback(entry.recaptchaToken || null);
        }
    }

    /**
     * Setup validation by creating a custom validation button.
     * 1. Hide the original submit button
     * 2. Create a validation button that looks identical
     * 3. On click: run validation first, then click original submit if passed
     */
    function vefgSetupFormValidation($form, $originalSubmit, entry, enableRecaptcha) {
        const formEl = $form.get(0);
        
        if (entry.validationBtnCreated) {
            console.log('[VMS Elements Form Guard] Validation button already created');
            return;
        }
        
        if (!$originalSubmit.length) {
            console.log('[VMS Elements Form Guard] No submit button found');
            return;
        }
        
        entry.validationBtnCreated = true;
        entry.$originalSubmit = $originalSubmit;
        
        const originalEl = $originalSubmit.get(0);
        const btnText = getSubmitButtonText($originalSubmit, t('submit', 'Submit'));
        entry.originalBtnText = btnText;
        
        // Create validation button
        const isInput = $originalSubmit.is('input');
        let $validationBtn;
        
        if (isInput) {
            $validationBtn = $('<input type="button">').val(btnText);
        } else {
            $validationBtn = $('<button type="button">').html($originalSubmit.html() || btnText);
        }
        
        // Copy classes (remove wpcf7-submit to avoid CF7 binding)
        const originalClasses = ($originalSubmit.attr('class') || '')
            .replace(/\bwpcf7-submit\b/g, '')
            .replace(/\bsubmit\b/g, '')
            .trim();
        $validationBtn.attr('class', originalClasses + ' vefg-validation-btn');
        
        // Copy inline styles
        const inlineStyle = $originalSubmit.attr('style') || '';
        if (inlineStyle) {
            $validationBtn.attr('style', inlineStyle);
        }
        
        // Copy computed styles for better appearance matching
        try {
            const computedStyle = window.getComputedStyle(originalEl);
            const cssProps = [
                'background', 'backgroundColor', 'backgroundImage', 'color', 
                'fontSize', 'fontWeight', 'fontFamily', 'padding', 'margin',
                'border', 'borderRadius', 'width', 'height', 'minWidth', 'minHeight',
                'lineHeight', 'textTransform', 'letterSpacing', 'boxShadow', 
                'cursor', 'display', 'textAlign'
            ];
            cssProps.forEach(function(prop) {
                const val = computedStyle.getPropertyValue(prop.replace(/([A-Z])/g, '-$1').toLowerCase());
                if (val && val !== '' && val !== 'none') {
                    $validationBtn.css(prop.replace(/([A-Z])/g, '-$1').toLowerCase(), val);
                }
            });
        } catch(e) {
            console.log('[VMS Elements Form Guard] Could not copy computed styles');
        }
        
        // Make sure validation button is visible
        $validationBtn.css({
            'display': 'inline-block',
            'visibility': 'visible',
            'opacity': '1',
            'position': 'relative',
            'left': 'auto'
        });
        
        // HIDE the original submit button completely
        $originalSubmit.css({
            'position': 'absolute',
            'left': '-9999px',
            'top': '-9999px',
            'visibility': 'hidden',
            'opacity': '0',
            'width': '1px',
            'height': '1px',
            'pointer-events': 'none'
        }).attr('tabindex', '-1');
        
        // Insert validation button after original
        $validationBtn.insertAfter($originalSubmit);
        
        entry.$validationBtn = $validationBtn;
        entry.enableRecaptcha = enableRecaptcha;
        
        console.log('[VMS Elements Form Guard] Validation button created:', btnText, 'reCAPTCHA:', enableRecaptcha);
        
        // Render reCAPTCHA if enabled
        if (enableRecaptcha && VEFGChecker.recaptcha && VEFGChecker.recaptcha.siteKey) {
            vefgRenderRecaptcha($form, entry, function(widgetId) {
                console.log('[VMS Elements Form Guard] reCAPTCHA rendered, widget:', widgetId);
            });
        }
        
        // VALIDATION BUTTON CLICK - This is the ONLY entry point for form submission
        $validationBtn.on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            console.log('[VMS Elements Form Guard] Validation button clicked');
            
            if (entry.validating) {
                console.log('[VMS Elements Form Guard] Already validating, ignoring');
                return false;
            }
            
            // Check reCAPTCHA v2 first (must be verified before proceeding)
            if (entry.enableRecaptcha && entry.recaptchaVersion === 'v2' && !entry.recaptchaVerified) {
                vefgToast.fire({
                    icon: 'warning',
                    title: t('recaptchaRequired', 'Please complete the reCAPTCHA verification.'),
                });
                return false;
            }
            
            // Run validation
            vefgRunValidation(formEl, entry);
            
            return false;
        });
        
        // Prevent Enter key from submitting form directly - must go through validation
        $form.on('keypress.vefgValidation', function(e) {
            if (e.which === 13 && !$(e.target).is('textarea')) {
                e.preventDefault();
                console.log('[VMS Elements Form Guard] Enter key pressed, triggering validation');
                $validationBtn.click();
                return false;
            }
        });
    }

    /**
     * Run validation for all configured fields, then submit if valid.
     */
    function vefgRunValidation(formEl, entry) {
        entry.validating = true;
        const $validationBtn = entry.$validationBtn;
        const originalBtnText = entry.originalBtnText || t('submit', 'Submit');
        const validatingText = t('validating', 'Validating...');

        console.log('[VMS Elements Form Guard] ========== STARTING VALIDATION ==========');
        console.log('[VMS Elements Form Guard] Configs count:', entry.configs.length);

        // Clear all previous field errors
        vefgClearAllFieldErrors($(formEl));

        // Show validating state on validation button
        if ($validationBtn && $validationBtn.length) {
            if ($validationBtn.is('input')) {
                $validationBtn.val(validatingText);
            } else {
                $validationBtn.text(validatingText);
            }
            $validationBtn.prop('disabled', true);
        }

        const before = new CustomEvent('vms_elements_form_guard:guard_before_validate', {
            cancelable: true,
            bubbles: true,
            detail: { form: formEl },
        });
        if (!formEl.dispatchEvent(before)) {
            vefgResetValidationBtn(entry);
            return;
        }

        const jobs = [];
        const autoJobs = [];
        
        entry.configs.forEach(function (cfg) {
            const mappingId = cfg.mappingId;
            
            console.log('[VMS Elements Form Guard] Config - isAuto:', cfg.isAuto, 'autoFields:', cfg.autoFields ? cfg.autoFields.length : 0);
            
            if (cfg.isAuto && cfg.autoFields) {
                cfg.autoFields.forEach(function(fieldConfig) {
                    const $el = fieldConfig.$el;
                    const val = String($el.val() || '').trim();
                    const fieldType = fieldConfig.type;
                    const rules = fieldConfig.rules || {};
                    const isRequired = fieldConfig.required;
                    
                    console.log('[VMS Elements Form Guard] Field:', fieldType, 'name:', fieldConfig.name, 'required:', isRequired, 'value:', val ? '"' + val.substring(0, 30) + '..."' : '(empty)');
                    console.log('[VMS Elements Form Guard] Rules:', JSON.stringify(rules));
                    
                    // Client-side validation (includes required check)
                    const clientResult = vefgValidateAutoField(fieldConfig);
                    console.log('[VMS Elements Form Guard] Client validation result:', clientResult);
                    
                    if (!clientResult.valid) {
                        autoJobs.push({
                            mappingId: mappingId,
                            fieldConfig: fieldConfig,
                            val: val,
                            clientError: clientResult.message
                        });
                        return;
                    }
                    
                    // Skip server validation if field is empty (and not required - already checked above)
                    if (!val) {
                        console.log('[VMS Elements Form Guard] Skipping server validation for empty non-required field');
                        return;
                    }
                    
                    // Check if server validation is needed
                    const needsServer = (fieldType === 'email' && (rules.mx || rules.disposable || rules.webrisk || rules.virustotal)) ||
                                       (fieldType === 'url' && (rules.webrisk || rules.virustotal)) ||
                                       (fieldType === 'textarea' && rules.ai_spam) ||
                                       (fieldType === 'username' && rules.check_exists);
                    
                    console.log('[VMS Elements Form Guard] Server validation check - fieldType:', fieldType, 'rules:', rules, 'needsServer:', needsServer);
                    if (fieldType === 'textarea') {
                        console.log('[VMS Elements Form Guard] Textarea ai_spam rule:', rules.ai_spam);
                    }
                    
                    if (needsServer) {
                        autoJobs.push({
                            mappingId: mappingId,
                            fieldConfig: fieldConfig,
                            val: val,
                            needsServer: true
                        });
                    }
                });
            } else {
                const formSettingData = cfg.formSettingData || [];
                formSettingData.forEach(function (fs, idx) {
                    const ft = fs.field_type || fs.field || '';
                    const $el = generateFieldClassId($(formEl), fs.id, fs.class);
                    const val = readFieldValue($el, ft);
                    const required = parseInt(fs.isRequired, 10) || 0;
                    const nonEmpty = String(val).trim() !== '';
                    if (required || (nonEmpty && fieldServerGate(fs))) {
                        jobs.push({ mappingId: mappingId, idx: idx, val: val, fieldName: fs.id || fs.class || '' });
                    }
                });
            }
        });

        // Check for client-side errors first - show inline errors
        const clientErrors = autoJobs.filter(function(j) { return j.clientError; });
        if (clientErrors.length > 0) {
            console.log('[VMS Elements Form Guard] CLIENT VALIDATION FAILED - errors:', clientErrors.length);
            
            // Show inline error for each field
            clientErrors.forEach(function(err) {
                if (err.fieldConfig && err.fieldConfig.$el) {
                    vefgShowFieldError(err.fieldConfig.$el, err.clientError);
                }
            });
            
            // Show summary toast
            vefgToast.fire({
                icon: 'error',
                title: t('validationErrors', 'Please review the highlighted fields and try again.'),
            });
            vefgResetValidationBtn(entry);
            return;
        }
        
        const serverAutoJobs = autoJobs.filter(function(j) { return j.needsServer; });

        console.log('[VMS Elements Form Guard] Jobs - manual:', jobs.length, ', auto-server:', serverAutoJobs.length);

        // If no server validation needed, proceed to submit
        if (jobs.length === 0 && serverAutoJobs.length === 0) {
            console.log('[VMS Elements Form Guard] No server validation needed, proceeding to submit');
            vefgProceedWithSubmit(formEl, entry);
            return;
        }

        // Combine all server validation jobs into a single AJAX call
        const allServerJobs = [];
        
        serverAutoJobs.forEach(function(job) {
            allServerJobs.push({
                type: 'auto',
                mappingId: job.mappingId,
                fieldType: job.fieldConfig.type,
                fieldName: job.fieldConfig.name,
                value: job.val,
                rules: job.fieldConfig.rules,
                $field: job.fieldConfig.$el
            });
        });
        
        jobs.forEach(function(job) {
            allServerJobs.push({
                type: 'manual',
                mappingId: job.mappingId,
                fieldIndex: job.idx,
                fieldName: job.fieldName,
                value: job.val
            });
        });
        
        console.log('[VMS Elements Form Guard] Sending', allServerJobs.length, 'fields for server validation');
        
        // Get reCAPTCHA token if enabled
        var doValidation = function(recaptchaToken) {
            vefgValidateAllFieldsServer(allServerJobs, recaptchaToken)
            .then(function(result) {
                if (result.status) {
                    console.log('[VMS Elements Form Guard] ========== ALL VALIDATIONS PASSED ==========');
                    formEl.dispatchEvent(
                        new CustomEvent('vms_elements_form_guard:guard_validated', {
                            bubbles: true,
                            detail: { form: formEl },
                        })
                    );
                    vefgProceedWithSubmit(formEl, entry);
                } else {
                    console.log('[VMS Elements Form Guard] ========== VALIDATION FAILED ==========');
                    
                    // Check if user is blocked
                    if (result.blocked) {
                        console.log('[VMS Elements Form Guard] User is BLOCKED');
                        var blockMessage = result.strike_message || t('userBlocked', 'You have been blocked due to repeated violations. Please contact support.');
                        
                        // Disable the validation button
                        if (entry.$validationBtn) {
                            entry.$validationBtn.prop('disabled', true).css('opacity', '0.5');
                        }
                        
                        // Show blocking message with Swal
                        Swal.fire({
                            icon: 'error',
                            title: t('blocked', 'Blocked'),
                            text: blockMessage,
                            confirmButtonColor: '#d33',
                        });
                        
                        return;
                    }
                    
                    // Show errors for each failed field
                    if (result.errors && result.errors.length > 0) {
                        result.errors.forEach(function(err) {
                            // Find the field element
                            var $field = null;
                            if (err.fieldIndex !== undefined) {
                                // Manual field - find by index
                                var job = allServerJobs.find(function(j) {
                                    return j.type === 'manual' && j.fieldIndex === err.fieldIndex;
                                });
                                if (job && job.$field) {
                                    $field = job.$field;
                                }
                            } else if (err.fieldName) {
                                // Auto field - find by name
                                var job = allServerJobs.find(function(j) {
                                    return j.fieldName === err.fieldName;
                                });
                                if (job && job.$field) {
                                    $field = job.$field;
                                }
                            }
                            
                            if ($field) {
                                vefgShowFieldError($field, err.message);
                            }
                        });
                        
                        // Show first error as toast
                        vefgToast.fire({
                            icon: 'error',
                            title: result.errors[0].message || t('validationErrors', 'Please review the highlighted fields and try again.'),
                        });
                    } else {
                        vefgToast.fire({
                            icon: 'error',
                            title: result.message || t('validationErrors', 'Please review the highlighted fields and try again.'),
                        });
                    }
                    
                    vefgResetValidationBtn(entry);
                }
            })
            .catch(function(err) {
                console.log('[VMS Elements Form Guard] Server validation error:', err);
                vefgToast.fire({
                    icon: 'error',
                    title: t('validationFailed', 'Validation failed. Please try again.'),
                });
                vefgResetValidationBtn(entry);
            });
        };
        
        // Get reCAPTCHA token if enabled, then run validation
        if (entry.enableRecaptcha && entry.recaptchaRendered) {
            vefgGetRecaptchaToken(entry, function(token) {
                console.log('[VMS Elements Form Guard] reCAPTCHA token obtained:', token ? 'yes' : 'no');
                doValidation(token);
            });
        } else {
            doValidation(null);
        }
    }

    /**
     * Proceed with form submission after validation passes.
     * Click the ORIGINAL submit button to trigger CF7/other plugin handlers.
     */
    function vefgProceedWithSubmit(formEl, entry) {
        const $validationBtn = entry.$validationBtn;
        const $originalSubmit = entry.$originalSubmit;
        const originalBtnText = entry.originalBtnText;
        const submittingText = t('submitting', 'Submitting...');
        
        // Update validation button text to show submitting
        if ($validationBtn && $validationBtn.length) {
            if ($validationBtn.is('input')) {
                $validationBtn.val(submittingText);
            } else {
                $validationBtn.text(submittingText);
            }
            $validationBtn.prop('disabled', true);
        }

        console.log('[VMS Elements Form Guard] Validation PASSED! Now clicking original submit button...');

        entry.validating = false;

        // Small delay to ensure UI updates
        setTimeout(function() {
            if ($originalSubmit && $originalSubmit.length) {
                // Temporarily make original button clickable
                $originalSubmit.css({
                    'pointer-events': 'auto'
                });
                
                // Click the ORIGINAL submit button - this triggers CF7/other plugin handlers
                console.log('[VMS Elements Form Guard] Clicking original submit button');
                $originalSubmit[0].click();
                
                // Hide it again after click
                setTimeout(function() {
                    $originalSubmit.css({
                        'pointer-events': 'none'
                    });
                }, 100);
            } else {
                // Fallback to native form submit
                console.log('[VMS Elements Form Guard] No original submit, using native form submit');
                HTMLFormElement.prototype.submit.call(formEl);
            }

            // Reset validation button after delay
            setTimeout(function() {
                vefgResetValidationBtn(entry);
            }, 3000);
        }, 50);
    }

    /**
     * Reset validation button to original state.
     */
    function vefgResetValidationBtn(entry) {
        const $validationBtn = entry.$validationBtn;
        const originalBtnText = entry.originalBtnText;
        
        if ($validationBtn && $validationBtn.length) {
            if ($validationBtn.is('input')) {
                $validationBtn.val(originalBtnText);
            } else {
                $validationBtn.text(originalBtnText);
            }
            $validationBtn.prop('disabled', false);
        }
        entry.validating = false;
    }

    console.log('[VMS Elements Form Guard] Total settings to process:', settings.length);
    console.log('[VMS Elements Form Guard] All settings:', JSON.stringify(settings.map(s => ({id: s.id, form_id: s.form_id, page_id: s.page_id})), null, 2));
    
    settings.forEach(function (setting) {
        const mappingId = parseInt(setting.id, 10) || 0;
        const fallbackWr = parseInt(setting.is_webrisk, 10) || 0;
        const fallbackVt = parseInt(setting.is_virustotal, 10) || 0;
        const enableRecaptcha = parseInt(setting.enable_recaptcha, 10) === 1;
        const form_id = setting.form_id ?? '';
        const form_class = setting.form_class ?? '';
        const submit_selector = setting.submit_selector ?? '';
        const rawSettings = setting.settings ? setting.settings : '{}';
        let formSettingData;
        
        console.log('[VMS Elements Form Guard] Processing setting:', {
            mappingId: mappingId,
            form_id: form_id,
            form_class: form_class,
            page_id: setting.page_id,
            enableRecaptcha: enableRecaptcha
        });
        try {
            formSettingData = typeof rawSettings === 'string' ? JSON.parse(rawSettings) : rawSettings;
        } catch (e) {
            formSettingData = [];
        }
        if (!Array.isArray(formSettingData)) {
            formSettingData = [];
        }

        const matchResult = vefgSettingMatchesCurrentPage(setting);
        if (!matchResult.matches) {
            console.log('[VMS Elements Form Guard] Skipping mapping ID:', mappingId, '- page does not match targets');
            return;
        }
        
        console.log('[VMS Elements Form Guard] Processing mapping ID:', mappingId, 'requiresFormSelector:', matchResult.requiresFormSelector);

        let $form = vefgFindFormForSetting(setting, matchResult);
        
        if (!$form.length) {
            if (matchResult.requiresFormSelector) {
                console.log('[VMS Elements Form Guard] Form not found - required for entire site mapping ID:', mappingId);
                return;
            }
            
            const hasFormSelector = String(form_id || '').trim() !== '' || String(form_class || '').trim() !== '';
            const hasSubmitSelector = String(submit_selector || '').trim() !== '';
            
            if (hasFormSelector) {
                $form = resolveForm$(form_id, form_class);
                if (!$form.length || vefgShouldSkipForm($form)) {
                    console.log('[VMS Elements Form Guard] Form not found or should be skipped for selector:', form_id || form_class);
                    return;
                }
                console.log('[VMS Elements Form Guard] Form found by form selector for mapping ID:', mappingId);
            } else if (hasSubmitSelector) {
                const $submitBtn = $(submit_selector).first();
                if (!$submitBtn.length) {
                    console.log('[VMS Elements Form Guard] Submit button not found for selector:', submit_selector);
                    return;
                }
                $form = $submitBtn.closest('form');
                if (!$form.length || vefgShouldSkipForm($form)) {
                    console.log('[VMS Elements Form Guard] No form found or should be skipped for submit button:', submit_selector);
                    return;
                }
                console.log('[VMS Elements Form Guard] Form found by submit selector for mapping ID:', mappingId);
            } else {
                const $forms = vefgGetContentForms();
                if ($forms.length >= 1) {
                    $form = $forms.first();
                    console.log('[VMS Elements Form Guard] Content form found on page for mapping ID:', mappingId, '(forms on page:', $forms.length, ')');
                } else {
                    console.log('[VMS Elements Form Guard] No content forms found on page for mapping ID:', mappingId);
                    return;
                }
            }
        }
        
        let submitButton = resolveSubmit$($form, submit_selector);

        const formEl = $form.get(0);
        if (!formEl || formEl.tagName !== 'FORM') {
            console.log('[VMS Elements Form Guard] Element is not a FORM, skipping');
            return;
        }

        // Check if form is already protected by another guard
        if (vefgIsFormAlreadyProtected($form)) {
            console.log('[VMS Elements Form Guard] Form already protected by another guard, skipping mapping ID:', mappingId);
            return;
        }

        // Mark form as protected
        vefgMarkFormAsProtected($form);
        console.log('[VMS Elements Form Guard] Marked form as protected for mapping ID:', mappingId);

        const isAutoValidation = parseInt(setting.auto_validation, 10) === 1 || setting.auto_validation === true || setting.auto_validation === '1';
        let autoRules = {};
        if (isAutoValidation) {
            try {
                autoRules = typeof setting.auto_rules === 'string' ? JSON.parse(setting.auto_rules) : setting.auto_rules;
            } catch (e) {
                autoRules = {};
            }
            autoRules = autoRules || {};
        }

        if (isAutoValidation) {
            const autoFields = vefgAutoDetectFormFields($form, autoRules);
            console.log('[VMS Elements Form Guard] Auto-detected fields:', autoFields.length, 'for mapping ID:', mappingId);
            console.log('[VMS Elements Form Guard] Auto rules:', autoRules);
            
            vefgRegisterFormGuardConfig(formEl, {
                mappingId: mappingId,
                isAuto: true,
                autoRules: autoRules,
                autoFields: autoFields,
                formSettingData: [],
            });
            
            const entry = vefgGetFormGuardEntry(formEl);
            console.log('[VMS Elements Form Guard] Setting up form validation interceptor for auto-validation, reCAPTCHA:', enableRecaptcha);
            vefgSetupFormValidation($form, submitButton, entry, enableRecaptcha);
            
            // Add real-time validation feedback on field change - show inline errors
            autoFields.forEach(function(fieldConfig) {
                const $field = fieldConfig.$el;
                const fieldType = fieldConfig.type;
                const rules = fieldConfig.rules || {};
                const isRequired = fieldConfig.required;
                
                // Clear error on focus
                $field.on('focus.vefgAuto', function() {
                    vefgClearFieldError($(this));
                });
                
                // Required field validation on blur
                if (isRequired) {
                    $field.on('blur.vefgRequired', function() {
                        const val = String($(this).val() || '').trim();
                        if (!val) {
                            vefgShowFieldError($(this), t('fieldRequired', 'This field is required.'));
                        }
                    });
                }
                
                if (fieldType === 'email' && rules.validate) {
                    $field.on('blur.vefgAuto change.vefgAuto', function() {
                        const val = String($(this).val() || '').trim();
                        if (!val) {
                            vefgClearFieldError($(this));
                            return;
                        }
                        const result = vefgValidateAutoField(fieldConfig);
                        if (!result.valid) {
                            vefgShowFieldError($(this), result.message);
                        } else {
                            vefgClearFieldError($(this));
                        }
                    });
                }
                
                if (fieldType === 'url' && rules.validate) {
                    $field.on('blur.vefgAuto change.vefgAuto', function() {
                        const val = String($(this).val() || '').trim();
                        if (!val) {
                            vefgClearFieldError($(this));
                            return;
                        }
                        const result = vefgValidateAutoField(fieldConfig);
                        if (!result.valid) {
                            vefgShowFieldError($(this), result.message);
                        } else {
                            vefgClearFieldError($(this));
                        }
                    });
                }
                
                if (fieldType === 'textarea' && rules.block_links) {
                    $field.on('blur.vefgAuto change.vefgAuto', function() {
                        const val = String($(this).val() || '').trim();
                        if (!val) {
                            vefgClearFieldError($(this));
                            return;
                        }
                        const result = vefgValidateAutoField(fieldConfig);
                        if (!result.valid) {
                            vefgShowFieldError($(this), result.message);
                        } else {
                            vefgClearFieldError($(this));
                        }
                    });
                }
                
                if (fieldType === 'text' && rules.block_urls) {
                    $field.on('blur.vefgAuto change.vefgAuto', function() {
                        const val = String($(this).val() || '').trim();
                        if (!val) {
                            vefgClearFieldError($(this));
                            return;
                        }
                        const result = vefgValidateAutoField(fieldConfig);
                        if (!result.valid) {
                            vefgShowFieldError($(this), result.message);
                        } else {
                            vefgClearFieldError($(this));
                        }
                    });
                }
                
                if (fieldType === 'password' && rules.strength) {
                    $field.on('blur.vefgAuto change.vefgAuto', function() {
                        const val = String($(this).val() || '').trim();
                        if (!val) {
                            vefgClearFieldError($(this));
                            return;
                        }
                        const result = vefgValidateAutoField(fieldConfig);
                        if (!result.valid) {
                            vefgShowFieldError($(this), result.message);
                        } else {
                            vefgClearFieldError($(this));
                        }
                    });
                }
            });
            
            return;
        }

        vefgRegisterFormGuardConfig(formEl, {
            mappingId: mappingId,
            isAuto: false,
            formSettingData: formSettingData,
        });

        const entry = vefgGetFormGuardEntry(formEl);

        console.log('[VMS Elements Form Guard] Setting up form validation interceptor for manual validation, reCAPTCHA:', enableRecaptcha);
        vefgSetupFormValidation($form, submitButton, entry, enableRecaptcha);

        formSettingData.forEach(function (formSetting, fieldIndex) {
            const eventType = formSetting.event;
            const fieldType = formSetting.field_type || formSetting.field || '';
            const apiOpts = apiPayloadFromField(formSetting, fallbackWr, fallbackVt);
            const reqOn = parseInt(formSetting.isRequired, 10) || 0;

            const $field = generateFieldClassId($form, formSetting.id, formSetting.class);
            applyRequired($field, !!reqOn);

            if (eventType === 'submit') {
                return;
            }
            if (!$field.length) {
                return;
            }
            switch (eventType) {
                case 'change':
                    if (fieldType === 'email') {
                        addChangeEvent($field, 'email', submitButton, apiOpts);
                    } else if (fieldType === 'url') {
                        addChangeEventUrl($field, submitButton, apiOpts);
                    }
                    break;
                case 'input':
                    addInputEvent($field, fieldType, submitButton, apiOpts);
                    break;
                default:
                    break;
            }
            if (
                fieldType === 'username' &&
                mappingId &&
                (parseInt(formSetting.check_username_exists, 10) || 0)
            ) {
                bindUsernameLiveCheck($field, submitButton, mappingId, fieldIndex);
            }
        });
    });

    /* Optional demo hooks: only bind when matching markup exists */
    const $verifyTempEmailBtn = $('#verifyTempEmailBtn');
    const $email = $('#email');
    const $password = $('#password');

    function isPasswordStrong(pwd) {
        return typeof pwd === 'string' && pwd.length >= 8;
    }

    if ($verifyTempEmailBtn.length && $email.length && $password.length) {
        $verifyTempEmailBtn.on('click', function (e) {
            e.preventDefault();
            const getEmail = $email.val().trim();
            const password = $password.val().trim();
            if (getEmail === '') {
                vefgToast.fire({ icon: 'error', title: t('emailFieldRequired', 'Email is required') });
                return;
            }
            if (password === '') {
                vefgToast.fire({ icon: 'error', title: t('passwordRequired', 'Password is required') });
                return;
            }
            if (!isPasswordStrong(password)) {
                vefgToast.fire({
                    icon: 'error',
                    title: t('passwordRequirements', 'Password must meet all requirements.'),
                });
                return;
            }
            const regDomain = getDomainFromEmail(getEmail);
            if (!regDomain) {
                vefgToast.fire({
                    icon: 'error',
                    title: t('emailInvalid', 'Email address is invalid'),
                });
                return;
            }
            validateUrlServer(regDomain, 'registration', [{ is_webrisk: 0, is_virustotal: 0 }])
                .then(function (res) {
                    if (res.status) {
                        vefgToast.fire({
                            icon: 'success',
                            title: res.message,
                            timer: 2000,
                        });
                    } else {
                        vefgToast.fire({
                            icon: 'error',
                            title: res.message || t('emailInvalid', 'Email address is invalid'),
                        });
                    }
                })
                .catch(function (err) {
                    vefgToast.fire({
                        icon: 'error',
                        title: err.message || t('validationFailed', 'Validation failed'),
                    });
                });
        });
    }

    const $verifyLinkButton = $('#verifyLinkButton');
    const $createShortLinkButton = $('#createShortLinkButton');
    const $urlValidation = $('#urlValidation');

    if ($verifyLinkButton.length && $createShortLinkButton.length) {
        $verifyLinkButton.on('click', async function () {
            const $urlInput = $('#link_location_url');
            const url = $urlInput.val().trim();
            if (url === '') {
                $createShortLinkButton.prop('disabled', true);
                vefgToast.fire({
                    icon: 'error',
                    title: t('urlRequired', 'URL is required'),
                });
                return;
            }
            $createShortLinkButton.prop('disabled', true);
            if (!isValidUrl(url)) {
                vefgToast.fire({
                    icon: 'error',
                    title: t('urlNotValid', 'URL not valid'),
                });
                return;
            }
            let toCheck = url;
            if (!/^https?:\/\//i.test(toCheck)) {
                toCheck = 'https://' + toCheck;
            }
            try {
                const res = await validateUrlServer(toCheck, 'url', [{ is_webrisk: 0, is_virustotal: 0 }]);
                if (res.status === true) {
                    $createShortLinkButton.prop('disabled', false);
                    vefgToast.fire({
                        icon: 'success',
                        title: t('urlValid', 'URL is valid'),
                    });
                    if ($urlValidation.length) {
                        $urlValidation.val(1);
                    }
                } else {
                    $createShortLinkButton.prop('disabled', true);
                    vefgToast.fire({
                        icon: 'error',
                        title: res.message || t('urlNotValid', 'URL not valid'),
                    });
                    if ($urlValidation.length) {
                        $urlValidation.val(0);
                    }
                }
            } catch (err) {
                $createShortLinkButton.prop('disabled', true);
                vefgToast.fire({
                    icon: 'error',
                    title: err.message || String(err),
                });
            }
        });
    }

    if ($createShortLinkButton.length) {
        $createShortLinkButton.on('click', async function submitShortUrl(e) {
            e.preventDefault();
            const $urlInput = $('#link_location_url');
            const urlStatus = $urlValidation.length ? parseInt($('#urlValidation').val(), 10) : 0;
            let urlValue = $urlInput.val().trim();
            if (!isValidUrl(urlValue)) {
                vefgToast.fire({
                    icon: 'error',
                    title: t('urlNotValid', 'URL not valid'),
                });
                return;
            }
            if (!/^https?:\/\//i.test(urlValue)) {
                urlValue = 'https://' + urlValue;
            }
            if (!urlStatus) {
                try {
                    const res = await validateUrlServer(urlValue, 'url', [{ is_webrisk: 0, is_virustotal: 0 }]);
                    if (res.status === true) {
                        $(this).parent().closest('form').trigger('submit');
                    } else {
                        $createShortLinkButton.prop('disabled', true);
                        vefgToast.fire({
                            icon: 'error',
                            title: res.message || 'URL not valid',
                        });
                    }
                } catch (err) {
                    $createShortLinkButton.prop('disabled', true);
                }
            } else {
                $(this).parent().closest('form').trigger('submit');
            }
        });
    }
});

jQuery(document).on('vefgFormSubmit', function () {});

function vefgStopFormSubmit(payload) {
    payload.originalEvent.preventDefault();
    payload.originalEvent.stopImmediatePropagation();
}

jQuery(document).on('click', '.quform-submit', function (e) {
    const $form = jQuery(this).closest('form');
    jQuery(document).trigger('vefgFormSubmit', {
        originalEvent: e,
        formId: $form.attr('id'),
        action: $form.attr('action'),
        data: $form.serializeArray(),
        test: true,
    });
});
