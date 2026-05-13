/**
 * PDF Viewer - renders a full PDF page into a canvas using PDF.js
 * One PDF page = one text page of the book (portrait format)
 */
const PdfViewer = {
    _pdf: null,

    /**
     * Render a PDF page into a canvas, fitting the container
     * @param {HTMLCanvasElement} canvas
     * @param {number} pdfPage - 1-based PDF page number
     */
    async render(canvas, pdfPage) {
        try {
            const pdf = await this._getDoc();
            const page = await pdf.getPage(pdfPage);

            const container = canvas.parentElement;
            const containerW = container.clientWidth || 500;
            const containerH = container.clientHeight || 600;

            const viewport = page.getViewport({ scale: 1 });
            const scale = Math.min(containerW / viewport.width, containerH / viewport.height);
            const scaledViewport = page.getViewport({ scale });

            canvas.width = scaledViewport.width;
            canvas.height = scaledViewport.height;

            await page.render({
                canvasContext: canvas.getContext('2d'),
                viewport: scaledViewport,
            }).promise;

        } catch (err) {
            console.error('PDF render error:', err);
            const ctx = canvas.getContext('2d');
            canvas.width = 300;
            canvas.height = 400;
            ctx.fillStyle = '#f5f5f5';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.fillStyle = '#999';
            ctx.font = '13px sans-serif';
            ctx.fillText('Erreur: ' + err.message, 10, 30);
        }
    },

    async _getDoc() {
        if (this._pdf) return this._pdf;
        this._pdf = await pdfjsLib.getDocument(BASE_URL + '/pdf/source.pdf').promise;
        return this._pdf;
    }
};

window.PdfViewer = PdfViewer;
