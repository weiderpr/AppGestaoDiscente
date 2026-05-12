#!/usr/bin/env node
/**
 * Gerador de QR Code — CLI para uso interno pelo PHP
 * Uso: node qrcode_gen.js <url> <width>
 * Saída: base64 da imagem PNG (sem header data:image/png)
 */
const QRCode = require('qrcode');

const url   = process.argv[2];
const width = parseInt(process.argv[3] || '300', 10);

if (!url) {
    process.stderr.write('Uso: node qrcode_gen.js <url> [width]\n');
    process.exit(1);
}

QRCode.toDataURL(url, {
    width,
    margin: 1,
    color: { dark: '#1e1b4b', light: '#ffffff' },
    errorCorrectionLevel: 'M'
}, (err, dataUrl) => {
    if (err) {
        process.stderr.write('Erro: ' + err.message + '\n');
        process.exit(1);
    }
    // Retorna apenas a parte base64 (sem "data:image/png;base64,")
    process.stdout.write(dataUrl.replace(/^data:image\/png;base64,/, ''));
});
