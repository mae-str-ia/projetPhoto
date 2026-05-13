const fs = require('fs');
const path = require('path');
const { PDFDocument } = require('pdf-lib');

const BOOK_DATA = '../data/book.json';
const TEXT_PDF_FILE = '../data/pdf/texte.pdf';
const SCREENSHOTS_DIR = '../data/screenshots';
const OUTPUT_PDF = '../livre.print.pdf';
const TEMP_DIR = '../data/temp-merged';

async function getPhotoImageBuffer(screenshotPath, pageData, bookProperties) {
  try {
    // Screenshots are now captured at full-page dimensions (2835×1890px)
    // Use them directly without adding borders
    return fs.readFileSync(screenshotPath);
  } catch (error) {
    console.error(`Error reading photo image: ${error.message}`);
    throw error;
  }
}

async function mergePDF() {
  try {
    console.log('Reading book data...');
    const bookData = JSON.parse(fs.readFileSync(BOOK_DATA, 'utf8'));
    const bookProperties = bookData.properties || {};

    // Create temp directory for merged images
    if (!fs.existsSync(TEMP_DIR)) {
      fs.mkdirSync(TEMP_DIR, { recursive: true });
    }

    // Load text PDF for direct page copying
    console.log('Loading text PDF...');
    const textPdfBuffer = fs.readFileSync(TEXT_PDF_FILE);
    const textPdfDoc = await PDFDocument.load(textPdfBuffer);
    const textPageCount = textPdfDoc.getPageCount();
    console.log(`  Text PDF has ${textPageCount} pages`);

    // Create new PDF document
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
        // Add text page directly from PDF (vectorial text, no rasterization)
        if (textPageIndex > textPageCount) {
          console.warn(`  Warning: Text page ${textPageIndex} exceeds PDF page count`);
          textPageIndex++;
          continue;
        }

        // Copy page from text PDF (0-indexed)
        const [copiedPage] = await pdfDoc.copyPages(textPdfDoc, [textPageIndex - 1]);
        pdfDoc.addPage(copiedPage);

        textPageIndex++;
      } else if (pageData.type === 'photo') {
        // Add photo page from screenshot (already captured at full-page dimensions)
        const screenshotFile = path.join(
          SCREENSHOTS_DIR,
          `page-${String(pageData.pageNumber).padStart(3, '0')}.png`
        );

        if (!fs.existsSync(screenshotFile)) {
          console.warn(`  Warning: Screenshot not found: ${screenshotFile}`);
          continue;
        }

        // Use screenshot directly (already full-page @ 2835×1890px = 24cm×16cm at 300 DPI)
        const imageBuffer = await getPhotoImageBuffer(screenshotFile, pageData, bookProperties);
        const image = await pdfDoc.embedPng(imageBuffer);
        const { width, height } = image.scale(1);

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
    console.log('\nSaving PDF...');
    const pdfBytes = await pdfDoc.save();
    fs.writeFileSync(OUTPUT_PDF, pdfBytes);

    // Clean up temp directory
    const tempFiles = fs.readdirSync(TEMP_DIR);
    tempFiles.forEach(f => fs.unlinkSync(path.join(TEMP_DIR, f)));

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
