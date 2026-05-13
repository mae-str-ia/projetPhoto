const fs = require('fs');
const path = require('path');
const { PDFDocument } = require('pdf-lib');

const PHOTO_PNG = '../data/screenshots/page-006.png';
const TEXT_PNG = '../data/pdf/texte-hq-7.png';
const OUTPUT_PDF = '../test-planche-6.pdf';

async function createTestPDF() {
  try {
    console.log('Creating test PDF with planche 6...');
    const pdfDoc = await PDFDocument.create();

    // Page 1: Photo (page 6)
    if (fs.existsSync(PHOTO_PNG)) {
      console.log('Adding photo page...');
      const photoBuffer = fs.readFileSync(PHOTO_PNG);
      const photoImage = await pdfDoc.embedPng(photoBuffer);
      const { width, height } = photoImage.scale(1);
      console.log(`  Photo size: ${width}×${height}px`);
      const page = pdfDoc.addPage([width, height]);
      page.drawImage(photoImage, {
        x: 0,
        y: 0,
        width: width,
        height: height
      });
      console.log(`  ✓ Photo page added`);
    }

    // Page 2: Text (page 7)
    if (fs.existsSync(TEXT_PNG)) {
      console.log('Adding text page...');
      const textBuffer = fs.readFileSync(TEXT_PNG);
      const textImage = await pdfDoc.embedPng(textBuffer);
      const { width, height } = textImage.scale(1);
      console.log(`  Text size: ${width}×${height}px`);
      const page = pdfDoc.addPage([width, height]);
      page.drawImage(textImage, {
        x: 0,
        y: 0,
        width: width,
        height: height
      });
      console.log(`  ✓ Text page added`);
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

createTestPDF();
