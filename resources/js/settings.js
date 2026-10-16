import Alpine from 'alpinejs';

/**
 * Receipt / KOT live preview: sends the current (unsaved) form to the server, which renders
 * the real receipt template with sample data. Nothing is saved.
 */
Alpine.data('settingsPreview', ({ url }) => {
    let sequence = 0;

    return {
        loading: false,
        error: null,

        async refresh() {
            const body = new FormData(this.$root);
            body.delete('_method'); // the preview endpoint is POST; the form itself saves with PUT
            // Don't send image files to the preview endpoint — images are shown from the saved setting.
            // File uploads in a fetch preview cause PHP upload errors in some environments.
            for (const key of [...body.keys()]) {
                if (body.get(key) instanceof Blob) body.delete(key);
            }
            const id = ++sequence;
            this.loading = true;

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body,
                });
                const data = await response.json().catch(() => ({}));
                if (id !== sequence) return; // a newer preview is on its way

                if (response.ok) {
                    this.$refs.preview.innerHTML = data.html;
                    this.error = null;
                } else {
                    const first = Object.values(data.errors ?? {})[0];
                    this.error = (Array.isArray(first) ? first[0] : null) ?? data.message ?? 'Preview failed.';
                }
            } catch (e) {
                if (id === sequence) this.error = 'Preview failed. Check the connection.';
            } finally {
                if (id === sequence) this.loading = false;
            }
        },
    };
});

/**
 * Appearance settings: picking a theme fills the colours; editing a colour switches to "custom".
 */
Alpine.data('appearanceSettings', ({ themes, values }) => ({
    themes,
    ...values,

    applyTheme() {
        const preset = this.themes[this.theme];
        if (!preset) return;
        this.primary_color = preset.primary;
        this.secondary_color = preset.secondary;
        this.button_color = preset.button;
        this.sidebar_color = preset.sidebar;
        this.sidebar_text_color = preset.sidebar_text;
    },

    customised() {
        this.theme = 'custom';
    },
}));
