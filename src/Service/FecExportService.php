<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Invoice;
use App\Entity\CompanySettings;
use App\Repository\CompanySettingsRepository;

/**
 * Service pour générer le Fichier des Écritures Comptables (FEC)
 * Conforme à l'article A47 A-1 du Livre des Procédures Fiscales
 */
class FecExportService
{
    private const SEPARATOR = "\t"; // Tabulation
    private const DATE_FORMAT = 'Ymd'; // AAAAMMJJ

    // Codes journaux
    private const JOURNAL_CODE_VENTES = 'VE';
    private const JOURNAL_LIB_VENTES = 'Journal des Ventes';

    // Comptes comptables (Plan Comptable Général)
    private const COMPTE_CLIENTS = '411000';
    private const COMPTE_CLIENTS_LIB = 'Clients';
    private const COMPTE_VENTES = '706000';
    private const COMPTE_VENTES_LIB = 'Prestations de services';
    private const COMPTE_TVA = '44571';
    private const COMPTE_TVA_LIB = 'TVA collectée';

    public function __construct(
        private CompanySettingsRepository $companySettingsRepository
    ) {}

    /**
     * Génère le contenu du fichier FEC pour une liste de factures
     *
     * @param Invoice[] $invoices
     * @param \DateTimeInterface $dateClotureExercice Date de clôture de l'exercice
     * @return array{filename: string, content: string}
     */
    public function generateFec(array $invoices, \DateTimeInterface $dateClotureExercice): array
    {
        // Récupérer les premiers settings disponibles (cas mono-utilisateur)
        $companySettings = $this->companySettingsRepository->findOneBy([]);
        $siren = $companySettings?->getSiren() ?? '000000000';

        // Nom du fichier : SIRENFECAAAAMMJJ.txt
        $filename = sprintf(
            '%sFEC%s.txt',
            str_pad(substr(preg_replace('/[^0-9]/', '', $siren), 0, 9), 9, '0', STR_PAD_LEFT),
            $dateClotureExercice->format(self::DATE_FORMAT)
        );

        // En-têtes des colonnes
        $headers = [
            'JournalCode',
            'JournalLib',
            'EcritureNum',
            'EcritureDate',
            'CompteNum',
            'CompteLib',
            'CompAuxNum',
            'CompAuxLib',
            'PieceRef',
            'PieceDate',
            'EcritureLib',
            'Debit',
            'Credit',
            'EcritureLet',
            'DateLet',
            'ValidDate',
            'Montantdevise',
            'Idevise'
        ];

        $lines = [];
        $lines[] = implode(self::SEPARATOR, $headers);

        foreach ($invoices as $invoice) {
            $ecritures = $this->generateEcrituresForInvoice($invoice);
            foreach ($ecritures as $ecriture) {
                $lines[] = implode(self::SEPARATOR, $ecriture);
            }
        }

        return [
            'filename' => $filename,
            'content' => implode("\r\n", $lines)
        ];
    }

    /**
     * Génère les écritures comptables pour une facture
     *
     * @return array<array<string>>
     */
    private function generateEcrituresForInvoice(Invoice $invoice): array
    {
        $ecritures = [];

        $date = $invoice->getDateCreation()->format(self::DATE_FORMAT);
        $numero = $invoice->getNumero();
        $client = $invoice->getClient();
        $clientNom = $client ? ($client->getNom() . ' ' . $client->getPrenom()) : 'Client inconnu';
        $clientId = $client ? (string)$client->getId() : '';

        $montantHT = $this->formatMontant($invoice->getMontantHT());
        $montantTVA = $this->formatMontant($invoice->getMontantTVA());
        $montantTTC = $this->formatMontant($invoice->getMontantTTC());

        // Libellé de l'écriture
        $ecritureLib = sprintf('Facture %s - %s', $numero, $clientNom);

        // Écriture 1 : Compte Client (Débit)
        $ecritures[] = $this->createLine(
            $numero,
            $date,
            self::COMPTE_CLIENTS,
            self::COMPTE_CLIENTS_LIB,
            $clientId,
            $clientNom,
            $numero,
            $date,
            $ecritureLib,
            $montantTTC, // Débit
            '0,00',      // Crédit
            $date
        );

        // Écriture 2 : Compte Ventes (Crédit)
        $ecritures[] = $this->createLine(
            $numero,
            $date,
            self::COMPTE_VENTES,
            self::COMPTE_VENTES_LIB,
            '',
            '',
            $numero,
            $date,
            $ecritureLib,
            '0,00',      // Débit
            $montantHT,  // Crédit
            $date
        );

        // Écriture 3 : Compte TVA (Crédit) - seulement si TVA > 0
        if ((float)str_replace(',', '.', $montantTVA) > 0) {
            $ecritures[] = $this->createLine(
                $numero,
                $date,
                self::COMPTE_TVA,
                self::COMPTE_TVA_LIB,
                '',
                '',
                $numero,
                $date,
                $ecritureLib,
                '0,00',      // Débit
                $montantTVA, // Crédit
                $date
            );
        }

        return $ecritures;
    }

    /**
     * Crée une ligne d'écriture
     */
    private function createLine(
        string $ecritureNum,
        string $ecritureDate,
        string $compteNum,
        string $compteLib,
        string $compAuxNum,
        string $compAuxLib,
        string $pieceRef,
        string $pieceDate,
        string $ecritureLib,
        string $debit,
        string $credit,
        string $validDate
    ): array {
        return [
            self::JOURNAL_CODE_VENTES, // JournalCode
            self::JOURNAL_LIB_VENTES,  // JournalLib
            $ecritureNum,              // EcritureNum
            $ecritureDate,             // EcritureDate
            $compteNum,                // CompteNum
            $compteLib,                // CompteLib
            $compAuxNum,               // CompAuxNum
            $compAuxLib,               // CompAuxLib
            $pieceRef,                 // PieceRef
            $pieceDate,                // PieceDate
            $ecritureLib,              // EcritureLib
            $debit,                    // Debit
            $credit,                   // Credit
            '',                        // EcritureLet
            '',                        // DateLet
            $validDate,                // ValidDate
            '',                        // Montantdevise
            'EUR'                      // Idevise
        ];
    }

    /**
     * Formate un montant pour le FEC (virgule comme séparateur décimal)
     */
    private function formatMontant(?string $montant): string
    {
        if ($montant === null) {
            return '0,00';
        }

        $value = (float)$montant;
        return number_format($value, 2, ',', '');
    }
}
