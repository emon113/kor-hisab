/* Salary certificate upload: pick or drop a file, shrink photos in the browser, upload with progress. */
document.addEventListener('alpine:init', () => {
    const MAX_EDGE = 2400;          // px; plenty for OCR, far smaller than a phone photo
    const SHRINK_ABOVE = 900 * 1024;

    window.Alpine.data('certificateUpload', (boot) => ({
        file: null,
        preview: null,
        stage: 'idle',      // idle → picked → uploading → reading
        progress: 0,
        error: null,
        dragging: false,

        get isPdf() { return this.file?.type === 'application/pdf'; },
        get sizeLabel() {
            if (!this.file) return '';
            const kb = this.file.size / 1024;
            return kb > 1024 ? KH.num((kb / 1024).toFixed(1)) + ' MB' : KH.num(Math.round(kb)) + ' KB';
        },

        drop(e) {
            this.dragging = false;
            const f = e.dataTransfer?.files?.[0];
            if (f) this.pick(f);
        },
        async pick(f) {
            this.error = null;
            if (!f) return;
            if (!/^(image\/(jpeg|png|webp|heic|heif)|application\/pdf)$/.test(f.type)) {
                this.error = KH.t('Choose a JPG, PNG or WEBP photo, or a PDF.');
                return;
            }
            if (f.size > boot.maxBytes) {
                this.error = KH.t('That file is over :size. A photo or the PDF from your employer is usually much smaller.', { size: boot.maxLabel });
                return;
            }
            try {
                this.file = f.type.startsWith('image/') ? await this.shrink(f) : f;
            } catch (e) {
                this.error = KH.t('This photo format cannot be opened here. Save it as JPG and try again.');
                return;
            }
            if (this.preview) URL.revokeObjectURL(this.preview);
            this.preview = this.isPdf ? null : URL.createObjectURL(this.file);
            this.stage = 'picked';
        },
        /* Re-encode large or HEIC photos as a JPEG of at most MAX_EDGE px. */
        async shrink(f) {
            if (f.size <= SHRINK_ABOVE && !/hei[cf]/.test(f.type)) return f;
            const bitmap = await createImageBitmap(f);
            const scale = Math.min(1, MAX_EDGE / Math.max(bitmap.width, bitmap.height));
            const canvas = document.createElement('canvas');
            canvas.width = Math.round(bitmap.width * scale);
            canvas.height = Math.round(bitmap.height * scale);
            canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
            const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.85));
            return new File([blob], f.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' });
        },
        reset() {
            if (this.preview) URL.revokeObjectURL(this.preview);
            Object.assign(this, { file: null, preview: null, stage: 'idle', progress: 0, error: null });
        },

        upload() {
            if (!this.file || this.stage === 'uploading' || this.stage === 'reading') return;
            this.stage = 'uploading';
            this.progress = 0;
            this.error = null;
            const body = new FormData();
            body.append('file', this.file);
            const xhr = new XMLHttpRequest();
            xhr.open('POST', boot.routes.store);
            xhr.setRequestHeader('X-CSRF-TOKEN', KH.csrf());
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.upload.onprogress = (e) => { if (e.lengthComputable) this.progress = Math.round((e.loaded / e.total) * 100); };
            // Upload done; the server is now reading the document.
            xhr.upload.onload = () => { this.stage = 'reading'; };
            xhr.onload = () => {
                let data = null;
                try { data = JSON.parse(xhr.responseText); } catch (e) { /* not JSON */ }
                if (xhr.status >= 200 && xhr.status < 300 && data?.redirect) {
                    window.location.href = data.redirect;
                    return;
                }
                this.stage = 'picked';
                this.error = (data?.errors && Object.values(data.errors)[0]?.[0]) || data?.message || KH.t('The upload did not go through. Try again.');
            };
            xhr.onerror = () => { this.stage = 'picked'; this.error = KH.t('The upload did not go through. Check your connection and try again.'); };
            xhr.send(body);
        },
    }));
});
