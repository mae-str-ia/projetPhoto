const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const BOOK_DATA = '../data/book.json';
const OUTPUT_DIR = '../data/screenshots-test';
const VIEW_URL = 'http://localhost:8081/?page=view';
const TIMEOUT = 30000;
const PAGES_TO_CAPTURE = [6]; // Capture page 6 only for PDF test

async function capturePages() {
  const startTime = Date.now();

  // Read book.json to get photo pages
  const bookData = JSON.parse(fs.readFileSync(BOOK_DATA, 'utf8'));
  const photoPages = bookData.pages.filter(p => p.type === 'photo' && PAGES_TO_CAPTURE.includes(p.pageNumber));
  const props = bookData.properties || {};

  // Calculate page dimensions at 300 DPI
  // 300 DPI = 300 pixels/inch = 300/25.4 = 11.81 px/mm
  const pxPerMm = 300 / 25.4;
  const dims = props.pageDimensions || { widthCm: 24, heightCm: 16 };

  const pageWidthMm = parseFloat(dims.widthCm) * 10;
  const pageHeightMm = parseFloat(dims.heightCm) * 10;

  // Full page dimensions (with margins) - all pages same size
  const fullViewportWidth = Math.round(pageWidthMm * pxPerMm);
  const fullViewportHeight = Math.round(pageHeightMm * pxPerMm);

  console.log(`Capturing ${photoPages.length} photo pages (test mode)...`)
  console.log(`Full page size: ${fullViewportWidth}×${fullViewportHeight}px (includes margins)\n`);

  // Create output directory
  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

  let browser;
  try {
    // Launch browser
    browser = await puppeteer.launch({
      headless: 'new',
      args: [
        '--disable-gpu',
        '--no-first-run',
        '--no-default-browser-check'
      ]
    });

    const page = await browser.newPage();

    // Capture each photo page
    for (const photoPage of photoPages) {
      const pageNum = photoPage.pageNumber;
      const outputFile = path.join(OUTPUT_DIR, `page-${String(pageNum).padStart(3, '0')}.png`);

      try {
        console.log(`\nCapturing page ${pageNum}...`);

        // Set viewport to full page size with margins
        await page.setViewport({
          width: fullViewportWidth,
          height: fullViewportHeight,
          deviceScaleFactor: 1
        });

        const url = `${VIEW_URL}&num=${pageNum}`;
        console.log(`  URL: ${url}`);
        console.log('  Navigating...');
        await page.goto(url, { waitUntil: 'networkidle2', timeout: TIMEOUT });

        // Wait for render and images to load
        console.log('  Waiting for render...');
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Check if images loaded
        const imgCount = await page.evaluate(() => {
          const imgs = document.querySelectorAll('.v-photo-clip img');
          return { total: imgs.length, loaded: Array.from(imgs).filter(i => i.complete).length };
        });
        console.log(`  Images: ${imgCount.loaded}/${imgCount.total} loaded`);

        // Wait longer if images still loading
        if (imgCount.loaded < imgCount.total) {
          console.log('  Waiting for images...');
          await new Promise(resolve => setTimeout(resolve, 3000));
        }

        // Check container dimensions
        const dims = await page.evaluate(() => {
          const container = document.getElementById('viewContainer');
          const rect = container ? container.getBoundingClientRect() : {};
          return {
            viewport: { width: window.innerWidth, height: window.innerHeight },
            container: { width: rect.width, height: rect.height, x: rect.left, y: rect.top }
          };
        });
        console.log(`  Container: ${dims.container.width.toFixed(0)}×${dims.container.height.toFixed(0)}px at (${dims.container.x.toFixed(0)},${dims.container.y.toFixed(0)}), viewport=${dims.viewport.width}×${dims.viewport.height}`);

        // Take screenshot
        console.log('  Taking screenshot...');
        await page.screenshot({
          path: outputFile,
          fullPage: false
        });

        const fileSize = fs.statSync(outputFile).size;
        console.log(`  ✓ Saved ${outputFile} (${(fileSize / 1024 / 1024).toFixed(2)} MB)`);
      } catch (error) {
        console.error(`  ✗ Error: ${error.message}`);
      }
    }

    await browser.close();
    const endTime = Date.now();
    const elapsed = ((endTime - startTime) / 1000).toFixed(1);
    const avgPerPage = (elapsed / photoPages.length).toFixed(1);
    console.log(`\n✓ Test capture complete in ${elapsed}s (${avgPerPage}s per page)`);
  } catch (error) {
    console.error('Fatal error:', error);
    process.exit(1);
  }
}

capturePages();
