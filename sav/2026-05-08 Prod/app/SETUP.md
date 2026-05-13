# Setup Guide - projetPhoto

## Quick Start

### 1. Enable PHP GD Extension

The app requires PHP GD for image manipulation.

Edit `C:/Devs/php85/php.ini` and uncomment:
```ini
extension=gd
```

Look for this line (around line 913) and remove the `;` at the start:
```ini
;extension=gd
```

Should become:
```ini
extension=gd
```

Then restart any running PHP servers.

To verify GD is loaded:
```bash
"C:/Devs/php85/php.exe" -m | grep -i gd
```

### 2. Install Dependencies

```bash
cd c:/Devs/ProjetCR/projetPhoto
composer install
```

### 3. Prepare PDF

Place your 160-page PDF at:
```
c:/Devs/ProjetCR/projetPhoto/pdf/source.pdf
```

The PDF must contain exactly 160 pages. Pages will be numbered 1-160.

### 4. Verify Ghostscript/ImageMagick

The app needs Ghostscript or ImageMagick to convert PDF pages to images.

**ImageMagick** (already detected on your system): https://imagemagick.org/script/download.php

**Ghostscript** (optional, better performance): https://www.ghostscript.com/download/gsdnld.html
- Make sure to check "Add Ghostscript to system path" during installation
- Test: Open CMD and run `gswin64c -v`

### 5. Create Data Directory

Ensure writable directories exist:

```bash
mkdir -p c:/Devs/ProjetCR/projetPhoto/data/pdf-cache
mkdir -p c:/Devs/ProjetCR/projetPhoto/public/uploads/photos
```

Permissions should allow write access (normally automatic on Windows).

### 6. Start Application

```bash
cd c:/Devs/ProjetCR/projetPhoto/public
"C:/Devs/php85/php.exe" -S localhost:8081
```

Open browser: http://localhost:8081

## What Gets Created

On first access:
- `data/book.json` - Book structure with 160 pages (pages 1,3,5... are text; 2,4,6... are photos)
- `data/pdf-cache/page_000.png`, `page_001.png`, etc. - Converted PDF pages (auto-created on demand)

## File Structure

```
projetPhoto/
├── config/config.php           # Constants and autoloader
├── src/
│   ├── BookManager.php         # JSON book management
│   ├── PhotoManager.php        # Photo upload/metadata
│   ├── ImageProcessor.php      # GD image manipulation
│   ├── PdfManager.php          # PDF to PNG conversion
│   └── ExportManager.php       # Word document generation
├── public/
│   ├── index.php              # Router entry point
│   ├── api/
│   │   ├── photos.php         # Photo API
│   │   ├── pages.php          # Page layout API
│   │   ├── pdf.php            # PDF conversion API
│   │   └── export.php         # Word export API
│   ├── js/
│   │   ├── app.js             # Main app logic
│   │   └── page-editor.js     # Editor view
│   ├── css/app.css            # Styling
│   └── uploads/photos/        # Uploaded photos
├── templates/
│   ├── layout.php             # Master template
│   ├── gallery.php            # Thumbnail view
│   ├── spread.php             # Double-page view
│   └── editor.php             # Photo editor view
├── data/
│   ├── book.json              # Book data (created automatically)
│   └── pdf-cache/             # PDF page images (created on demand)
├── pdf/
│   └── source.pdf             # Your PDF file (place manually)
└── vendor/                     # Composer dependencies
```

## Page Numbering

The book has 160 pages total:
- **Page 1** (right) = Text from PDF page 0
- **Page 2** (left) = Photo page
- **Page 3** (right) = Text from PDF page 1
- **Page 4** (left) = Photo page
- ... and so on

Each pair (odd+even) is called a "spread".

## Troubleshooting

### PHP Error: "Class not found: PhpWord"

Run: `composer install`

### PDF pages not showing

1. Check Ghostscript is installed: `gswin64c -v`
2. Delete `data/pdf-cache/` and reload (pages will be re-converted)
3. Check that `pdf/source.pdf` exists
4. Check `public/uploads/photos/` has write permissions

### Photos not uploading

1. Check directory permissions: `public/uploads/photos/`
2. Check max upload size in `config/config.php` (default 50MB)
3. Check file format (JPEG, PNG, WebP only)
4. Check PHP `upload_max_filesize` and `post_max_size` in php.ini

### Export to Word fails

1. Check Composer dependencies: `composer install`
2. Check file is actually being created in `data/book.json`
3. Try smaller book first (test with 2-3 pages)

## Browser Compatibility

- Chrome/Edge 90+
- Firefox 88+
- Safari 14+

Requires modern JavaScript features (ES6+).

## Performance Notes

- First page load may be slow (PDF conversion takes ~5-30 seconds per page)
- Pages are cached, so repeated visits are fast
- Large photos (>5MB) take longer to upload
- Export to Word takes 10-30 seconds depending on number of photos

## Typical Workflow

1. Open http://localhost:8081
2. See thumbnail gallery of all spreads
3. Click on a spread to see double-page view
4. Click on photo page (left) to edit
5. Upload photos, arrange them, add captions
6. Click "Exporter" to download Word document

## Customization

Edit `config/config.php` to adjust:
- `PAGE_WIDTH`, `PAGE_HEIGHT` - Page dimensions
- `MAX_FILE_SIZE` - Max upload size
- `ALLOWED_EXTENSIONS` - File types
- Layouts in `BookManager::initBook()`

Edit `public/css/app.css` for styling.

## Notes

- No user authentication (single user only)
- All changes auto-save to JSON
- PDF source cannot be changed from UI (must manually replace file)
- No undo/redo system (can edit JSON manually if needed)
- Photos are never auto-deleted (safe deletion)
