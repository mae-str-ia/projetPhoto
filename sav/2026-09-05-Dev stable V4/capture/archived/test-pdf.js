const fs = require('fs');
const path = require('path');
const { PDFDocument } = require('pdf-lib');
const { createCanvas, loadImage } = require('canvas');

const PHOTO_PNG = '../data/screenshots/page-006.png';
const TEXT_PNG = '../data/pdf/texte-hq-7.png';
const OUTPUT_PDF = '../test-planche-6.pdf';

// 300 DPI, 24cm x 16cm
const pxPerMm = 300 / 25.4;
const pageWidthMm = 24 * 10;
const pageHeightMm = 16 * 10;
const pageWidthPx = Math.round(pageWidthMm * pxPerMm);
const pageHeightPx = Math.round(pageHeightMm * pxPerMm);

async function resizeImageToPageDimensions(imagePath, isPhoto = true) {
  const img = await loadImage(imagePath);
  const canvas = createCanvas(pageWidthPx, pageHeightPx);
  const ctx = canvas.getContext('2d');

  // White background
  ctx.fillStyle = 'white';
  ctx.fillRect(0, 0, pageWidthPx, pageHeightPx);

  // Draw image centered
  const scale = Math.min(pageWidthPx / img.width, pageHeightPx / img.height);
  const scaledW = img.width * scale;
  const scaledH = img.height * scale;
  const x = (pageWidthPx - scaledW) / 2;
  const y = (pageHeightPx - scaledH) / 2;

  ctx.drawImage(img, x, y, scaledW, scaledH);
  return canvas.toBuffer('image/png');
}

async function createTestPDF() {
  try {
    console.log('Creating test PDF with planche 6...');
    console.log(`Target size: ${pageWidthPx}×${pageHeightPx}px (24cm×16cm @ 300 DPI)\n`);

    const pdfDoc = await PDFDocument.create();

    // Page 1: Photo (page 6)
    if (fs.existsSync(PHOTO_PNG)) {
      console.log('Processing photo page...');
      const photoBuffer = await resizeImageToPageDimensions(PHOTO_PNG, true);
      const photoImage = await pdfDoc.embedPng(photoBuffer);
      const { width, height } = photoImage.scale(1);
      const page = pdfDoc.addPage([width, height]);
      page.drawImage(photoImage, {
        x: 0,
        y: 0,
        width: width,
        height: height
      });
      console.log(`  ✓ Photo page added (${(photoBuffer.length / 1024 / 1024).toFixed(2)}MB)`);
    } else {
      console.error(`  ✗ Photo not found: ${PHOTO_PNG}`);
    }

    // Page 2: Text (page 7)
    if (fs.existsSync(TEXT_PNG)) {
      console.log('Processing text page...');
      const textBuffer = await resizeImageToPageDimensions(TEXT_PNG, false);
      const textImage = await pdfDoc.embedPng(textBuffer);
      const { width, height } = textImage.scale(1);
      const page = pdfDoc.addPage([width, height]);
      page.drawImage(textImage, {
        x: 0,
        y: 0,
        width: width,
        height: height
      });
      console.log(`  ✓ Text page added (${(textBuffer.length / 1024 / 1024).toFixed(2)}MB)`);
    } else {
      console.error(`  ✗ Text not found: ${TEXT_PNG}`);
    }

    // Save PDF
    console.log('\nSaving PDF...');
    const pdfBytes = await pdfDoc.save();
    fs.writeFileSync(OUTPUT_PDF, pdfBytes);

    const fileSize = fs.statSync(OUTPUT_PDF).size;
    console.log(`\n✓ Test PDF created: ${OUTPUT_PDF}`);
    console.log(`  File size: ${(fileSize / 1024 / 1024).toFixed(1)} MB`);
    console.log(`  Pages: ${pdfDoc.getPageCount()}`);
  } catch (error) {
    console.error('Error:', error);
    process.exit(1);
  }
}

createTestPDF();
