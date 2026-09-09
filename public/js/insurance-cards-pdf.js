async function exportInsuranceCards(output, personKey) {
    const root = document.getElementById('insurance-cards-print');

    if (! root) {
        throw new Error('Insurance card print root is missing.');
    }

    if (typeof html2canvas !== 'function' || ! window.jspdf?.jsPDF) {
        throw new Error('PDF libraries are not loaded.');
    }

    await document.fonts.ready;

    await Promise.all([...root.querySelectorAll('img')].map((image) => {
        if (image.complete) {
            return Promise.resolve();
        }

        return new Promise((resolve) => {
            image.addEventListener('load', resolve, { once: true });
            image.addEventListener('error', resolve, { once: true });
        });
    }));

    const pages = [...root.querySelectorAll('.employee-id-card')]
        .filter((page) => ! personKey || page.dataset.cardPerson === personKey);

    if (pages.length === 0) {
        throw new Error('No insurance cards match the print selection.');
    }

    const cardWidthPx = 1004;
    const cardHeightPx = 634;
    const cardWidthMm = 85.6;
    const cardHeightMm = 53.98;
    const printScale = 4;

    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({
        unit: 'mm',
        format: [cardWidthMm, cardHeightMm],
        orientation: 'landscape',
        compress: true,
        hotfixes: ['px_scaling'],
    });

    for (const [index, page] of pages.entries()) {
        const canvas = await html2canvas(page, {
            scale: printScale,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff',
            width: cardWidthPx,
            height: cardHeightPx,
            windowWidth: cardWidthPx,
            windowHeight: cardHeightPx,
            imageTimeout: 0,
        });

        if (index > 0) {
            pdf.addPage([cardWidthMm, cardHeightMm], 'landscape');
        }

        pdf.addImage(
            canvas.toDataURL('image/png'),
            'PNG',
            0,
            0,
            cardWidthMm,
            cardHeightMm,
            `card-${index}`,
            'NONE',
        );
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

window.exportInsuranceCards = exportInsuranceCards;
