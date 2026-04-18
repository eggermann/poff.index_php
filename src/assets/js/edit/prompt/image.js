export function isSupportedImageFile(file) {
    return !!file && typeof file.type === 'string' && file.type.startsWith('image/');
}

export function readImageFile(file) {
    return new Promise((resolve, reject) => {
        if (!isSupportedImageFile(file)) {
            reject(new Error('Only image files are supported.'));
            return;
        }

        const reader = new FileReader();
        reader.onload = () => {
            const dataUrl = typeof reader.result === 'string' ? reader.result : '';
            if (!dataUrl.startsWith('data:image/')) {
                reject(new Error('Invalid image data.'));
                return;
            }
            resolve({
                name: file.name || 'clipboard-image.png',
                mimeType: file.type || 'image/png',
                dataUrl,
            });
        };
        reader.onerror = () => reject(new Error('Failed to read image.'));
        reader.readAsDataURL(file);
    });
}
