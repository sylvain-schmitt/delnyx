<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invoice;

/**
 * Service pour l'export CSV des factures (format simplifié pour Excel)
 */
class ExportService
{
    private const CSV_SEPARATOR = ';';

    /**
     * Génère un export CSV des factures
     *
     * @param Invoice[] $invoices
     * @return array{filename: string, content: string}
     */
    public function exportInvoicesCsv(array $invoices): array
    {
        $filename = sprintf('factures_export_%s.csv', date('Y-m-d_H-i-s'));

        // En-têtes
        $headers = [
            'Numéro',
            'Date création',
            'Date échéance',
            'Client',
            'Email client',
            'Montant HT',
            'Montant TVA',
            'Montant TTC',
            'Statut',
            'Date paiement',
            'Notes'
        ];

        $lines = [];
        // UTF-8 BOM pour Excel
        $lines[] = "\xEF\xBB\xBF" . implode(self::CSV_SEPARATOR, $headers);

        foreach ($invoices as $invoice) {
            $client = $invoice->getClient();
            $clientNom = $client ? ($client->getNom() . ' ' . $client->getPrenom()) : '';
            $clientEmail = $client ? $client->getEmail() : '';

            $line = [
                $invoice->getNumero(),
                $invoice->getDateCreation()->format('d/m/Y'),
                $invoice->getDateEcheance()?->format('d/m/Y') ?? '',
                $this->escapeCsvValue($clientNom),
                $clientEmail,
                $this->formatMontant($invoice->getMontantHT()),
                $this->formatMontant($invoice->getMontantTVA()),
                $this->formatMontant($invoice->getMontantTTC()),
                $invoice->getStatutEnum()->getLabel(),
                $invoice->getDatePaiement()?->format('d/m/Y') ?? '',
                $this->escapeCsvValue($invoice->getNotes() ?? '')
            ];

            $lines[] = implode(self::CSV_SEPARATOR, $line);
        }

        return [
            'filename' => $filename,
            'content' => implode("\r\n", $lines)
        ];
    }

    /**
     * Formate un montant pour le CSV
     */
    private function formatMontant(?string $montant): string
    {
        if ($montant === null) {
            return '0,00';
        }

        $value = (float)$montant;
        return number_format($value, 2, ',', ' ');
    }

    /**
     * Échappe les valeurs CSV (guillemets, retours à la ligne)
     */
    private function escapeCsvValue(string $value): string
    {
        // Si contient le séparateur, des guillemets ou des retours à la ligne
        if (
            str_contains($value, self::CSV_SEPARATOR) ||
            str_contains($value, '"') ||
            str_contains($value, "\n") ||
            str_contains($value, "\r")
        ) {
            // Doubler les guillemets et encadrer
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
