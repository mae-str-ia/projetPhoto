const { PDFDocument } = require('pdf-lib');
const fs = require('fs');

async function fixPDFSize() {
  try {
    console.log('Loading PDF...');
    const pdfBuffer = fs.readFileSync('../livre.print.pdf');
    const pdfDoc = await PDFDocument.load(pdfBuffer);

    const pages = pdfDoc.getPages();
    console.log(`Rescaling ${pages.length} pages...`);

    // Scale factor: from 300 DPI (2835x1890 points) to 72 DPI (680x453 points)
    const scaleX = 680.31 / 2835;
    const scaleY = 453.54 / 1890;

    console.log(`Scale factor: ${scaleX.toFixed(4)} × ${scaleY.toFixed(4)}`);

    let processedPages = 0;
    for (const page of pages) {
      page.scale(scaleX, scaleY);
      processedPages++;

      if (processedPages % 50 === 0) {
        console.log(`  Processed ${processedPages}/${pages.length} pages...`);
      }
    }

    console.log('\nSaving rescaled PDF...');
    const pdfBytes = await pdfDoc.save();
    fs.writeFileSync('../livre.print.pdf', pdfBytes);

    console.log(`✓ PDF rescaled: ../livre.print.pdf`);
    const fileSize = fs.statSync('../livre.print.pdf').size;
    console.log(`  File size: ${(fileSize / 1024 / 1024).toFixed(1)} MB`);
    console.log(`  Pages: ${pdfDoc.getPageCount()}`);
    console.log(`  Display size: 24cm × 16cm`);

  } catch (error) {
    console.error('Error:', error);
    process.exit(1);
  }
}

fixPDFSize();
