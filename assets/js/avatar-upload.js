const avatarForm = document.querySelector('[data-avatar-upload]');
if (avatarForm) {
    const input = avatarForm.querySelector('[name="avatar"]');
    const preview = avatarForm.querySelector('[data-avatar-preview]');
    const status = avatarForm.querySelector('[data-avatar-status]');
    const submit = avatarForm.querySelector('[type="submit"]');
    let revision = 0;
    input.addEventListener('change', async () => {
        const current = ++revision;
        const file = input.files[0];
        preview.hidden = true;
        status.textContent = '';
        input.setCustomValidity('');
        if (!file) return;
        if (file.size > 8 * 1024 * 1024 || !['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            input.setCustomValidity('Choose a JPG, PNG, or WebP up to 8 MB.');
            input.reportValidity();
            return;
        }
        submit.disabled = true;
        status.textContent = 'Preparing your photo…';
        let bitmap;
        try {
            bitmap = await createImageBitmap(file);
            if (current !== revision) return;
            if (bitmap.width * bitmap.height > 16000000 || bitmap.width > 8000 || bitmap.height > 8000) {
                input.setCustomValidity('Choose a photo with no more than 16 megapixels.');
                status.textContent = input.validationMessage;
                return;
            }
            const side = Math.min(bitmap.width, bitmap.height);
            const canvas = document.createElement('canvas');
            canvas.width = canvas.height = Math.min(512, side);
            const context = canvas.getContext('2d');
            context.fillStyle = '#fff';
            context.fillRect(0, 0, canvas.width, canvas.height);
            context.drawImage(bitmap, (bitmap.width - side) / 2, (bitmap.height - side) / 2, side, side, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.82));
            if (current !== revision) return;
            if (!blob) throw new Error('Image encoding unavailable');
            const transfer = new DataTransfer();
            transfer.items.add(new File([blob], 'profile.jpg', {type: 'image/jpeg'}));
            input.files = transfer.files;
            preview.src = canvas.toDataURL('image/jpeg', 0.82);
            preview.hidden = false;
            status.textContent = `Ready to upload · ${Math.max(1, Math.round(blob.size / 1024))} KB`;
        } catch {
            if (current === revision) {
                status.textContent = 'Your photo will be optimized when uploaded.';
            }
        } finally {
            bitmap?.close();
            if (current === revision) submit.disabled = false;
        }
    });
    avatarForm.addEventListener('submit', () => {
        submit.disabled = true;
        status.textContent = 'Uploading your photo…';
    });
}
