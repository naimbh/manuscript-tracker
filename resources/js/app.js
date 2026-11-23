import { AvatarCropper, MAX_ZOOM, MESSAGES, MIN_ZOOM, checkFile, loadImage } from './avatar-cropper.js';

/**
 * Profile photo (Settings → Profile): pick or drop an image, crop it in the "avatar-crop" modal, then upload the
 * 512 × 512 result to the Livewire component (`photo`, then `savePhoto()`). The original image never leaves the
 * browser.
 */
function avatarPhoto() {
    // Kept outside Alpine's reactive state (the cropper holds DOM and canvas objects).
    let cropper = null;

    return {
        error: '',
        uploadError: '',
        zoom: 1,
        minZoom: MIN_ZOOM,
        maxZoom: MAX_ZOOM,
        saving: false,
        dragOver: false,
        url: null,

        destroy() {
            cropper?.destroy();
            cropper = null;
            this.revoke();
        },

        setZoom(z) {
            cropper?.setZoom(z);
        },

        zoomBy(step) {
            cropper?.zoomBy(step);
        },

        rotate() {
            cropper?.rotate();
        },

        reset() {
            cropper?.reset();
        },

        choose() {
            this.$refs.file.click();
        },

        async picked(event) {
            const file = event.target.files?.[0];
            event.target.value = '';
            if (file) await this.open(file);
        },

        async dropped(event) {
            this.dragOver = false;
            const files = [...(event.dataTransfer?.files ?? [])];
            if (!files.length) return;
            if (files.length > 1) {
                this.error = 'Drop one image at a time.';
                return;
            }
            await this.open(files[0]);
        },

        async open(file) {
            this.error = '';
            const message = await checkFile(file);
            if (message) {
                this.error = message;
                return;
            }
            this.revoke();
            this.url = URL.createObjectURL(file);
            let img;
            try {
                img = await loadImage(this.url);
            } catch {
                this.error = MESSAGES.decode;
                this.revoke();
                return;
            }
            this.zoom = 1;
            this.uploadError = '';
            this.$flux.modal('avatar-crop').show();
            await this.$nextTick();
            await new Promise((resolve) => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            cropper?.destroy();
            // The stage is inside the modal's own Alpine scope, so it is found by id rather than x-ref.
            const stage = document.getElementById('avatar-crop-stage');
            cropper = new AvatarCropper(stage, { onChange: (s) => { this.zoom = s.zoom; } });
            cropper.setImage(img);
            stage.focus({ preventScroll: true });
        },

        closed() {
            cropper?.destroy();
            cropper = null;
            this.saving = false;
            this.revoke();
            document.querySelector('[data-photo-upload]')?.focus({ preventScroll: true });
        },

        revoke() {
            if (this.url) URL.revokeObjectURL(this.url);
            this.url = null;
        },

        async save() {
            if (!cropper || this.saving) return;
            this.saving = true;
            this.uploadError = '';
            try {
                const blob = await cropper.export();
                const file = new File([blob], blob.type === 'image/webp' ? 'avatar.webp' : 'avatar.png', { type: blob.type });
                await new Promise((resolve, reject) => this.$wire.upload('photo', file, resolve, reject));
                if (await this.$wire.savePhoto()) this.$flux.modal('avatar-crop').close();
            } catch {
                // Validation errors come back through the component; this is a failed upload.
                this.uploadError = 'The photo could not be uploaded. Please try again.';
            } finally {
                this.saving = false;
            }
        },
    };
}

document.addEventListener('alpine:init', () => {
    window.Alpine.data('avatarPhoto', avatarPhoto);
});
