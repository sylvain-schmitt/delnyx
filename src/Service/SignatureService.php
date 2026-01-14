<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Signature;
use App\Entity\Quote;
use App\Entity\Amendment;
use App\Repository\SignatureRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Service de gestion des signatures électroniques
 */
class SignatureService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private SignatureRepository $signatureRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Crée une nouvelle signature pour un document
     *
     * @param Quote|Amendment $document Document à signer
     * @param array $signatureData Données de signature selon la méthode
     * @param array $signerInfo Informations du signataire (name, email, ip, userAgent)
     * @param string $method Méthode de signature (text, draw, upload)
     * @return Signature
     */
    public function createSignature(
        Quote|Amendment $document,
        array $signatureData,
        array $signerInfo,
        string $method = 'text'
    ): Signature {
        // Déterminer le type de document
        $documentType = $document instanceof Quote ? 'quote' : 'amendment';

        // Créer l'entité Signature
        $signature = new Signature();
        $signature->setDocumentType($documentType);
        $signature->setDocumentId($document->getId());
        $signature->setSignerName($signerInfo['name']);
        $signature->setSignerEmail($signerInfo['email']);
        $signature->setSignatureMethod($method);
        $signature->setSignatureData($signatureData);
        
        // Informations de sécurité
        if (isset($signerInfo['ip'])) {
            $signature->setIpAddress($signerInfo['ip']);
        }
        
        if (isset($signerInfo['userAgent'])) {
            $signature->setUserAgent($signerInfo['userAgent']);
        }

        // TODO: Générer le hash du document (PDF) pour preuve d'intégrité
        // $signature->setDocumentHash($this->generateDocumentHash($document));

        // Persister
        $this->entityManager->persist($signature);
        $this->entityManager->flush();

        $this->logger->info('Signature created', [
            'signature_id' => $signature->getId(),
            'document_type' => $documentType,
            'document_id' => $document->getId(),
            'method' => $method,
        ]);

        return $signature;
    }

    /**
     * Vérifie si une signature est valide
     *
     * @param int $signatureId ID de la signature
     * @return bool
     */
    public function verifySignature(int $signatureId): bool
    {
        $signature = $this->entityManager->getRepository(Signature::class)->find($signatureId);

        if (!$signature) {
            return false;
        }

        // Vérifications de base
        if (empty($signature->getSignerName()) || empty($signature->getSignerEmail())) {
            return false;
        }

        // TODO: Vérifier le hash du document si disponible
        // if ($signature->getDocumentHash()) {
        //     return $this->verifyDocumentHash($signature);
        // }

        return true;
    }

    /**
     * Récupère toutes les signatures pour un document
     *
     * @param Quote|Amendment $document
     * @return Signature[]
     */
    public function getDocumentSignatures(Quote|Amendment $document): array
    {
        $documentType = $document instanceof Quote ? 'quote' : 'amendment';
        
        return $this->signatureRepository->findByDocument($documentType, $document->getId());
    }

    /**
     * Génère un certificat PDF de signature
     *
     * @param Signature $signature
     * @return Response PDF Response
     */
    public function exportSignatureCertificate(Signature $signature): Response
    {
        // TODO: Implémenter avec TCPDF dans Sprint 2
        // Générer un PDF avec :
        // - En-tête avec logo
        // - Informations du document
        // - Informations du signataire
        // - Date/heure de signature
        // - IP et User-Agent
        // - Hash du document
        // - Signature visuelle si draw/upload

        throw new \RuntimeException('Export PDF not implemented yet - Sprint 2');
    }

    /**
     * Génère le hash SHA-256 d'un document
     * 
     * @param Quote|Amendment $document
     * @return string
     */
    private function generateDocumentHash(Quote|Amendment $document): string
    {
        // TODO: Générer le PDF et calculer son hash
        // Pour l'instant, on hash juste les données essentielles
        $data = sprintf(
            '%s-%d-%s',
            $document instanceof Quote ? 'quote' : 'amendment',
            $document->getId(),
            $document->getNumero()
        );

        return hash('sha256', $data);
    }
}
