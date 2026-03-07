<?php

namespace AvalancheStudio\AvalancheCRM\Classes;

use Barryvdh\DomPDF\Facade\Pdf;
use AvalancheStudio\AvalancheCRM\Models\Invoice;
use AvalancheStudio\AvalancheCRM\Models\Settings;
use Winter\Storm\Support\Facades\Twig;

/**
 * Generates PDF documents for invoices.
 */
class InvoicePdf
{
    /**
     * Generate a PDF for the given invoice.
     *
     * @param  Invoice  $invoice
     * @return \Barryvdh\DomPDF\PDF
     */
    public static function generate(Invoice $invoice)
    {
        $invoice->load(['client', 'project', 'items']);

        $settings = Settings::instance();

        $data = [
            'invoice' => $invoice,
            'currencySymbol' => $settings->currency_symbol ?: '$',
            'currencyCode' => $settings->currency_code ?: 'USD',
            'companyName' => $settings->company_name ?? '',
            'companyAddress' => $settings->company_address ?? '',
            'companyEmail' => $settings->company_email ?? '',
            'companyPhone' => $settings->company_phone ?? '',
            'companyLogo' => $settings->company_logo ? $settings->company_logo->getLocalPath() : null,
        ];

        // Render the Twig template to HTML
        $templatePath = plugins_path('avalanchestudio/avalanchecrm/views/pdf/invoice.htm');
        $templateContent = file_get_contents($templatePath);
        $html = Twig::parse($templateContent, $data);

        $pdf = Pdf::loadHTML($html);
        $pdf->setPaper('a4', 'portrait');

        return $pdf;
    }

    /**
     * Get a download-friendly filename for the invoice.
     *
     * @param  Invoice  $invoice
     * @return string
     */
    public static function filename(Invoice $invoice): string
    {
        $number = $invoice->invoice_number ?: ('INV-' . $invoice->id);
        return preg_replace('/[^A-Za-z0-9\-_]/', '_', $number) . '.pdf';
    }
}
