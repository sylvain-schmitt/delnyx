<?php

namespace App\Service;

use App\Entity\Quote;
use App\Entity\Amendment;
use App\Entity\Invoice;
use App\Entity\CreditNote;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Service de génération de "Magic Links" - URLs signées pour actions clients
 * 
 * Permet aux clients d'effectuer des actions (signer, payer, refuser) 
 * directement depuis un email, sans authentification.
 * 
 * Sécurité : HMAC-SHA256 basé sur APP_SECRET + Expiration configurable
 */
class MagicLinkService
{
    private const DEFAULT_EXPIRATION_DAYS = 30;
    
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private string $appSecret
    ) {
    }

    /**
     * Génère un lien magique public signé pour une action client
     * 
     * @param Quote|Amendment|Invoice|CreditNote $document
     * @param string $action Action à effectuer : 'view', 'sign', 'refuse', 'pay', 'apply'
     * @param int $expirationDays Nombre de jours avant expiration (défaut: 30)
     * @return string URL complète signée
     */
    public function generatePublicLink(
        Quote|Amendment|Invoice|CreditNote $document,
        string $action,
        int $expirationDays = self::DEFAULT_EXPIRATION_DAYS
    ): string {
        // Déterminer le type d'entité et la route correspondante
        $entityType = $this->getEntityType($document);
        $routeName = $this->getRouteNameForAction($entityType, $action);
        
        // Calculer le timestamp d'expiration
        $expires = time() + ($expirationDays * 24 * 60 * 60);
        
        // Générer les paramètres de base
        $params = [
            'id' => $document->getId(),
            'expires' => $expires,
        ];
        
        // Générer la signature
        $signature = $this->generateSignature($entityType, $document->getId(), $action, $expires);
        $params['signature'] = $signature;
        
        // Générer l'URL complète (absolue)
        return $this->urlGenerator->generate(
            $routeName,
            $params,
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }

    /**
     * Vérifie si un lien magique est valide
     * 
     * @param string $entityType Type d'entité : 'quote', 'amendment', 'invoice', 'credit_note'
     * @param int $documentId ID du document
     * @param string $action Action demandée
     * @param int $expires Timestamp d'expiration
     * @param string $signature Signature fournie dans l'URL
     * @return bool True si le lien est valide
     */
    public function verifySignature(
        string $entityType,
        int $documentId,
        string $action,
        int $expires,
        string $signature
    ): bool {
        // DEBUG: Log des paramètres reçus
        error_log("=== DEBUG MagicLink Verify ===");
        error_log("Entity Type: " . $entityType);
        error_log("Document ID: " . $documentId);
        error_log("Action: " . $action);
        error_log("Expires: " . $expires . " (currently: " . time() . ")");
        error_log("Signature reçue: " . $signature);
        error_log("APP_SECRET length: " . strlen($this->appSecret));
        error_log("APP_SECRET (10 first): " . substr($this->appSecret, 0, 10));
        
        // Vérifier l'expiration
        if (time() > $expires) {
            error_log("RESULT: Expiré");
            return false;
        }
        
        // Générer la signature attendue
        $expectedSignature = $this->generateSignature($entityType, $documentId, $action, $expires);
        error_log("Signature attendue: " . $expectedSignature);
        error_log("Signatures identiques: " . ($expectedSignature === $signature ? 'OUI' : 'NON'));
        
        // Comparer de manière sécurisée (protection contre timing attacks)
        $result = hash_equals($expectedSignature, $signature);
        error_log("RESULT: " . ($result ? 'VALID' : 'INVALID'));
        error_log("=== END DEBUG ===");
        
        return $result;
    }

    /**
     * Génère une signature HMAC pour sécuriser le lien
     * 
     * @param string $entityType
     * @param int $documentId
     * @param string $action
     * @param int $expires
     * @return string Signature hexadécimale
     */
    private function generateSignature(
        string $entityType,
        int $documentId,
        string $action,
        int $expires
    ): string {
        // Construire la chaîne à signer
        $data = sprintf(
            '%s:%d:%s:%d',
            $entityType,
            $documentId,
            $action,
            $expires
        );
        
        // Générer le HMAC avec SHA256
        return hash_hmac('sha256', $data, $this->appSecret);
    }

    /**
     * Détermine le type d'entité
     */
    private function getEntityType(Quote|Amendment|Invoice|CreditNote $document): string
    {
        return match (true) {
            $document instanceof Quote => 'quote',
            $document instanceof Amendment => 'amendment',
            $document instanceof Invoice => 'invoice',
            $document instanceof CreditNote => 'credit_note',
        };
    }

    /**
     * Retourne le nom de la route pour une action donnée
     */
    private function getRouteNameForAction(string $entityType, string $action): string
    {
        // Validation de l'action
        $validActions = ['view', 'sign', 'refuse', 'pay', 'apply'];
        if (!in_array($action, $validActions, true)) {
            throw new \InvalidArgumentException(sprintf(
                'Action "%s" invalide. Actions valides : %s',
                $action,
                implode(', ', $validActions)
            ));
        }
        
        // Construction du nom de route : public_{entityType}_{action}
        return sprintf('public_%s_%s', $entityType, $action);
    }

    /**
     * Génère un lien de visualisation (action par défaut)
     */
    public function generateViewLink(
        Quote|Amendment|Invoice|CreditNote $document,
        int $expirationDays = self::DEFAULT_EXPIRATION_DAYS
    ): string {
        return $this->generatePublicLink($document, 'view', $expirationDays);
    }

    /**
     * Génère un lien de signature (pour Devis/Avenants)
     */
    public function generateSignLink(
        Quote|Amendment $document,
        int $expirationDays = self::DEFAULT_EXPIRATION_DAYS
    ): string {
        return $this->generatePublicLink($document, 'sign', $expirationDays);
    }

    /**
     * Génère un lien de refus (pour Devis/Avenants)
     */
    public function generateRefuseLink(
        Quote|Amendment $document,
        int $expirationDays = self::DEFAULT_EXPIRATION_DAYS
    ): string {
        return $this->generatePublicLink($document, 'refuse', $expirationDays);
    }

    /**
     * Génère un lien de paiement (pour Factures)
     */
    public function generatePayLink(
        Invoice $document,
        int $expirationDays = self::DEFAULT_EXPIRATION_DAYS
    ): string {
        return $this->generatePublicLink($document, 'pay', $expirationDays);
    }

    /**
     * Génère un lien d'application (pour Avoirs)
     */
    public function generateApplyLink(
        CreditNote $document,
        int $expirationDays = self::DEFAULT_EXPIRATION_DAYS
    ): string {
        return $this->generatePublicLink($document, 'apply', $expirationDays);
    }
}


