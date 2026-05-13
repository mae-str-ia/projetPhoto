const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

// Load .env if it exists
const envPath = path.join(__dirname, '.env');
console.log(`[DEBUG] Looking for .env at: ${envPath}`);
if (fs.existsSync(envPath)) {
  console.log(`[DEBUG] Found .env, loading...`);
  const envContent = fs.readFileSync(envPath, 'utf8');
  envContent.split('\n').forEach(line => {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) return;
    const eqIndex = trimmed.indexOf('=');
    if (eqIndex === -1) return;
    const key = trimmed.substring(0, eqIndex);
    const value = trimmed.substring(eqIndex + 1);
    if (key && value) {
      process.env[key] = value;
      if (key === 'VIEW_URL') {
        console.log(`[DEBUG] Set VIEW_URL to: ${value}`);
      }
    }
  });
} else {
  console.log(`[DEBUG] .env not found at ${envPath}`);
}

const BOOK_DATA = path.join(__dirname, '../data/SYNC/book.json');
const OUTPUT_DIR = path.join(__dirname, '../data/cache/screenshots');
const VIEW_URL = process.env.VIEW_URL || 'http://127.0.0.1:8081/?page=view';
const SITE_PASSWORD = process.env.SITE_PASSWORD || null;
const TIMEOUT = 30000;

async function capturePages() {
  const startTime = Date.now();

  const bookData = JSON.parse(fs.readFileSync(BOOK_DATA, 'utf8'));
  const printablePages = Number(bookData.printablePages || bookData.totalPages || 0);
  const photoPages = bookData.pages.filter(p => p.type === 'photo' && (!printablePages || p.pageNumber <= printablePages));
  const ignoredPhotoPages = bookData.pages.filter(p => p.type === 'photo' && printablePages && p.pageNumber > printablePages);
  const props = bookData.properties || {};

  const pxPerMm = 300 / 25.4;
  const dims = props.pageDimensions || { widthCm: 24, heightCm: 16 };
  const rightMargins = props.photoPageMargins || { topCm: 1, rightCm: 1, bottomCm: 1, leftCm: 1 };
  const leftMargins = props.leftPageMargins || { topCm: 1, rightCm: 3, bottomCm: 1, leftCm: 1 };
  const binding = parseFloat(props.bindingCm || 2);

  const pageWidthMm = parseFloat(dims.widthCm) * 10;
  const pageHeightMm = parseFloat(dims.heightCm) * 10;

  // Full page dimensions (with margins) - all pages same size
  const fullPageWidthPx = Math.round(pageWidthMm * pxPerMm);
  const fullPageHeightPx = Math.round(pageHeightMm * pxPerMm);

  // Content dimensions (without margins) - for reference
  const rightContentWidthMm = pageWidthMm - (parseFloat(rightMargins.leftCm) * 10) - (parseFloat(rightMargins.rightCm) * 10 + binding * 10);
  const rightContentHeightMm = pageHeightMm - (parseFloat(rightMargins.topCm) * 10) - (parseFloat(rightMargins.bottomCm) * 10);
  const leftContentWidthMm = pageWidthMm - (parseFloat(leftMargins.leftCm) * 10) - (parseFloat(leftMargins.rightCm) * 10 + binding * 10);
  const leftContentHeightMm = pageHeightMm - (parseFloat(leftMargins.topCm) * 10) - (parseFloat(leftMargins.bottomCm) * 10);

  const rightContentWidth = Math.round(rightContentWidthMm * pxPerMm);
  const rightContentHeight = Math.round(rightContentHeightMm * pxPerMm);
  const leftContentWidth = Math.round(leftContentWidthMm * pxPerMm);
  const leftContentHeight = Math.round(leftContentHeightMm * pxPerMm);

  console.log(`Found ${photoPages.length} photo pages`)
  if (ignoredPhotoPages.length > 0) {
    console.log(`Ignoring ${ignoredPhotoPages.length} photo pages after printable page ${printablePages}`)
  }
  console.log(`Full page size: ${fullPageWidthPx}×${fullPageHeightPx}px (all pages same @ 300 DPI)`)
  console.log(`Right content: ${rightContentWidth}×${rightContentHeight}px, Left content: ${leftContentWidth}×${leftContentHeight}px`)
  console.log(`Estimated time: ${(photoPages.length * 4 / 60).toFixed(1)} minutes\n`);

  if (!fs.existsSync(OUTPUT_DIR)) {
    fs.mkdirSync(OUTPUT_DIR, { recursive: true });
  }

  let browser;
  try {
    browser = await puppeteer.launch({
      headless: 'new',
      args: ['--disable-gpu', '--no-first-run', '--no-default-browser-check']
    });

    const page = await browser.newPage();

    // Set up HTTP Basic Auth if password is provided
    if (SITE_PASSWORD) {
      const username = process.env.SITE_USERNAME || 'admin';
      await page.authenticate({ username, password: SITE_PASSWORD });
    }

    for (const photoPage of photoPages) {
      const pageNum = photoPage.pageNumber;
      const pageSide = photoPage.side || 'right';
      const outputFile = path.join(OUTPUT_DIR, `page-${String(pageNum).padStart(3, '0')}.png`);

      // Use full page dimensions (with margins)
      const vw = fullPageWidthPx;
      const vh = fullPageHeightPx;

      try {
        console.log(`Page ${pageNum}...`);

        await page.setViewport({ width: vw, height: vh, deviceScaleFactor: 1 });
        await page.goto(`${VIEW_URL}&num=${pageNum}`, { waitUntil: 'networkidle0', timeout: TIMEOUT });

        // Extra wait for PHP server to respond
        await new Promise(resolve => setTimeout(resolve, 2000));

        // Wait for images to render with visual detection
        let imagesReady = false;
        let attempts = 0;

        while (!imagesReady && attempts < 20) {
          await new Promise(resolve => setTimeout(resolve, 2000));

          const imgStatus = await page.evaluate(() => {
            const imgs = document.querySelectorAll('.v-photo-clip img');
            if (imgs.length === 0) return { ready: true, loaded: 0, total: 0, visible: 0 };

            let loaded = 0;
            let visible = 0;

            for (const img of imgs) {
              if (img.complete && img.naturalHeight > 0 && img.naturalWidth > 0) {
                loaded++;
                // Check if image is actually visible (not just complete)
                const rect = img.getBoundingClientRect();
                if (rect.width > 0 && rect.height > 0) {
                  visible++;
                }
              }
            }

            return {
              ready: loaded === imgs.length && visible === imgs.length,
              loaded,
              total: imgs.length,
              visible
            };
          });

          imagesReady = imgStatus.ready;
          attempts++;

          // Debug: show progress every 5 attempts
          if (attempts % 5 === 0) {
            console.log(`    [attempt ${attempts}] ${imgStatus.loaded}/${imgStatus.total} loaded, ${imgStatus.visible} visible`);
          }
        }

        // Final wait to ensure everything is rendered
        await new Promise(resolve => setTimeout(resolve, 2000));

        await page.screenshot({ path: outputFile, fullPage: false });
      } catch (error) {
        console.error(`  Error page ${pageNum}: ${error.message}`);
      }
    }

    await browser.close();
    const endTime = Date.now();
    const elapsed = ((endTime - startTime) / 1000 / 60).toFixed(1);
    console.log(`\n✓ Complete! ${photoPages.length} screenshots in ${OUTPUT_DIR}`);
    console.log(`Time: ${elapsed} minutes`);
  } catch (error) {
    console.error('Fatal error:', error);
    process.exit(1);
  }
}

capturePages();
