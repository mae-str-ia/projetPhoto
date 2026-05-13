const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const BOOK_DATA = '../data/book.json';
const OUTPUT_DIR = '../data/screenshots-test-10';
const VIEW_URL = 'http://localhost:8081/?page=view';
const MAX_PAGES = 10;
const TIMEOUT = 30000;

async function capturePages() {
  const startTime = Date.now();
  const bookData = JSON.parse(fs.readFileSync(BOOK_DATA, 'utf8'));
  const photoPages = bookData.pages.filter(p => p.type === 'photo').slice(0, MAX_PAGES);
  const props = bookData.properties || {};

  const pxPerMm = 300 / 25.4;
  const dims = props.pageDimensions || { widthCm: 24, heightCm: 16 };
  const margins = props.photoPageMargins || { topCm: 1, rightCm: 1, bottomCm: 1, leftCm: 1 };
  const binding = parseFloat(props.bindingCm || 2);

  const pageWidthMm = parseFloat(dims.widthCm) * 10;
  const pageHeightMm = parseFloat(dims.heightCm) * 10;
  const contentWidthMm = pageWidthMm - (parseFloat(margins.leftCm) * 10) - (parseFloat(margins.rightCm) * 10 + binding * 10);
  const contentHeightMm = pageHeightMm - (parseFloat(margins.topCm) * 10) - (parseFloat(margins.bottomCm) * 10);

  const viewportWidth = Math.round(contentWidthMm * pxPerMm);
  const viewportHeight = Math.round(contentHeightMm * pxPerMm);

  console.log(`Testing ${photoPages.length} pages at 300 DPI (${viewportWidth}×${viewportHeight}px)\n`);

  if (!fs.existsSync(OUTPUT_DIR)) fs.mkdirSync(OUTPUT_DIR, { recursive: true });

  let browser;
  try {
    browser = await puppeteer.launch({ headless: 'new' });
    const page = await browser.newPage();
    await page.setViewport({ width: viewportWidth, height: viewportHeight, deviceScaleFactor: 1 });

    for (const photoPage of photoPages) {
      const pageNum = photoPage.pageNumber;
      const outputFile = path.join(OUTPUT_DIR, `page-${String(pageNum).padStart(3, '0')}.png`);

      try {
        console.log(`Page ${pageNum}...`);
        const url = `${VIEW_URL}&num=${pageNum}`;
        await page.goto(url, { waitUntil: 'networkidle2', timeout: TIMEOUT });
        await new Promise(resolve => setTimeout(resolve, 1000));
        await page.screenshot({ path: outputFile, fullPage: false });
        const size = fs.statSync(outputFile).size;
        console.log(`  ✓ Saved (${(size/1024/1024).toFixed(1)} MB)`);
      } catch (error) {
        console.error(`  ✗ ${error.message}`);
      }
    }

    await browser.close();
    const elapsed = ((Date.now() - startTime) / 1000).toFixed(1);
    const perPage = (elapsed / photoPages.length).toFixed(1);
    console.log(`\n✓ Done in ${elapsed}s (${perPage}s/page)`);
  } catch (error) {
    console.error('Error:', error);
  }
}

capturePages();
