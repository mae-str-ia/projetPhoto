const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');
const markdownIt = require('markdown-it');
const markdownItTable = require('markdown-it-multimd-table');
const hljs = require('highlight.js');

async function markdownToPdf() {
  try {
    console.log('Loading markdown file...');
    const mdPath = '../WORKFLOW.md';
    const mdContent = fs.readFileSync(mdPath, 'utf8');

    // Initialize markdown parser with syntax highlighting and table support
    const md = new markdownIt({
      html: true,
      linkify: true,
      typographer: true,
      highlight: (code, lang) => {
        if (lang && hljs.getLanguage(lang)) {
          try {
            return hljs.highlight(code, { language: lang, ignoreIllegals: true }).value;
          } catch (err) {
            return code;
          }
        }
        return code;
      }
    }).use(markdownItTable, {
      multiline: true,
      rowspan: true,
      headerless: true
    });

    console.log('Converting markdown to HTML...');
    const htmlContent = md.render(mdContent);

    // Create styled HTML document
    const htmlDoc = `
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ProjetPhoto - Workflow Documentation</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/highlight.js/11.9.0/styles/atom-one-light.min.css">
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      color: #333;
      background: white;
      padding: 40px;
      max-width: 210mm;
      margin: 0 auto;
    }

    h1 {
      font-size: 2.5em;
      margin: 40px 0 20px 0;
      color: #1a1a1a;
      border-bottom: 3px solid #0066cc;
      padding-bottom: 10px;
      page-break-after: avoid;
    }

    h2 {
      font-size: 1.8em;
      margin: 30px 0 15px 0;
      color: #0066cc;
      page-break-after: avoid;
    }

    h3 {
      font-size: 1.3em;
      margin: 20px 0 10px 0;
      color: #004499;
      page-break-after: avoid;
    }

    h4 {
      font-size: 1.1em;
      margin: 15px 0 8px 0;
      color: #333;
      page-break-after: avoid;
    }

    p {
      margin: 12px 0;
      text-align: justify;
    }

    ul, ol {
      margin: 15px 0 15px 40px;
      padding-left: 0;
    }

    li {
      margin: 6px 0;
    }

    code {
      background: #f5f5f5;
      padding: 2px 6px;
      border-radius: 3px;
      font-family: 'Courier New', monospace;
      font-size: 0.9em;
    }

    pre {
      background: #f5f5f5;
      padding: 15px;
      border-radius: 5px;
      overflow-x: auto;
      margin: 15px 0;
      border-left: 4px solid #0066cc;
      page-break-inside: avoid;
    }

    pre code {
      background: none;
      padding: 0;
      font-size: 0.85em;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      margin: 15px 0;
      page-break-inside: avoid;
    }

    th, td {
      border: 1px solid #ddd;
      padding: 10px;
      text-align: left;
    }

    th {
      background: #0066cc;
      color: white;
      font-weight: bold;
    }

    tr:nth-child(even) {
      background: #f9f9f9;
    }

    blockquote {
      border-left: 4px solid #0066cc;
      margin: 15px 0;
      padding-left: 15px;
      color: #666;
      font-style: italic;
    }

    strong {
      font-weight: bold;
      color: #0066cc;
    }

    em {
      font-style: italic;
    }

    a {
      color: #0066cc;
      text-decoration: underline;
    }

    .emoji {
      font-size: 1.2em;
    }

    @media print {
      body {
        padding: 20px;
      }

      h1, h2, h3, h4 {
        page-break-after: avoid;
      }

      pre, table {
        page-break-inside: avoid;
      }

      a {
        text-decoration: none;
      }
    }
  </style>
</head>
<body>
  ${htmlContent}
  <footer style="margin-top: 60px; padding-top: 20px; border-top: 1px solid #ddd; color: #999; font-size: 0.9em; text-align: center;">
    <p>ProjetPhoto - Documentation | Généré le ${new Date().toLocaleDateString('fr-FR')}</p>
  </footer>
</body>
</html>
    `;

    // Launch browser and generate PDF
    console.log('Launching browser...');
    const browser = await puppeteer.launch();
    const page = await browser.newPage();

    console.log('Rendering HTML to PDF...');
    await page.setContent(htmlDoc, { waitUntil: 'networkidle0' });

    const pdfPath = '../WORKFLOW.pdf';
    await page.pdf({
      path: pdfPath,
      format: 'A4',
      margin: {
        top: '20mm',
        bottom: '20mm',
        left: '15mm',
        right: '15mm'
      },
      headerTemplate: '<div style="width: 100%; text-align: center; font-size: 10px; color: #999;">ProjetPhoto - Workflow Documentation</div>',
      footerTemplate: '<div style="width: 100%; text-align: right; font-size: 10px; color: #999; padding-right: 20px;"><span class="pageNumber"></span>/<span class="totalPages"></span></div>',
      displayHeaderFooter: true,
      printBackground: true
    });

    await browser.close();

    const fileSize = fs.statSync(pdfPath).size;
    console.log(`\n✅ PDF généré avec succès!`);
    console.log(`  Fichier: ${pdfPath}`);
    console.log(`  Taille: ${(fileSize / 1024 / 1024).toFixed(1)} MB`);
    console.log(`  Format: A4`);
    console.log(`  Orientation: Portrait`);

  } catch (error) {
    console.error('❌ Erreur:', error.message);
    process.exit(1);
  }
}

markdownToPdf();
