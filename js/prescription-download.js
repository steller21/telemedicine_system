(function () {
    function inferDownloadName(url, fallbackName) {
        try {
            var pathname = new URL(url, window.location.href).pathname;
            var fileName = pathname.substring(pathname.lastIndexOf('/') + 1);
            return fileName || fallbackName;
        } catch (error) {
            return fallbackName;
        }
    }

    async function downloadFileWithPicker(url, suggestedName) {
        var response = await fetch(url, { credentials: 'same-origin' });
        if (!response.ok) {
            throw new Error('Failed to download file.');
        }

        var blob = await response.blob();
        var fileName = suggestedName || inferDownloadName(url, 'prescription.pdf');
        var extension = fileName.indexOf('.') !== -1 ? fileName.slice(fileName.lastIndexOf('.')) : '.pdf';
        var mimeType = blob.type || 'application/octet-stream';

        if (window.showSaveFilePicker) {
            var pickerHandle = await window.showSaveFilePicker({
                suggestedName: fileName,
                types: [{
                    description: 'Prescription file',
                    accept: {
                        [mimeType]: [extension]
                    }
                }]
            });
            var writable = await pickerHandle.createWritable();
            await writable.write(blob);
            await writable.close();
            return;
        }

        var blobUrl = window.URL.createObjectURL(blob);
        var anchor = document.createElement('a');
        anchor.href = blobUrl;
        anchor.download = fileName;
        document.body.appendChild(anchor);
        anchor.click();
        anchor.remove();
        window.setTimeout(function () {
            window.URL.revokeObjectURL(blobUrl);
        }, 1500);
    }

    document.addEventListener('click', function (event) {
        var link = event.target.closest('.prescription-download-link');
        if (!link) {
            return;
        }

        event.preventDefault();

        var href = link.getAttribute('href');
        if (!href) {
            return;
        }

        var originalText = link.textContent;
        link.style.pointerEvents = 'none';
        link.textContent = 'Downloading...';

        downloadFileWithPicker(href, link.dataset.downloadName || inferDownloadName(href, 'prescription.pdf'))
            .catch(function (error) {
                if (error && error.name === 'AbortError') {
                    return;
                }

                console.error(error);
                window.open(href, '_blank', 'noopener');
            })
            .finally(function () {
                link.style.pointerEvents = '';
                link.textContent = originalText;
            });
    });

    window.downloadFileWithPicker = downloadFileWithPicker;
})();
