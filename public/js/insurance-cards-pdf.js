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

    await ensurePdfLibraries(root);

    if (typeof html2canvas !== 'function' || ! window.jspdf?.jsPDF) {
        throw new Error('PDF libraries are not loaded.');
    }

    const pages = await collectPrintPages(root, personKey);

    if (pages.length === 0) {
        throw new Error('No insurance cards match the print selection.');
    }

    await inlineCardFont(root);
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

async function collectPrintPages(host, personKey) {
    const html = await fetchPrintPackHtml(personKey);

    host.innerHTML = html;

    return [...host.querySelectorAll('.employee-id-card')]
        .filter((page) => ! personKey || page.dataset.cardPerson === personKey);
}

async function fetchPrintPackHtml(personKey) {
    const component = livewireComponent();

    if (! component || typeof component.insuranceCardPrintHtml !== 'function') {
        throw new Error('Insurance card print pack is unavailable.');
    }

    return await component.insuranceCardPrintHtml(personKey || null);
}

function livewireComponent() {
    const root = document.getElementById('insurance-cards-print');
    const el = root?.closest('[wire\\:id]');
    const id = el?.getAttribute('wire:id');

    return id && window.Livewire ? window.Livewire.find(id) : null;
}

async function ensurePdfLibraries(root) {
    if (typeof html2canvas === 'function' && window.jspdf?.jsPDF) {
        return;
    }

    await Promise.all([
        loadScript(root.dataset.html2canvasUrl),
        loadScript(root.dataset.jspdfUrl),
    ]);
}

function loadScript(src) {
    if (! src) {
        return Promise.reject(new Error('PDF library URL is missing.'));
    }

    const existing = document.querySelector(`script[src="${src}"]`);

    if (existing) {
        return existing.dataset.loaded === 'true'
            ? Promise.resolve()
            : new Promise((resolve, reject) => {
                existing.addEventListener('load', resolve, { once: true });
                existing.addEventListener('error', reject, { once: true });
            });
    }

    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = () => {
            script.dataset.loaded = 'true';
            resolve();
        };
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

async function inlineCardFont(root) {
    const fontUrl = root.dataset.fontUrl;

    if (! fontUrl) {
        return;
    }

    const dataUri = await fetchAsDataUri(fontUrl);
    const rule = cardFontFaceRule(dataUri);

    await Promise.all([
        registerCardFont('Somar Sans', dataUri),
        registerCardFont('SomarSans-SemiBold', dataUri),
    ]);

    await document.fonts.load("600 24px 'Somar Sans'");
    await document.fonts.load('600 24px SomarSans-SemiBold');

    root.querySelectorAll('style').forEach((style) => {
        style.textContent = rule + style.textContent;
    });
}

function cardFontFaceRule(dataUri) {
    return "@font-face{font-family:'Somar Sans';src:url('"+dataUri+"') format('truetype');font-weight:600;font-style:normal}"
        +"@font-face{font-family:SomarSans-SemiBold;src:url('"+dataUri+"') format('truetype');font-weight:600;font-style:normal}";
}

async function registerCardFont(family, dataUri) {
    const face = new FontFace(family, "url('"+dataUri+"')", {
        weight: '600',
        style: 'normal',
    });

    document.fonts.add(await face.load());
}

async function inlineCardPhotos(root) {
    await Promise.all([
        ...[...root.querySelectorAll('img')].map(inlineHtmlPhoto),
        ...[...root.querySelectorAll('image')].map(inlineSvgPhoto),
    ]);
}

async function inlineHtmlPhoto(image) {
    const source = image.currentSrc || image.getAttribute('src') || '';

    if (! shouldInlineAsset(source)) {
        return;
    }

    try {
        image.src = await fetchAsDataUri(source);
    } catch (error) {
        console.warn('Insurance card photo could not be inlined.', error);
    }
}

async function inlineSvgPhoto(image) {
    const source = svgImageHref(image);

    if (! shouldInlineAsset(source)) {
        return;
    }

    try {
        setSvgImageHref(image, await fetchAsDataUri(source));
    } catch (error) {
        console.warn('Insurance card SVG photo could not be inlined.', error);
    }
}

function shouldInlineAsset(source) {
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

async function fetchAsDataUri(url) {
    const response = await fetch(url, { credentials: 'include' });

    if (! response.ok) {
        throw new Error('Asset fetch failed.');
    }

    return await blobToDataUri(await response.blob());
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
