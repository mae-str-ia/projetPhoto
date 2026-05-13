const puppeteer = require('puppeteer');

async function debugView() {
  const browser = await puppeteer.launch();
  const page = await browser.newPage();

  await page.goto('http://localhost:8081/?page=view&num=6', { waitUntil: 'networkidle2' });

  const info = await page.evaluate(() => {
    const container = document.getElementById('viewContainer');
    const content = document.querySelector('.view-page-content');
    const slots = document.querySelectorAll('.v-slot');
    const images = document.querySelectorAll('.v-photo-clip img');

    return {
      containerExists: !!container,
      containerDisplay: container ? window.getComputedStyle(container).display : 'N/A',
      containerSize: container ? `${container.clientWidth}×${container.clientHeight}` : 'N/A',
      contentExists: !!content,
      contentDisplay: content ? window.getComputedStyle(content).display : 'N/A',
      contentSize: content ? `${content.clientWidth}×${content.clientHeight}` : 'N/A',
      slotsFound: slots.length,
      slotsInfo: Array.from(slots).slice(0, 3).map((s, i) => ({
        index: i,
        display: window.getComputedStyle(s).display,
        position: `${s.offsetLeft},${s.offsetTop}`,
        size: `${s.offsetWidth}×${s.offsetHeight}`,
        visible: s.offsetHeight > 0 && s.offsetWidth > 0
      })),
      imagesFound: images.length,
      imagesLoaded: Array.from(images).filter(img => img.complete && img.naturalHeight > 0).length
    };
  });

  console.log(JSON.stringify(info, null, 2));

  await browser.close();
}

debugView().catch(console.error);
