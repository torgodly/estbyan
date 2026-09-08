async function exportInsuranceCards(output) {
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

    const pages = [...root.querySelectorAll('.employee-id-card')];
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF({
        unit: 'px',
        format: [1004, 634],
        orientation: 'landscape',
        hotfixes: ['px_scaling'],
    });
    const pageWidth = pdf.internal.pageSize.getWidth();
    const pageHeight = pdf.internal.pageSize.getHeight();

    for (const [index, page] of pages.entries()) {
        const canvas = await html2canvas(page, {
            scale: 1,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff',
            width: 1004,
            height: 634,
        });

        if (index > 0) {
            pdf.addPage([1004, 634], 'landscape');
        }

        pdf.addImage(canvas.toDataURL('image/jpeg', 0.92), 'JPEG', 0, 0, pageWidth, pageHeight);
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
