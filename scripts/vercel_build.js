/**
 * Script de Build Oficial para Vercel (MVM Estoque)
 * Isola apenas os arquivos estáticos (CSS, JS, Imagens, Uploads, PWA) na pasta public/
 * garantindo que NENHUM arquivo .php seja servido como download estático pela CDN da Vercel.
 */
const fs = require('fs');
const path = require('path');

const rootDir = path.resolve(__dirname, '..');
const publicDir = path.join(rootDir, 'public');

function copyRecursiveSync(src, dest) {
  if (!fs.existsSync(src)) return;
  const stats = fs.statSync(src);
  if (stats.isDirectory()) {
    if (!fs.existsSync(dest)) {
      fs.mkdirSync(dest, { recursive: true });
    }
    for (const child of fs.readdirSync(src)) {
      copyRecursiveSync(path.join(src, child), path.join(dest, child));
    }
  } else {
    // Nunca copia arquivos .php ou .sql para o diretório estático público
    if (/\.(php|sql|env|md)$/i.test(src)) return;
    fs.copyFileSync(src, dest);
  }
}

if (fs.existsSync(publicDir)) {
  fs.rmSync(publicDir, { recursive: true, force: true });
}
fs.mkdirSync(publicDir, { recursive: true });

const staticDirs = ['css', 'javaScript', 'img', 'uploads'];
for (const dir of staticDirs) {
  copyRecursiveSync(path.join(rootDir, dir), path.join(publicDir, dir));
}

const staticFiles = ['manifest.json', 'sw.js'];
for (const file of staticFiles) {
  const srcFile = path.join(rootDir, file);
  if (fs.existsSync(srcFile)) {
    fs.copyFileSync(srcFile, path.join(publicDir, file));
  }
}

console.log('[MVM Vercel Build] Diretório estático public/ gerado com sucesso (0 arquivos .php expostos).');
