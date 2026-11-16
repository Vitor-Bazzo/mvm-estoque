/**
 * Script de Build Oficial para Vercel (MVM Estoque)
 * 1. Isola os arquivos estáticos (CSS, JS, Imagens, Uploads, PWA) na pasta public/
 * 2. Empacota um mapa estático de fallback em lib/mvm_static_assets.json para garantir
 *    que 100% do CSS, JS, ícones SVG e fotos de produtos carreguem com o MIME type exato.
 */
const fs = require('fs');
const path = require('path');

const rootDir = path.resolve(__dirname, '..');
const publicDir = path.join(rootDir, 'public');
const bundleFile = path.join(rootDir, 'lib', 'mvm_static_assets.json');

const MIME_MAP = {
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.gif': 'image/gif',
  '.ico': 'image/x-icon',
  '.woff2': 'font/woff2'
};

const assetBundle = {};

function processStaticSync(src, dest, relPrefix) {
  if (!fs.existsSync(src)) return;
  const stats = fs.statSync(src);
  if (stats.isDirectory()) {
    if (!fs.existsSync(dest)) {
      fs.mkdirSync(dest, { recursive: true });
    }
    for (const child of fs.readdirSync(src)) {
      processStaticSync(
        path.join(src, child),
        path.join(dest, child),
        relPrefix ? `${relPrefix}/${child}` : child
      );
    }
  } else {
    const ext = path.extname(src).toLowerCase();
    if (/\.(php|sql|env|md)$/i.test(src)) return;
    fs.copyFileSync(src, dest);
    if (MIME_MAP[ext]) {
      const normalizedKey = '/' + relPrefix.replace(/\\/g, '/');
      assetBundle[normalizedKey] = {
        mime: MIME_MAP[ext],
        b64: fs.readFileSync(src).toString('base64')
      };
    }
  }
}

if (fs.existsSync(publicDir)) {
  fs.rmSync(publicDir, { recursive: true, force: true });
}
fs.mkdirSync(publicDir, { recursive: true });

const staticDirs = ['css', 'javaScript', 'img', 'uploads'];
for (const dir of staticDirs) {
  processStaticSync(path.join(rootDir, dir), path.join(publicDir, dir), dir);
}

const staticFiles = ['manifest.json', 'sw.js'];
for (const file of staticFiles) {
  const srcFile = path.join(rootDir, file);
  if (fs.existsSync(srcFile)) {
    processStaticSync(srcFile, path.join(publicDir, file), file);
  }
}

fs.writeFileSync(bundleFile, JSON.stringify(assetBundle), 'utf8');
console.log(`[MVM Vercel Build] ${Object.keys(assetBundle).length} assets estáticos empacotados com sucesso.`);
