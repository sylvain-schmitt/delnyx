<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Invoice;
use App\Entity\InvoiceStatus;
use App\Entity\CompanySettings;
use App\Entity\PDPMode;
use App\Repository\CompanySettingsRepository;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Psr\Log\LoggerInterface;

/**
 * EventSubscriber pour la transmission des factures vers une PDP
 * (Plateforme de Dématérialisation Partenaire)
 * 
 * Ce subscriber prépare la structure pour la facturation électronique obligatoire en France.
 * L'intégration complète avec les APIs PDP sera implémentée ultérieurement.
 * 
 * Fonctionnalités :
 * - Détecte quand une facture est émise (statut SENT)
 * - Vérifie si le mode PDP est activé dans CompanySettings
 * - Prépare la transmission vers la PDP (structure de base)
 * - Met à jour le statut PDP de la facture
 * - Log les actions pour le débogage
 */
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate')]
class PDPTransmissionSubscriber
{
    public function __construct(
        private CompanySettingsRepository $companySettingsRepository,
        private ?LoggerInterface $logger = null
    ) {}

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Invoice) {
            return;
        }

        $uow = $args->getObjectManager()->getUnitOfWork();
        $changeset = $uow->getEntityChangeSet($entity);

        // Vérifier si le statut a changé vers SENT (facture émise)
        if (isset($changeset['statut']) && $entity->getStatut() === InvoiceStatus::ISSUED->value) {
            $this->handleInvoiceEmission($entity, $args);
        }
    }

    /**
     * Gère l'émission d'une facture et prépare la transmission PDP si nécessaire
     */
    private function handleInvoiceEmission(Invoice $invoice, LifecycleEventArgs $args): void
    {
        // Récupérer les paramètres de l'entreprise
        $companySettings = $this->companySettingsRepository->findByCompanyId($invoice->getCompanyId());

        if (!$companySettings) {
            $this->log('warning', sprintf(
                'CompanySettings non trouvé pour company_id: %s. Transmission PDP ignorée.',
                $invoice->getCompanyId()
            ));
            return;
        }

        // Vérifier si le mode PDP est activé
        if (!$companySettings->isPdpEnabled()) {
            $this->log('debug', sprintf(
                'Mode PDP non activé pour la facture #%s. Transmission ignorée.',
                $invoice->getNumero()
            ));
            return;
        }

        $pdpMode = $companySettings->getPdpModeEnum();

        if (!$pdpMode || $pdpMode === PDPMode::NONE) {
            return;
        }

        // Préparer la transmission vers la PDP
        $this->preparePDPTransmission($invoice, $companySettings, $pdpMode, $args);
    }

    /**
     * Prépare la transmission vers la PDP
     * 
     * NOTE: Cette méthode prépare la structure. L'intégration complète avec les APIs PDP
     * sera implémentée ultérieurement avec les providers spécifiques (Jefacture, DPii, etc.)
     */
    private function preparePDPTransmission(
        Invoice $invoice,
        CompanySettings $companySettings,
        PDPMode $pdpMode,
        LifecycleEventArgs $args
    ): void {
        $em = $args->getObjectManager();

        // Mettre à jour les informations PDP de la facture
        $invoice->setPdpProvider($companySettings->getPdpProvider());
        $invoice->setPdpStatus('PENDING'); // Statut initial
        $invoice->setPdpTransmissionDate(new \DateTime());

        // Log de la préparation
        $this->log('info', sprintf(
            'Préparation transmission PDP pour facture #%s (Mode: %s, Provider: %s)',
            $invoice->getNumero(),
            $pdpMode->value,
            $companySettings->getPdpProvider() ?? 'Non défini'
        ));

        // TODO: Implémenter la conversion en Factur-X XML
        // $facturXXml = $this->convertToFacturX($invoice, $companySettings);

        // TODO: Implémenter l'envoi vers la PDP selon le provider
        // if ($pdpMode === PDPMode::SANDBOX || $pdpMode === PDPMode::PRODUCTION) {
        //     $this->sendToPDP($invoice, $facturXXml, $companySettings, $pdpMode);
        // }

        // Pour l'instant, on simule juste la préparation
        // En production, cette partie sera remplacée par l'appel réel à l'API PDP
        if ($pdpMode === PDPMode::SANDBOX) {
            // Mode sandbox : simulation
            $this->simulatePDPSandbox($invoice);
        } elseif ($pdpMode === PDPMode::PRODUCTION) {
            // Mode production : préparation pour l'intégration future
            $this->log('warning', sprintf(
                'Mode PRODUCTION activé pour facture #%s mais intégration PDP non encore implémentée.',
                $invoice->getNumero()
            ));
            // TODO: Appel réel à l'API PDP en production
        }

        // L'EntityManager se charge automatiquement du flush via Doctrine
    }

    /**
     * Simule une transmission PDP en mode sandbox
     * 
     * Cette méthode sera remplacée par l'intégration réelle avec l'API PDP
     */
    private function simulatePDPSandbox(Invoice $invoice): void
    {
        // Simulation : on accepte automatiquement en sandbox
        $invoice->setPdpStatus('ACCEPTED');
        $invoice->setPdpResponse(json_encode([
            'status' => 'accepted',
            'message' => 'Transmission simulée en mode sandbox',
            'timestamp' => (new \DateTime())->format('Y-m-d H:i:s'),
            'invoice_number' => $invoice->getNumero()
        ]));

        $this->log('info', sprintf(
            'Transmission PDP simulée (SANDBOX) pour facture #%s - Statut: ACCEPTED',
            $invoice->getNumero()
        ));
    }

    /**
     * Convertit une facture en format Factur-X XML
     * 
     * TODO: Implémenter la conversion complète selon le standard Factur-X
     * Voir: https://www.factur-x.com/
     * 
     * @return string XML Factur-X
     */
    private function convertToFacturX(Invoice $invoice, CompanySettings $companySettings): string
    {
        // TODO: Implémenter la conversion en Factur-X XML
        // Structure de base à préparer :
        // - En-tête (CrossIndustryInvoice)
        // - Informations vendeur (depuis CompanySettings)
        // - Informations acheteur (depuis Invoice->Client)
        // - Lignes de facture (depuis Invoice->Lines)
        // - Totaux (HT, TVA, TTC)
        // - Mentions légales

        $this->log('debug', sprintf(
            'Conversion Factur-X XML non encore implémentée pour facture #%s',
            $invoice->getNumero()
        ));

        return '';
    }

    /**
     * Envoie une facture vers la PDP via l'API du provider
     * 
     * TODO: Implémenter l'intégration avec les différents providers PDP
     * Providers possibles : Jefacture, DPii, Pennylane, etc.
     */
    private function sendToPDP(
        Invoice $invoice,
        string $facturXXml,
        CompanySettings $companySettings,
        PDPMode $pdpMode
    ): void {
        $provider = $companySettings->getPdpProvider();
        $apiKey = $companySettings->getPdpApiKey();

        if (!$provider || !$apiKey) {
            $this->log('error', sprintf(
                'Provider PDP ou API Key manquant pour facture #%s',
                $invoice->getNumero()
            ));
            return;
        }

        // TODO: Implémenter l'appel API selon le provider
        // Exemple de structure :
        // switch ($provider) {
        //     case 'jefacture':
        //         $this->sendToJefacture($invoice, $facturXXml, $apiKey, $pdpMode);
        //         break;
        //     case 'dpii':
        //         $this->sendToDPii($invoice, $facturXXml, $apiKey, $pdpMode);
        //         break;
        //     // etc.
        // }

        $this->log('info', sprintf(
            'Envoi vers PDP non encore implémenté (Provider: %s) pour facture #%s',
            $provider,
            $invoice->getNumero()
        ));
    }

    /**
     * Log un message
     */
    private function log(string $level, string $message): void
    {
        if ($this->logger) {
            $this->logger->log($level, '[PDPTransmission] ' . $message);
        }
    }
}
