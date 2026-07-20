TCPDF Integration for Velora

This project can generate server-side PDFs for invoices using TCPDF.

Installation (recommended):
1. Install Composer if you don't have it: https://getcomposer.org/
2. From the project root (`c:/xampp/htdocs/velora`) run:

```bash
composer require tecnickcom/tcpdf
```

What this does:
- Adds TCPDF to `vendor/` and updates `vendor/autoload.php`.
- `api/invoice.php` will automatically require `vendor/autoload.php` when present and use TCPDF to generate PDF downloads.

Notes:
- If TCPDF is not installed, the invoice download will fall back to an HTML file the browser can print/save-as-PDF.
- After installing, you can test by visiting an invoice URL like:

```
http://localhost/velora/api/invoice.php?order=VLR-XXXXXXXX&action=download
```

Troubleshooting:
- If PHP throws memory or font errors, ensure `php.ini` settings allow enough memory and that the `vendor/tecnickcom/tcpdf/fonts` directory is readable.
- For Windows + XAMPP, run Composer from Git Bash or WSL, or install Composer globally and run from PowerShell.
