const fs = require('fs');
const path = require('path');
const { PDFDocument } = require('pdf-lib');

const BOOK_DATA = '../data/book.json';
const PAGE_MAP_FILE = '../data/markdown/build/page-map.json';
const TEXT_PDF_FILE = '../data/outputs/texte.pdf';
const SCREENSHOTS_DIR = '../data/cache/screenshots';
const OUTPUT_PDF = '../livre.print.pdf';

// Page dimensions: 24cm × 16cm at 300 DPI
// 1cm = 1/2.54 inches = (1/2.54) × 300 pixels/inches = 118.11 pixels
// So 24cm × 16cm = 2834.65 × 1889.76 pixels
// In PDF points (where 72 points = 1 inch at baseline):
// 24cm = 24 × (1/2.54) × 300 points = 2834.65 points
const PAGE_WIDTH_CM = 24;
const PAGE_HEIGHT_CM = 16;
const DPI = 300;
const CM_TO_INCHES = 1 / 2.54;
const PAGE_WIDTH_POINTS = PAGE_WIDTH_CM * CM_TO_INCHES * DPI;
const PAGE_HEIGHT_POINTS = PAGE_HEIGHT_CM * CM_TO_INCHES * DPI;

async function mergePDF() {
  try {
    console.log('Loading book structure...');
    const bookData = JSON.parse(fs.readFileSync(BOOK_DATA, 'utf8'));
    const pageMapData = JSON.parse(fs.readFileSync(PAGE_MAP_FILE, 'utf8'));

    // Load text PDF
    console.log('Loading text PDF...');
    const textPdfBuffer = fs.readFileSync(TEXT_PDF_FILE);
    const textPdfDoc = await PDFDocument.load(textPdfBuffer);
    const textPageCount = textPdfDoc.getPageCount();
    console.log(`  Text PDF has ${textPageCount} pages`);

    // Create new PDF document
    const pdfDoc = await PDFDocument.create();

    const sourceToFinal = pageMapData.sourceToFinal;
    const sectionByPage = pageMapData.sectionByPage;

    // Map final page number to text page index
    const finalPageToTextPage = {};
    let maxTextFinalPage = 0;
    Object.entries(sourceToFinal).forEach(([textPage, finalPage]) => {
      finalPageToTextPage[finalPage] = parseInt(textPage);
      maxTextFinalPage = Math.max(maxTextFinalPage, finalPage);
    });

    console.log(`\nText pages span to final page ${maxTextFinalPage}`);
    console.log('Building final PDF...');
    let processedPages = 0;
    let skippedPhotoPagesCount = 0;

    // Process each page in the book
    for (const pageData of bookData.pages) {
      const pageNum = pageData.pageNumber;

      // Skip pages beyond where text ends
      if (pageNum > maxTextFinalPage) {
        if (pageData.type === 'photo') {
          skippedPhotoPagesCount++;
        }
        continue;
      }

      processedPages++;

      if (processedPages % 50 === 0) {
        console.log(`  Processing page ${pageNum}/${maxTextFinalPage}...`);
      }

      if (pageData.type === 'photo') {
        // Add photo page from screenshot
        const screenshotFile = path.join(
          SCREENSHOTS_DIR,
          `page-${String(pageNum).padStart(3, '0')}.png`
        );

        if (!fs.existsSync(screenshotFile)) {
          console.warn(`  Warning: Screenshot not found for page ${pageNum}: ${screenshotFile}`);
          continue;
        }

        try {
          const imageBuffer = fs.readFileSync(screenshotFile);
          const image = await pdfDoc.embedPng(imageBuffer);

          // Create page with exact book dimensions
          const page = pdfDoc.addPage([PAGE_WIDTH_POINTS, PAGE_HEIGHT_POINTS]);
          // Scale image to fill entire page (preserving aspect ratio)
          page.drawImage(image, {
            x: 0,
            y: 0,
            width: PAGE_WIDTH_POINTS,
            height: PAGE_HEIGHT_POINTS
          });
        } catch (error) {
          console.warn(`  Error adding photo page ${pageNum}: ${error.message}`);
        }

      } else if (pageData.type === 'text') {
        // Find text page index from final page number
        const textPageIndex = finalPageToTextPage[pageNum];

        if (!textPageIndex) {
          console.warn(`  Warning: No text page mapping for final page ${pageNum}`);
          continue;
        }

        if (textPageIndex > textPageCount) {
          console.warn(`  Warning: Text page ${textPageIndex} exceeds PDF page count (${textPageCount})`);
          continue;
        }

        try {
          // Copy page from text PDF (0-indexed)
          const [copiedPage] = await pdfDoc.copyPages(textPdfDoc, [textPageIndex - 1]);
          // Scale text page to 300 DPI dimensions (text PDF is 680.31×453.54 at 72 DPI, scale to 300 DPI)
          const scaleRatio = PAGE_WIDTH_POINTS / 680.31;
          copiedPage.scale(scaleRatio, scaleRatio);
          pdfDoc.addPage(copiedPage);
        } catch (error) {
          console.warn(`  Error adding text page ${textPageIndex}: ${error.message}`);
        }
      }
    }

    if (skippedPhotoPagesCount > 0) {
      console.warn(`\n⚠ Warning: Skipped ${skippedPhotoPagesCount} photo pages beyond text content (book.json may be out of date)`);
    }

    // Save PDF
    console.log('\nSaving PDF...');
    const pdfBytes = await pdfDoc.save();
    fs.writeFileSync(OUTPUT_PDF, pdfBytes);

    console.log(`\n✓ PDF created: ${OUTPUT_PDF}`);
    const fileSize = fs.statSync(OUTPUT_PDF).size;
    console.log(`  File size: ${(fileSize / 1024 / 1024).toFixed(1)} MB`);
    console.log(`  Pages: ${pdfDoc.getPageCount()}`);
    console.log(`\nBook structure:`);
    console.log(`  Photo pages: ${bookData.pages.filter(p => p.type === 'photo').length}`);
    console.log(`  Text pages: ${bookData.pages.filter(p => p.type === 'text').length}`);

  } catch (error) {
    console.error('Error:', error);
    process.exit(1);
  }
}

mergePDF();
