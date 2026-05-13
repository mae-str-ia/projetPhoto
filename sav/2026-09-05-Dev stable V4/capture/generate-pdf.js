#!/usr/bin/env node
/**
 * generate-pdf.js
 *
 * Orchestrates the complete PDF generation pipeline:
 * 1. Pulls data from server (book.json, livre.md, photos)
 * 2. Generates texte.pdf (text)
 * 3. Captures photo pages (Puppeteer screenshots)
 * 4. Merges final PDF (pdf-lib)
 *
 * Configuration via environment variables (or .env file)
 */

const fs = require('fs');
const path = require('path');
const https = require('https');
const http = require('http');
const { execSync } = require('child_process');

// Load .env if it exists
if (fs.existsSync(path.join(__dirname, '.env'))) {
  const envContent = fs.readFileSync(path.join(__dirname, '.env'), 'utf8');
  envContent.split('\n').forEach(line => {
    const trimmed = line.trim();
    if (!trimmed || trimmed.startsWith('#')) return;
    const [key, value] = trimmed.split('=');
    if (key && value) {
      process.env[key] = value;
    }
  });
}

// Configuration
const SERVER_URL = process.env.VIEW_URL?.split('?')[0] || 'http://localhost:8081';
const SYNC_TOKEN = process.env.SYNC_TOKEN || 'changeme';
const DATA_DIR = path.join(__dirname, '../data');
const PHP_EXE = process.env.PHP_EXE || 'C:/Devs/php85/php.exe';

const startTime = Date.now();

// ============================================================================
// 1. PULL DATA FROM SERVER
// ============================================================================
async function pullDataFromServer() {
  console.log('\n📥 Pulling data from server...');

  try {
    // Get export data
    const exportUrl = new URL('/api/sync.php', SERVER_URL);
    exportUrl.searchParams.set('action', 'export');
    exportUrl.searchParams.set('token', SYNC_TOKEN);

    const exportData = await fetchJson(exportUrl.toString());
    if (!exportData.success) {
      throw new Error('Export failed: ' + (exportData.error || 'Unknown error'));
    }

    console.log(`✓ Loaded book.json with ${exportData.photos.length} photos`);

    // Save book.json
    const bookPath = path.join(DATA_DIR, 'book.json');
    ensureDir(DATA_DIR);
    fs.writeFileSync(bookPath, JSON.stringify(exportData.book, null, 2), 'utf8');
    console.log(`✓ Saved book.json`);

    // Save markdown
    const markdownDir = path.join(DATA_DIR, 'markdown/clean');
    ensureDir(markdownDir);
    const markdownPath = path.join(markdownDir, 'livre.md');
    fs.writeFileSync(markdownPath, exportData.markdown, 'utf8');
    console.log(`✓ Saved livre.md`);

    // Download missing photos
    const photoDir = path.join(DATA_DIR, 'uploads/photos');
    ensureDir(photoDir);
    let downloadedCount = 0;

    for (const photoFile of exportData.photos) {
      const photoPath = path.join(photoDir, photoFile);
      if (!fs.existsSync(photoPath)) {
        await downloadPhoto(photoFile, photoPath, exportData.baseUrl, SYNC_TOKEN);
        downloadedCount++;
      }
    }

    if (downloadedCount > 0) {
      console.log(`✓ Downloaded ${downloadedCount} new photos`);
    } else {
      console.log(`✓ All ${exportData.photos.length} photos already present`);
    }

    return exportData;

  } catch (error) {
    console.error('❌ Failed to pull data:', error.message);
    process.exit(1);
  }
}

// ============================================================================
// 2. GENERATE TEXT PDF
// ============================================================================
function generateTextPdf() {
  console.log('\n📄 Generating text PDF...');

  try {
    // Check if CLI script exists (or create one if needed)
    const cliScript = path.join(__dirname, '../app/public/api/cli-generate-text.php');

    if (!fs.existsSync(cliScript)) {
      console.warn('⚠️  cli-generate-text.php not found, will attempt to use markdown.php via HTTP instead');
      // Fallback: could make HTTP request to api/markdown.php?action=generateTextPdf
      // For now, skip this step if the file doesn't exist
      console.log('⏭️  Skipping text PDF generation (no CLI script)');
      return;
    }

    const cmd = `"${PHP_EXE}" "${cliScript}"`;
    console.log(`Running: ${cmd}`);

    const result = execSync(cmd, {
      cwd: __dirname,
      encoding: 'utf8',
      stdio: 'pipe'
    });

    const output = JSON.parse(result);
    if (output.success) {
      console.log(`✓ Generated texte.pdf`);
    } else {
      throw new Error(output.error || 'Unknown error');
    }

  } catch (error) {
    console.error('❌ Failed to generate text PDF:', error.message);
    process.exit(1);
  }
}

