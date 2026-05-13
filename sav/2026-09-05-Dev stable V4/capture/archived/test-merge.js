const fs = require('fs');
const path = require('path');
const { PDFDocument } = require('pdf-lib');

const SCREENSHOTS_DIR = '../data/screenshots-test';
const TEXT_PDF_FILE = '../data/pdf/texte.pdf';
const OUTPUT_PDF = '../test-final.pdf';

async function testMerge() {
  try {
    console.log('Creating test PDF with merged pages...\n');
    const pdfDoc = await PDFDocument.create();

    // Load text PDF for direct page copying
    console.log('Loading text PDF...');
    const textPdfBuffer = fs.readFileSync(TEXT_PDF_FILE);
    const textPdfDoc = await PDFDocument.load(textPdfBuffer);
    const textPageCount = textPdfDoc.getPageCount();
    console.log(`  Text PDF has ${textPageCount} pages`);

    // Add photo page 6
    const photoFile = path.join(SCREENSHOTS_DIR, 'page-006.png');
    if (fs.existsSync(photoFile)) {
      console.log('Adding photo page 6...');
      const photoBuffer = fs.readFileSync(photoFile);
      const photoImage = await pdfDoc.embedPng(photoBuffer);
      const { width, height } = photoImage.scale(1);
      console.log(`  Photo size: ${width}×${height}px (300 DPI quality)`);

      const page = pdfDoc.addPage([width, height]);
      page.drawImage(photoImage, { x: 0, y: 0, width, height });
      console.log(`  ✓ Photo page added (vectorial quality - apply 24% at print)`);
    }

    // Add text page 7 directly from PDF
    if (7 <= textPageCount) {
      console.log('Adding text page 7 from PDF...');
      const [copiedPage] = await pdfDoc.copyPages(textPdfDoc, [6]); // 0-indexed
      pdfDoc.addPage(copiedPage);
      console.log(`  ✓ Text page added (vectorial text - fully sélectionnable)`);
    } else {
      console.warn(`  Warning: Text page 7 exceeds PDF page count (${textPageCount})`);
    }

    // Save PDF
    console.log('\nSaving PDF...');
    const pdfBytes = await pdfDoc.save();
    fs.writeFileSync(OUTPUT_PDF, pdfBytes);

    const fileSize = fs.statSync(OUTPUT_PDF).size;
    console.log(`\n✓ Test PDF created: ${OUTPUT_PDF}`);
    console.log(`  File size: ${(fileSize / 1024 / 1024).toFixed(2)} MB`);
    console.log(`  Pages: ${pdfDoc.getPageCount()}`);
  } catch (error) {
    console.error('Error:', error);
    process.exit(1);
  }
}

testMerge();
