# Vendored client libraries

Stored locally so QR codes still work when the venue has no internet
(for example during the defense). Exact versions, unmodified.

| File | Library | Version | License | Used by |
|---|---|---|---|---|
| `qrcode-generator-1.4.4.js` | qrcode-generator (Kazuhiko Arase) | 1.4.4 | MIT | `barangay/qr.php` — draws the activity QR code |
| `html5-qrcode-2.3.8.min.js` | html5-qrcode (mebjas) | 2.3.8 | Apache-2.0 | `member/scan.php` — in-browser camera scanner |

Both were taken from the npm registry tarballs (`npm pack`).
