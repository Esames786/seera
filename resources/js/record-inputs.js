document.querySelectorAll('[data-master-code]').forEach(root => {
    const checkbox = root.querySelector('[data-code-manual]');
    const input = root.querySelector('[data-code-input]');
    if (!checkbox || !input) return;
    checkbox.addEventListener('change', () => {
        input.hidden = !checkbox.checked;
        if (!checkbox.checked) input.value = '';
        else input.focus();
        input.dispatchEvent(new Event('input', { bubbles: true }));
    });
});

document.querySelectorAll('[data-profile-photo]').forEach(root => {
    let previewUrl;
    const input = root.querySelector('[data-photo-input]');
    input?.addEventListener('change', () => {
        if (previewUrl) URL.revokeObjectURL(previewUrl);
        const preview = root.querySelector('[data-photo-preview]');
        const file = input.files[0];
        if (!file || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) return;
        previewUrl = URL.createObjectURL(file);
        preview.src = previewUrl;
        preview.hidden = false;
    });
});

document.querySelectorAll('[data-document-inputs]').forEach(root => {
    let nextIndex = root.querySelectorAll('[data-document-row]').length;
    root.addEventListener('click', event => {
        if (event.target.closest('[data-add-document]')) {
            const rows = root.querySelectorAll('[data-document-row]');
            if (!rows.length || rows.length >= 10) return;
            const copy = rows[0].cloneNode(true);
            copy.querySelectorAll('input,select').forEach(input => {
                input.name = input.name.replace(/\[\d+\]/, `[${nextIndex}]`);
                input.value = input.tagName === 'SELECT' ? 'other' : '';
            });
            nextIndex++;
            root.querySelector('[data-add-document]').before(copy);
        }
        const remove = event.target.closest('[data-remove-document]');
        if (remove) {
            const row = remove.closest('[data-document-row]');
            if (root.querySelectorAll('[data-document-row]').length > 1) row.remove();
            else row.querySelectorAll('input,select').forEach(input => { input.value = input.tagName === 'SELECT' ? 'other' : ''; });
            root.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
});
