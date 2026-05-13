const fs = require('fs');
const path = require('path');
const { PDFDocument, PDFPage } = require('pdf-lib');

const BOOK_DATA = '../data/book.json';
const TEXT_PDF = '../data/pdf/texte.processed.pdf';
const SCREENSHOTS_DIR = '../data/screenshots';
const OUTPUT_PDF = '../livre.print.pdf';

async function mergePDF() {
  try {
    console.log('Reading book data...');
    const bookData = JSON.parse(fs.readFileSync(BOOK_DATA, 'utf8'));

    // Load text PDF if it exists
    let textPdfDoc = null;
    if (fs.existsSync(TEXT_PDF)) {
      console.log(`Loading text PDF: ${TEXT_PDF}`);
      const textPdfBuffer = fs.readFileSync(TEXT_PDF);
      textPdfDoc = await PDFDocument.load(textPdfBuffer);
    } else {
      console.log(`Text PDF not found: ${TEXT_PDF}`);
    }

    // Create new PDF
    const pdfDoc = await PDFDocument.create();

    let textPageIndex = 1;
    let pageIndex = 0;

    // Process each page in the book
    for (const pageData of bookData.pages) {
      pageIndex++;
      if (pageIndex % 20 === 0) {
        console.log(`Processing page ${pageIndex}/${bookData.pages.length}...`);
      }

      if (pageData.type === 'text') {
        // Add text page from markdown PDF
        if (textPdfDoc && textPageIndex <= textPdfDoc.getPageCount()) {
          const [textPage] = await pdfDoc.copyPages(textPdfDoc, [textPageIndex - 1]);
          pdfDoc.addPage(textPage);
          textPageIndex++;
        }
      } else if (pageData.type === 'photo') {
        // Add photo page from screenshot
        const screenshotFile = path.join(
          SCREENSHOTS_DIR,
          `page-${String(pageData.pageNumber).padStart(3, '0')}.png`
        );

        if (!fs.existsSync(screenshotFile)) {
          // Create blank page if screenshot not found
          const page = pdfDoc.addPage([3071, 1890]);
          continue;
        }

        // Read and embed image
        const imageBuffer = fs.readFileSync(screenshotFile);
        const image = await pdfDoc.embedPng(imageBuffer);
        const { width, height } = image.scale(1);

        // Create page with image dimensions
        const page = pdfDoc.addPage([width, height]);
        page.drawImage(image, {
          x: 0,
          y: 0,
          width: width,
          height: height
        });
      }
    }

    // Save PDF
    const pdfBytes = await pdfDoc.save();
    fs.writeFileSync(OUTPUT_PDF, pdfBytes);

    console.log(`\n✓ PDF created: ${OUTPUT_PDF}`);
    const fileSize = fs.statSync(OUTPUT_PDF).size;
    console.log(`  File size: ${(fileSize / 1024 / 1024).toFixed(1)} MB`);
    console.log(`  Pages: ${pdfDoc.getPageCount()}`);
  } catch (error) {
    console.error('Error:', error);
    process.exit(1);
  }
}

mergePDF();
