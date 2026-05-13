# Server Setup - ProjetPhoto

## ✅ Working Configuration

### Start Development Server

```bash
cd c:\Devs\ProjetCR\projetPhoto\app\public
"C:/Devs/php85/php.exe" -S 127.0.0.1:8081
```

**Important**: 
- Use `127.0.0.1:8081` (IPv4), NOT `localhost:8081` (might resolve to IPv6)
- Start from `/app/public/` directory directly
- NO router file needed

### Access the Application

- **Main app**: http://127.0.0.1:8081
- **View specific page**: http://127.0.0.1:8081/?page=view&num=2
- **Image endpoint**: http://127.0.0.1:8081/photo.php?file=photo_xxx.png

## ❌ What Doesn't Work

- Starting from `/projetPhoto/` root with router.php ❌
- Using `localhost:8081` instead of `127.0.0.1:8081` ❌ (IPv6 issues)
- Using custom router in dev server ❌

## 📝 Configuration

The config automatically detects:
- Running from `/app/public/` directory
- Sets BASE_URL to just the host (no `/app/public` prefix)

### How It Works

1. **Development** (PHP built-in server from `/app/public/`)
   - Server root = `/app/public/`
   - Requests like `/photo.php` directly hit the file
   - BASE_URL = `http://127.0.0.1:8081`

2. **Production** (Apache/Nginx with `/app/public/` as web root)
   - DocumentRoot = `/app/public/`
   - Same URL structure as development
   - .htaccess handles routing

## 🚀 Running Capture

With the server running on 127.0.0.1:8081:

```bash
cd c:\Devs\ProjetCR\projetPhoto\capture
node capture-pages.js
node merge-hq-pdf-v2.js
```

This creates: `c:\Devs\ProjetCR\livre.print.pdf`

## 📋 Files Modified

- `config/config.php`: Removed `/app/public` from BASE_URL
- `capture/capture-pages.js`: Changed default VIEW_URL to 127.0.0.1
- This file: SERVER_SETUP.md
