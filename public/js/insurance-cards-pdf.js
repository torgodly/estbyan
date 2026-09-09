const CARD_WIDTH_PX = 1004;
const CARD_HEIGHT_PX = 634;
const CARD_WIDTH_MM = 85.6;
const CARD_HEIGHT_MM = 53.98;
const PRINT_SCALE = 4;
const JPEG_QUALITY = 0.92;
const XLINK_NS = 'http://www.w3.org/1999/xlink';

async function exportInsuranceCards(output, personKey) {
    const root = document.getElementById('insurance-cards-print');

    if (! root) {
        throw new Error('Insurance card print root is missing.');
    }

    if (typeof html2canvas !== 'function' || ! window.jspdf?.jsPDF) {
        throw new Error('PDF libraries are not loaded.');
    }

    const pages = collectPrintPages(root, personKey);

    if (pages.length === 0) {
        throw new Error('No insurance cards match the print selection.');
    }

    await inlineCardPhotos(root);
    await document.fonts.ready;
    await waitForPrintImages(root);

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({
        unit: 'mm',
        format: [CARD_WIDTH_MM, CARD_HEIGHT_MM],
        orientation: 'landscape',
        compress: true,
    });

    try {
        for (const [index, page] of pages.entries()) {
            if (index > 0) {
                pdf.addPage([CARD_WIDTH_MM, CARD_HEIGHT_MM], 'landscape');
            }

            const canvas = await captureCard(page);
            pdf.addImage(
                canvas.toDataURL('image/jpeg', JPEG_QUALITY),
                'JPEG',
                0,
                0,
                CARD_WIDTH_MM,
                CARD_HEIGHT_MM,
                undefined,
                'MEDIUM',
            );
        }
    } finally {
        root.replaceChildren();
    }

    const filename = (root.dataset.filename || 'insurance-cards') + '.pdf';

    if (output === 'print') {
        const url = URL.createObjectURL(pdf.output('blob'));
        let frame = document.getElementById('insurance-cards-print-frame');

        if (! frame) {
            frame = document.createElement('iframe');
            frame.id = 'insurance-cards-print-frame';
            frame.setAttribute('aria-hidden', 'true');
            frame.style.position = 'fixed';
            frame.style.right = '0';
            frame.style.bottom = '0';
            frame.style.width = '0';
            frame.style.height = '0';
            frame.style.border = '0';
            document.body.appendChild(frame);
        }

        frame.src = url;
        frame.onload = () => {
            frame.contentWindow?.focus();
            frame.contentWindow?.print();
        };

        return;
    }

    pdf.save(filename);
}

function collectPrintPages(host, personKey) {
    const preview = document.querySelector('.employee-insurance-cards--preview');

    if (! preview) {
        throw new Error('Insurance card preview is missing.');
    }

    const sources = [...preview.querySelectorAll('.employee-id-card')]
        .filter((page) => ! personKey || page.dataset.cardPerson === personKey);

    host.replaceChildren();

    return sources.map((page) => {
        const clone = page.cloneNode(true);
        clone.style.position = 'relative';
        clone.style.top = '0';
        clone.style.left = '0';
        clone.style.transform = 'none';
        clone.querySelectorAll('img').forEach((image) => {
            image.loading = 'eager';
            image.decoding = 'sync';
        });
        host.appendChild(clone);

        return clone;
    });
}

async function inlineCardPhotos(root) {
    await Promise.all([
        ...[...root.querySelectorAll('img')].map(inlineHtmlPhoto),
        ...[...root.querySelectorAll('image')].map(inlineSvgPhoto),
    ]);
}

async function inlineHtmlPhoto(image) {
    const source = image.currentSrc || image.getAttribute('src') || '';

    if (! shouldInlinePhoto(source)) {
        return;
    }

    try {
        image.src = await urlToDataUri(source);
    } catch (error) {
        console.warn('Insurance card photo could not be inlined.', error);
    }
}

async function inlineSvgPhoto(image) {
    const source = svgImageHref(image);

    if (! shouldInlinePhoto(source)) {
        return;
    }

    try {
        setSvgImageHref(image, await urlToDataUri(source));
    } catch (error) {
        console.warn('Insurance card SVG photo could not be inlined.', error);
    }
}

