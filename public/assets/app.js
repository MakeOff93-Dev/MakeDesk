(() => {
    const body = document.body;
    const menuButton = document.querySelector('[data-menu]');
    const closeMenu = () => {
        body.classList.remove('menu-open');
        menuButton?.setAttribute('aria-expanded', 'false');
    };
    menuButton?.setAttribute('aria-expanded', 'false');
    menuButton?.addEventListener('click', () => {
        const open = body.classList.toggle('menu-open');
        menuButton.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.querySelector('[data-menu-close]')?.addEventListener('click', closeMenu);
    document.querySelectorAll('.nav a').forEach((link) => link.addEventListener('click', closeMenu));
    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && body.classList.contains('menu-open')) closeMenu();
    });

    const requestedSection = new URLSearchParams(window.location.search).get('section');
    if (requestedSection && /^[a-z0-9-]{2,40}$/i.test(requestedSection)) {
        document.getElementById(requestedSection)?.scrollIntoView({block: 'start'});
    }

    document.querySelector('[data-channel-switch]')?.addEventListener('change', (event) => {
        event.currentTarget.form?.requestSubmit();
    });

    const versionDialog = document.querySelector('[data-version-dialog]');
    document.querySelector('[data-version-open]')?.addEventListener('click', () => {
        if (versionDialog?.showModal) versionDialog.showModal();
    });
    versionDialog?.querySelector('[data-version-close]')?.addEventListener('click', () => versionDialog.close());
    versionDialog?.addEventListener('click', (event) => {
        if (event.target === versionDialog) versionDialog.close();
    });

    document.querySelectorAll('[data-dismiss]').forEach((button) => {
        button.addEventListener('click', () => button.closest('.alert')?.remove());
    });

    document.querySelectorAll('[data-file-input]').forEach((input) => {
        const field = input.closest('.upload-field');
        const status = field?.querySelector('[data-file-status]');
        const update = () => {
            const file = input.files?.[0];
            field?.classList.toggle('has-file', Boolean(file));
            if (!status) return;
            if (!file) {
                status.textContent = 'Noch keine Datei ausgewählt.';
                return;
            }
            const size = file.size >= 1048576
                ? `${(file.size / 1048576).toFixed(1).replace('.', ',')} MB`
                : `${Math.max(1, Math.round(file.size / 1024))} KB`;
            status.textContent = `${file.name} · ${size}`;
        };
        input.addEventListener('change', update);
        update();
    });

    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (!window.confirm(element.dataset.confirm || 'Aktion wirklich ausführen?')) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-mod-form]').forEach((form) => {
        const action = form.querySelector('[data-mod-action]');
        const duration = form.querySelector('[data-duration]');
        const update = () => {
            if (duration) duration.hidden = action?.value !== 'timeout';
        };
        action?.addEventListener('change', update);
        update();
    });

    document.querySelectorAll('[data-ban-sync-form]').forEach((form) => {
        const actions = form.querySelectorAll('input[name="sync_action"]');
        const reason = form.querySelector('[data-sync-reason]');
        const submit = form.querySelector('[data-sync-submit]');
        const update = () => {
            const selected = form.querySelector('input[name="sync_action"]:checked')?.value || 'ban';
            if (reason) {
                reason.required = selected === 'ban';
                reason.placeholder = selected === 'ban'
                    ? 'Wird an Twitch übermittelt und im Banlog gespeichert.'
                    : 'Optionale interne Notiz zum Unban.';
            }
            if (submit) {
                submit.dataset.confirm = selected === 'ban'
                    ? 'Diesen User jetzt auf allen ausgewählten Twitch-Kanälen dauerhaft bannen?'
                    : 'Den Ban dieses Users jetzt auf allen ausgewählten Twitch-Kanälen aufheben?';
            }
        };
        actions.forEach((action) => action.addEventListener('change', update));
        update();
    });

    document.querySelectorAll('[data-design-editor]').forEach((editor) => {
        const preview = editor.querySelector('[data-design-preview]');
        const nameInput = editor.querySelector('[data-design-name]');
        const eyebrowInput = editor.querySelector('[data-design-eyebrow]');
        const footerInput = editor.querySelector('[data-design-footer]');
        const syncCopy = () => {
            const name = nameInput?.value.trim() || 'Twitch ModDesk';
            const eyebrow = eyebrowInput?.value.trim() || 'CONTROL CENTER';
            const footer = footerInput?.value.trim() || 'Eigener Footer-Text';
            const namePreview = editor.querySelector('[data-preview-name]');
            const eyebrowPreview = editor.querySelector('[data-preview-eyebrow]');
            const footerPreview = editor.querySelector('[data-preview-footer]');
            if (namePreview) namePreview.textContent = name;
            if (eyebrowPreview) eyebrowPreview.textContent = eyebrow;
            if (footerPreview) footerPreview.textContent = footer;
        };
        [nameInput, eyebrowInput, footerInput].forEach((input) => input?.addEventListener('input', syncCopy));
        syncCopy();

        const variableMap = {
            background: '--bg', surface: '--surface', surface_alt: '--surface-2', text: '--text',
            muted: '--muted', primary: '--purple', secondary: '--purple-2'
        };
        editor.querySelectorAll('[data-theme-color]').forEach((input) => {
            const syncColor = () => {
                input.closest('.color-field')?.querySelector('code')?.replaceChildren(input.value.toUpperCase());
                const variable = variableMap[input.dataset.themeColor];
                if (preview && variable) preview.style.setProperty(variable, input.value);
            };
            input.addEventListener('input', syncColor);
            syncColor();
        });

        editor.querySelector('[data-logo-input]')?.addEventListener('change', (event) => {
            const file = event.currentTarget.files?.[0];
            if (!file || !file.type.startsWith('image/')) return;
            const reader = new FileReader();
            reader.addEventListener('load', () => {
                const image = editor.querySelector('[data-logo-preview]');
                if (image) {
                    image.src = String(reader.result || '');
                    image.hidden = false;
                }
                const fallback = editor.querySelector('[data-logo-fallback]');
                if (fallback) fallback.hidden = true;
            });
            reader.readAsDataURL(file);
        });
    });

    document.querySelectorAll('[data-avatar-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            if (!file || !['image/png', 'image/jpeg', 'image/webp'].includes(file.type)) return;
            const shell = document.querySelector('[data-avatar-preview-shell]');
            const image = shell?.querySelector('[data-avatar-preview]');
            const fallback = shell?.querySelector('[data-avatar-fallback]');
            if (!image) return;
            const reader = new FileReader();
            reader.addEventListener('load', () => {
                image.src = String(reader.result || '');
                image.hidden = false;
                if (fallback) fallback.hidden = true;
            });
            reader.readAsDataURL(file);
        });
    });

    const copyText = async (value, button) => {
        if (!value) return;
        const original = button?.textContent || '';
        try {
            if (navigator.clipboard?.writeText && window.isSecureContext) {
                await navigator.clipboard.writeText(value);
            } else {
                const temporary = document.createElement('textarea');
                temporary.value = value;
                temporary.setAttribute('readonly', '');
                temporary.style.position = 'fixed';
                temporary.style.opacity = '0';
                document.body.append(temporary);
                temporary.select();
                document.execCommand('copy');
                temporary.remove();
            }
            if (button) {
                button.textContent = 'Kopiert ✓';
                window.setTimeout(() => { button.textContent = original; }, 1800);
            }
        } catch {
            if (button) {
                button.textContent = 'Kopieren fehlgeschlagen';
                window.setTimeout(() => { button.textContent = original; }, 2200);
            }
        }
    };

    document.querySelectorAll('[data-copy-target]').forEach((button) => {
        button.addEventListener('click', () => {
            const source = document.querySelector(`[data-copy-source="${button.dataset.copyTarget}"]`);
            copyText(('value' in (source || {}) ? source.value : source?.textContent || '').trim(), button);
        });
    });
    document.querySelector('[data-copy-recovery]')?.addEventListener('click', (event) => {
        const codes = [...document.querySelectorAll('[data-recovery-codes] code')]
            .map((element) => element.textContent.trim())
            .filter(Boolean)
            .join('\n');
        copyText(codes, event.currentTarget);
    });
    document.querySelector('[data-print-recovery]')?.addEventListener('click', () => window.print());

    document.querySelectorAll('.otp-input[data-otp-only]').forEach((input) => {
        input.addEventListener('input', () => {
            const digits = input.value.replace(/\D/g, '').slice(0, 6);
            input.value = digits.length > 3 ? `${digits.slice(0, 3)} ${digits.slice(3)}` : digits;
        });
    });

    document.querySelectorAll('.permission-editor').forEach((editor) => {
        editor.querySelectorAll('[data-permission-set]').forEach((button) => {
            button.addEventListener('click', () => {
                const checked = button.dataset.permissionSet === 'all';
                const scope = button.dataset.permissionScope || '';
                const container = scope
                    ? editor.querySelector(`[data-permission-category="${scope}"]`)
                    : editor;
                container?.querySelectorAll('input[type="checkbox"][name="permissions[]"]').forEach((checkbox) => {
                    checkbox.checked = checked;
                    checkbox.dispatchEvent(new Event('change', {bubbles: true}));
                });
            });
        });
    });

    document.querySelectorAll('[data-discord-editor]').forEach((editor) => {
        const value = (selector) => editor.querySelector(selector)?.value.trim() || '';
        const fieldsRoot = editor.querySelector('[data-embed-fields]');
        let lastTextInput = editor.querySelector('[name="message_content"]');

        const setImage = (element, url) => {
            if (!element) return;
            if (/^https:\/\//i.test(url)) {
                element.src = url;
                element.hidden = false;
                element.onerror = () => { element.hidden = true; };
            } else {
                element.removeAttribute('src');
                element.hidden = true;
            }
        };

        const updatePreview = () => {
            const content = value('[name="message_content"]');
            const title = value('[name="embed_title"]');
            const description = value('[name="embed_description"]');
            const author = value('[name="author_name"]');
            const footer = value('[name="footer_text"]');
            const contentPreview = editor.querySelector('[data-preview-content]');
            const titlePreview = editor.querySelector('[data-preview-title]');
            const descriptionPreview = editor.querySelector('[data-preview-description]');
            const authorPreview = editor.querySelector('[data-preview-author]');
            const authorWrap = editor.querySelector('[data-preview-author-wrap]');
            const footerPreview = editor.querySelector('[data-preview-footer]');
            const timestampPreview = editor.querySelector('[data-preview-timestamp]');
            if (contentPreview) {
                contentPreview.textContent = content;
                contentPreview.hidden = content === '';
            }
            if (titlePreview) {
                titlePreview.textContent = title || 'Embed-Titel';
                titlePreview.hidden = title === '';
            }
            if (descriptionPreview) {
                descriptionPreview.textContent = description;
                descriptionPreview.hidden = description === '';
            }
            if (authorPreview) authorPreview.textContent = author;
            if (authorWrap) authorWrap.hidden = author === '';
            if (footerPreview) footerPreview.textContent = footer;
            if (timestampPreview) timestampPreview.hidden = !editor.querySelector('[name="include_timestamp"]')?.checked;

            const embedPreview = editor.querySelector('[data-preview-embed]');
            const color = editor.querySelector('[name="embed_color"]')?.value || '#c7364a';
            if (embedPreview) embedPreview.style.borderLeftColor = color;
            const colorCode = editor.querySelector('[data-color-code]');
            if (colorCode) colorCode.textContent = color.toUpperCase();

            setImage(editor.querySelector('[data-preview-author-icon]'), value('[name="author_icon_url"]'));
            setImage(editor.querySelector('[data-preview-thumbnail]'), value('[name="thumbnail_url"]'));
            setImage(editor.querySelector('[data-preview-image]'), value('[name="image_url"]'));
            setImage(editor.querySelector('[data-preview-footer-icon]'), value('[name="footer_icon_url"]'));

            const fieldsPreview = editor.querySelector('[data-preview-fields]');
            if (fieldsPreview) {
                fieldsPreview.replaceChildren();
                fieldsRoot?.querySelectorAll('[data-embed-field]').forEach((row) => {
                    const fieldName = row.querySelector('[data-field-name]')?.value.trim() || '';
                    const fieldValue = row.querySelector('[data-field-value]')?.value.trim() || '';
                    if (!fieldName && !fieldValue) return;
                    const field = document.createElement('div');
                    field.className = row.querySelector('[data-field-inline]')?.checked ? 'inline' : '';
                    const strong = document.createElement('strong');
                    const copy = document.createElement('span');
                    strong.textContent = fieldName || 'Feldname';
                    copy.textContent = fieldValue || 'Inhalt';
                    field.append(strong, copy);
                    fieldsPreview.append(field);
                });
            }

            const hasFields = Boolean(fieldsPreview?.children.length);
            const hasEmbed = Boolean(title || description || author || footer || hasFields || value('[name="thumbnail_url"]') || value('[name="image_url"]'));
            if (embedPreview) embedPreview.hidden = !hasEmbed;
        };

        const reindexFields = () => {
            fieldsRoot?.querySelectorAll('[data-embed-field]').forEach((row, index) => {
                const name = row.querySelector('[data-field-name]');
                const fieldValue = row.querySelector('[data-field-value]');
                const inline = row.querySelector('[data-field-inline]');
                if (name) name.name = `embed_fields[${index}][name]`;
                if (fieldValue) fieldValue.name = `embed_fields[${index}][value]`;
                if (inline) inline.name = `embed_fields[${index}][inline]`;
            });
        };

        const bindField = (row) => {
            row.querySelectorAll('input, textarea').forEach((input) => {
                if (input.matches('textarea, input[type="text"]:not([inputmode="numeric"]), input[type="url"]')) {
                    input.addEventListener('focus', () => { lastTextInput = input; });
                }
                input.addEventListener('input', updatePreview);
                input.addEventListener('change', updatePreview);
            });
            row.querySelector('[data-remove-embed-field]')?.addEventListener('click', () => {
                row.remove();
                reindexFields();
                updatePreview();
            });
        };

        fieldsRoot?.querySelectorAll('[data-embed-field]').forEach(bindField);
        editor.querySelectorAll('input, textarea').forEach((input) => {
            if (input.matches('textarea, input[type="text"]:not([inputmode="numeric"]), input[type="url"]')) {
                input.addEventListener('focus', () => { lastTextInput = input; });
            }
            input.addEventListener('input', updatePreview);
            input.addEventListener('change', updatePreview);
        });

        editor.querySelector('[data-add-embed-field]')?.addEventListener('click', () => {
            if (!fieldsRoot || fieldsRoot.children.length >= 25) return;
            const template = document.querySelector('#embed-field-template');
            const row = template?.content.firstElementChild?.cloneNode(true);
            if (!row) return;
            fieldsRoot.append(row);
            bindField(row);
            reindexFields();
            row.querySelector('[data-field-name]')?.focus();
            updatePreview();
        });

        editor.querySelector('[data-emoji-toggle]')?.addEventListener('click', () => {
            const picker = editor.querySelector('[data-emoji-picker]');
            if (picker) picker.hidden = !picker.hidden;
        });
        editor.querySelectorAll('[data-emoji]').forEach((button) => {
            button.addEventListener('click', () => {
                if (!lastTextInput || !('value' in lastTextInput)) return;
                const start = lastTextInput.selectionStart ?? lastTextInput.value.length;
                const end = lastTextInput.selectionEnd ?? start;
                lastTextInput.value = lastTextInput.value.slice(0, start) + button.dataset.emoji + lastTextInput.value.slice(end);
                const nextPosition = start + (button.dataset.emoji || '').length;
                lastTextInput.focus();
                lastTextInput.setSelectionRange?.(nextPosition, nextPosition);
                lastTextInput.dispatchEvent(new Event('input', {bubbles: true}));
            });
        });

        editor.querySelectorAll('[data-discord-route]').forEach((button) => {
            button.addEventListener('click', () => {
                const guild = editor.querySelector('[data-guild-input]');
                const channel = editor.querySelector('[data-channel-input]');
                if (guild) guild.value = button.dataset.guildId || '';
                if (channel) channel.value = button.dataset.channelId || '';
            });
        });

        reindexFields();
        updatePreview();
    });
})();
