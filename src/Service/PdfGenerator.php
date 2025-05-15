<?php

namespace App\Service;

use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment; // Import Twig Environment

class PdfGenerator
{
    private Environment $twig;

    /**
     * @param Environment $twig The Twig environment service
     */
    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    /**
     * Generates a PDF from a Twig template.
     *
     * @param string $template The path to the Twig template (e.g., 'invoice/invoice.html.twig')
     * @param array $data An array of data to pass to the Twig template
     * @return string The PDF content as a string
     */
    public function generatePdf(string $template, array $data): string
    {
        // Configure Dompdf options
        $options = new Options();
        // Set the default font to one that supports a wider range of characters if needed (like DejaVu Sans)
        // Make sure this font is available to Dompdf. You might need to install it or configure Dompdf's font directory.
        // For simpler cases, 'Arial' or 'Helvetica' might suffice, but can have issues with non-ASCII characters.
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        // Enabling remote access is necessary if your template includes external resources like images via URL
        $options->set('isRemoteEnabled', true);

        // Instantiate Dompdf with the configured options
        $dompdf = new Dompdf($options);

        // Render the Twig template to HTML
        $html = $this->twig->render($template, $data);

        // Load HTML content into Dompdf
        $dompdf->loadHtml($html);

        // Set paper size and orientation
        $dompdf->setPaper('A4', 'portrait');

        // Render the HTML as PDF
        $dompdf->render();

        // Get the generated PDF content
        return $dompdf->output();
    }
}