function shouldInlinePhoto(source) {
    return Boolean(source) && ! source.startsWith('data:') && ! source.startsWith('blob:');
}

function svgImageHref(image) {
    return image.getAttribute('href')
        || image.getAttributeNS(XLINK_NS, 'href')
        || '';
}

function setSvgImageHref(image, href) {
    image.setAttribute('href', href);
    image.setAttributeNS(XLINK_NS, 'href', href);
}

async function urlToDataUri(url) {
    const response = await fetch(url, { credentials: 'include' });

    if (! response.ok) {
        throw new Error('Photo fetch failed.');
    }

    const blob = await response.blob();

    if (blob.type && ! blob.type.startsWith('image/')) {
        throw new Error('Photo response is not an image.');
    }

    return await blobToDataUri(blob);
}

function blobToDataUri(blob) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = () => resolve(String(reader.result));
        reader.onerror = reject;
        reader.readAsDataURL(blob);
    });
}

async function waitForPrintImages(root) {
    await Promise.all([...root.querySelectorAll('img')].map((image) => {
        if (image.complete && image.naturalWidth) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            image.addEventListener('load', resolve, { once: true });
            image.addEventListener('error', resolve, { once: true });
        });
    }));
}

async function captureCard(page) {
    const inlineSvg = page.querySelector('.employee-id-card__canvas > svg');
    const artImage = page.querySelector('img.employee-id-card__art');
    const hasHtmlFields = page.querySelector('.employee-id-card__data');

    if (inlineSvg && ! hasHtmlFields) {
        return rasterizeSvgOnCardCanvas(inlineSvg);
    }

    if (artImage && ! hasHtmlFields && ! inlineSvg) {
        return rasterizeHtmlImageOnCardCanvas(artImage);
    }

    return html2canvas(page, {
        scale: PRINT_SCALE,
        useCORS: true,
        backgroundColor: '#ffffff',
        logging: false,
        width: CARD_WIDTH_PX,
        height: CARD_HEIGHT_PX,
        windowWidth: CARD_WIDTH_PX,
    });
}

function createPrintCanvas() {
    const canvas = document.createElement('canvas');
    canvas.width = CARD_WIDTH_PX * PRINT_SCALE;
    canvas.height = CARD_HEIGHT_PX * PRINT_SCALE;
    const context = canvas.getContext('2d');
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, canvas.width, canvas.height);

    return { canvas, context };
}

async function rasterizeSvgOnCardCanvas(svg) {
    const clone = svg.cloneNode(true);
    await Promise.all([...clone.querySelectorAll('image')].map(inlineSvgPhoto));
    const size = readSvgSize(clone);
    clone.setAttribute('width', String(size.width));
    clone.setAttribute('height', String(size.height));
    const xml = new XMLSerializer().serializeToString(clone);
    const url = URL.createObjectURL(new Blob([xml], { type: 'image/svg+xml;charset=utf-8' }));

    try {
        const image = await loadImage(url);
        const { canvas, context } = createPrintCanvas();
        const width = size.width * PRINT_SCALE;
        const height = size.height * PRINT_SCALE;
        const x = (canvas.width - width) / 2;
        const y = (canvas.height - height) / 2;
        context.drawImage(image, x, y, width, height);

        return canvas;
    } finally {
        URL.revokeObjectURL(url);
    }
}

async function rasterizeHtmlImageOnCardCanvas(image) {
    await loadImage(image.currentSrc || image.src);
    const { canvas, context } = createPrintCanvas();
    context.drawImage(image, 0, 0, canvas.width, canvas.height);

    return canvas;
}

function readSvgSize(svg) {
    const parts = (svg.getAttribute('viewBox') || '').trim().split(/[\s,]+/).map(Number);

    return {
        width: parts[2] || parseFloat(svg.getAttribute('width')) || CARD_WIDTH_PX,
        height: parts[3] || parseFloat(svg.getAttribute('height')) || CARD_HEIGHT_PX,
    };
}

function loadImage(source) {
    return new Promise((resolve, reject) => {
        const image = source instanceof HTMLImageElement && source.complete && source.naturalWidth
            ? source
            : new Image();

        if (image === source) {
            resolve(image);

            return;
        }

        image.onload = () => resolve(image);
        image.onerror = reject;
        image.src = source;
    });
}

window.exportInsuranceCards = exportInsuranceCards;