// ============================================================================
// 3. CAPTURE PHOTO PAGES
// ============================================================================
async function capturePhotoPages() {
  console.log('\n📸 Capturing photo pages...');

  try {
    // Set environment variables for capture-pages.js
    process.env.VIEW_URL = `${SERVER_URL}/?page=view`;
    if (process.env.SITE_PASSWORD) {
      process.env.SITE_USERNAME = process.env.SITE_USERNAME || 'admin';
    }

    // Require and run capture-pages.js
    // This is a bit tricky since capture-pages.js calls capturePages() at the end
    // Instead, we'll fork it as a child process

    const { execSync } = require('child_process');
    const captureScript = path.join(__dirname, 'capture-pages.js');

    const env = { ...process.env };
    env.VIEW_URL = `${SERVER_URL}/?page=view`;

    execSync(`node "${captureScript}"`, {
      cwd: __dirname,
      stdio: 'inherit',
      env
    });

    console.log(`✓ Photo pages captured`);

  } catch (error) {
    console.error('❌ Failed to capture photo pages:', error.message);
    process.exit(1);
  }
}

// ============================================================================
// 4. MERGE FINAL PDF
// ============================================================================
function mergeFinalPdf() {
  console.log('\n🔗 Merging final PDF...');

  try {
    const mergeScript = path.join(__dirname, 'merge-hq-pdf-v2.js');

    if (!fs.existsSync(mergeScript)) {
      console.warn('⚠️  merge-hq-pdf-v2.js not found');
      return;
    }

    execSync(`node "${mergeScript}"`, {
      cwd: __dirname,
      stdio: 'inherit'
    });

    console.log(`✓ Final PDF merged: livre.print.pdf`);

  } catch (error) {
    console.error('❌ Failed to merge PDF:', error.message);
    process.exit(1);
  }
}

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function ensureDir(dir) {
  if (!fs.existsSync(dir)) {
    fs.mkdirSync(dir, { recursive: true });
  }
}

async function fetchJson(url) {
  return new Promise((resolve, reject) => {
    const protocol = url.startsWith('https') ? https : http;
    protocol.get(url, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          resolve(JSON.parse(data));
        } catch (e) {
          reject(new Error('Invalid JSON response: ' + e.message));
        }
      });
    }).on('error', reject);
  });
}

async function downloadPhoto(filename, destPath, baseUrl, token) {
  return new Promise((resolve, reject) => {
    const protocol = baseUrl.startsWith('https') ? https : http;
    const photoUrl = baseUrl + filename;

    protocol.get(photoUrl, (res) => {
      if (res.statusCode !== 200) {
        reject(new Error(`Failed to download ${filename}: ${res.statusCode}`));
        return;
      }

      const file = fs.createWriteStream(destPath);
      res.pipe(file);
      file.on('finish', () => {
        file.close();
        resolve();
      });
      file.on('error', reject);
    }).on('error', reject);
  });
}

// ============================================================================
// MAIN EXECUTION
// ============================================================================

async function main() {
  console.log('╔════════════════════════════════════════════╗');
  console.log('║  ProjetPhoto PDF Generation Pipeline      ║');
  console.log('╚════════════════════════════════════════════╝');
  console.log(`Server: ${SERVER_URL}`);
  console.log(`Data directory: ${DATA_DIR}`);

  try {
    // Step 1: Pull from server
    await pullDataFromServer();

    // Step 2: Generate text PDF
    generateTextPdf();

    // Step 3: Capture photo pages
    await capturePhotoPages();

    // Step 4: Merge final PDF
    mergeFinalPdf();

    // Summary
    const elapsed = ((Date.now() - startTime) / 1000 / 60).toFixed(1);
    console.log('\n╔════════════════════════════════════════════╗');
    console.log('║  ✓ PDF Generation Complete                ║');
    console.log('╚════════════════════════════════════════════╝');
    console.log(`Output: ${path.join(__dirname, 'livre.print.pdf')}`);
    console.log(`Total time: ${elapsed} minutes`);

  } catch (error) {
    console.error('\n❌ Pipeline failed:', error.message);
    process.exit(1);
  }
}

main();
