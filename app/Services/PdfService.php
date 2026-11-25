<?php
declare(strict_types=1);

namespace Orixo\Services;

use Orixo\Bootstrap\App;

/**
 * Generates branded invoice PDFs via Dompdf with invoice-specific currency formatting.
 */
final class PdfService
{
    public static function generateInvoicePdf(array $invoice, array $items, array $payments, ?int $actorUserId = null): int
    {
        if (!class_exists(\Dompdf\Dompdf::class)) {
            throw new \RuntimeException('Dompdf is not installed. Run composer install.');
        }

        $settings = app_settings_all();
        ob_start();
        require App::rootPath() . '/app/Views/emails/invoice_pdf.php';
        $html = (string) ob_get_clean();

        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfBytes = $dompdf->output();

        $filename = preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $invoice['invoice_number']) . '.pdf';
        return FileService::storeRawPrivateFile($pdfBytes, 'invoices', $filename, 'application/pdf', $actorUserId);
    }
}
