<?php

namespace App\Controller\Public;

use App\Entity\Quote;
use App\Entity\Amendment;
use App\Entity\Invoice;
use App\Entity\CreditNote;
use App\Entity\QuoteStatus;
use App\Entity\InvoiceStatus;
use App\Entity\CreditNoteStatus;
use App\Repository\QuoteRepository;
use App\Repository\AmendmentRepository;
use App\Repository\InvoiceRepository;
use App\Repository\CreditNoteRepository;
use App\Service\MagicLinkService;
use App\Service\AuditService;
use App\Service\QuoteService;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Contrôleur pour les actions publiques via "Magic Links"
 * 
 * Permet aux clients d'effectuer des actions (consulter, signer, payer, refuser)
 * sans authentification, via des URLs signées envoyées par email.
 */
class PublicDocumentController extends AbstractController
{
    public function __construct(
        private MagicLinkService $magicLinkService,
        private AuditService $auditService,
        private EntityManagerInterface $entityManager,
        private QuoteService $quoteService,
        private InvoiceService $invoiceService,
    ) {
    }

    // ==================== DEVIS (Quote) ====================

    public function viewQuote(
        int $id,
        Request $request,
        QuoteRepository $repository
    ): Response {
        $quote = $this->verifyAndGetDocument($repository, $id, 'quote', 'view', $request);
        
        // Enregistrer l'audit de visualisation
        $this->auditService->log(
            entityType: 'Quote',
            entityId: $quote->getId(),
            action: 'view_by_client',
            metadata: [
                'method' => 'magic_link',
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]
        );
        $this->entityManager->flush();
        
        return $this->render('public/quote/view.html.twig', [
            'quote' => $quote,
        ]);
    }

    public function signQuote(
        int $id,
        Request $request,
        QuoteRepository $repository
    ): Response {
        $quote = $this->verifyAndGetDocument($repository, $id, 'quote', 'sign', $request);
        
        // Vérifier que le devis peut être signé
        if (!in_array($quote->getStatut(), [QuoteStatus::ISSUED, QuoteStatus::SENT], true)) {
            throw new AccessDeniedHttpException('Ce devis ne peut plus être signé.');
        }
        
        if ($request->isMethod('POST')) {
            // Traiter la signature
            $signatureName = $request->request->get('signature_name');
            $signatureDate = new \DateTimeImmutable();
            
            if (empty($signatureName)) {
                $this->addFlash('error', 'Veuillez saisir votre nom pour signer.');
            } else {
                // Changer le statut à SIGNED
                $this->quoteService->sign($quote, $signatureName);
                
                // Enregistrer les informations de signature
                $this->auditService->log(
                    entityType: 'Quote',
                    entityId: $quote->getId(),
                    action: 'sign_by_client',
                    metadata: [
                        'method' => 'magic_link',
                        'signature_name' => $signatureName,
                        'ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent')
                    ]
                );
                $this->entityManager->flush();
                
                $this->addFlash('success', 'Devis signé avec succès !');
                
                return $this->redirectToRoute('public_quote_view', [
                    'id' => $id,
                    'expires' => $request->query->get('expires'),
                    'signature' => $request->query->get('signature'),
                ]);
            }
        }
        
        return $this->render('public/quote/sign.html.twig', [
            'quote' => $quote,
        ]);
    }

    public function refuseQuote(
        int $id,
        Request $request,
        QuoteRepository $repository
    ): Response {
        $quote = $this->verifyAndGetDocument($repository, $id, 'quote', 'refuse', $request);
        
        // Vérifier que le devis peut être refusé
        if (!in_array($quote->getStatut(), [QuoteStatus::ISSUED, QuoteStatus::SENT], true)) {
            throw new AccessDeniedHttpException('Ce devis ne peut plus être refusé.');
        }
        
        if ($request->isMethod('POST')) {
            $refuseReason = $request->request->get('refuse_reason', '');
            
            // Changer le statut à REFUSED
            $this->quoteService->refuse($quote, $refuseReason);
            
            // Enregistrer la raison du refus
            $this->auditService->log(
                entityType: 'Quote',
                entityId: $quote->getId(),
                action: 'refuse_by_client',
                metadata: [
                    'method' => 'magic_link',
                    'refuse_reason' => $refuseReason,
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent')
                ]
            );
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Votre refus a été enregistré.');
            
            return $this->redirectToRoute('public_quote_view', [
                'id' => $id,
                'expires' => $request->query->get('expires'),
                'signature' => $request->query->get('signature'),
            ]);
        }
        
        return $this->render('public/quote/refuse.html.twig', [
            'quote' => $quote,
        ]);
    }

    // ==================== AVENANTS (Amendment) ====================

    public function viewAmendment(
        int $id,
        Request $request,
        AmendmentRepository $repository
    ): Response {
        $amendment = $this->verifyAndGetDocument($repository, $id, 'amendment', 'view', $request);
        
        $this->auditService->log(
            entityType: 'Amendment',
            entityId: $amendment->getId(),
            action: 'view_by_client',
            metadata: [
                'method' => 'magic_link',
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]
        );
        $this->entityManager->flush();
        
        return $this->render('public/amendment/view.html.twig', [
            'amendment' => $amendment,
        ]);
    }

    public function signAmendment(
        int $id,
        Request $request,
        AmendmentRepository $repository
    ): Response {
        $amendment = $this->verifyAndGetDocument($repository, $id, 'amendment', 'sign', $request);
        
        if (!in_array($amendment->getStatut(), [QuoteStatus::ISSUED, QuoteStatus::SENT], true)) {
            throw new AccessDeniedHttpException('Cet avenant ne peut plus être signé.');
        }
        
        if ($request->isMethod('POST')) {
            $signatureName = $request->request->get('signature_name');
            
            if (empty($signatureName)) {
                $this->addFlash('error', 'Veuillez saisir votre nom pour signer.');
            } else {
                $amendment->setStatut(QuoteStatus::SIGNED);
                
                $this->auditService->log(
                    entityType: 'Amendment',
                    entityId: $amendment->getId(),
                    action: 'sign_by_client',
                    metadata: [
                        'method' => 'magic_link',
                        'signature_name' => $signatureName,
                        'ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent')
                    ]
                );
                $this->entityManager->flush();
                
                $this->addFlash('success', 'Avenant signé avec succès !');
                
                return $this->redirectToRoute('public_amendment_view', [
                    'id' => $id,
                    'expires' => $request->query->get('expires'),
                    'signature' => $request->query->get('signature'),
                ]);
            }
        }
        
        return $this->render('public/amendment/sign.html.twig', [
            'amendment' => $amendment,
        ]);
    }

    public function refuseAmendment(
        int $id,
        Request $request,
        AmendmentRepository $repository
    ): Response {
        $amendment = $this->verifyAndGetDocument($repository, $id, 'amendment', 'refuse', $request);
        
        if (!in_array($amendment->getStatut(), [QuoteStatus::ISSUED, QuoteStatus::SENT], true)) {
            throw new AccessDeniedHttpException('Cet avenant ne peut plus être refusé.');
        }
        
        if ($request->isMethod('POST')) {
            $refuseReason = $request->request->get('refuse_reason', '');
            
            $amendment->setStatut(QuoteStatus::REFUSED);
            
            $this->auditService->log(
                entityType: 'Amendment',
                entityId: $amendment->getId(),
                action: 'refuse_by_client',
                metadata: [
                    'method' => 'magic_link',
                    'refuse_reason' => $refuseReason,
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent')
                ]
            );
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Votre refus a été enregistré.');
            
            return $this->redirectToRoute('public_amendment_view', [
                'id' => $id,
                'expires' => $request->query->get('expires'),
                'signature' => $request->query->get('signature'),
            ]);
        }
        
        return $this->render('public/amendment/refuse.html.twig', [
            'amendment' => $amendment,
        ]);
    }

    // ==================== FACTURES (Invoice) ====================

    public function viewInvoice(
        int $id,
        Request $request,
        InvoiceRepository $repository
    ): Response {
        $invoice = $this->verifyAndGetDocument($repository, $id, 'invoice', 'view', $request);
        
        $this->auditService->log(
            entityType: 'Invoice',
            entityId: $invoice->getId(),
            action: 'view_by_client',
            metadata: [
                'method' => 'magic_link',
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]
        );
        $this->entityManager->flush();
        
        return $this->render('public/invoice/view.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    public function payInvoice(
        int $id,
        Request $request,
        InvoiceRepository $repository
    ): Response {
        $invoice = $this->verifyAndGetDocument($repository, $id, 'invoice', 'pay', $request);
        
        if (!in_array($invoice->getStatutEnum(), [InvoiceStatus::ISSUED, InvoiceStatus::SENT], true)) {
            throw new AccessDeniedHttpException('Cette facture ne peut plus être marquée comme payée.');
        }
        
        if ($request->isMethod('POST')) {
            // Dans une vraie application, ici vous intégreriez un système de paiement (Stripe, PayPal, etc.)
            // Pour l'instant, on simule simplement le marquage comme payé
            
            $paymentMethod = $request->request->get('payment_method', 'non spécifié');
            
            // Changer le statut à PAID
            $this->invoiceService->markPaid($invoice);
            
            $this->auditService->log(
                entityType: 'Invoice',
                entityId: $invoice->getId(),
                action: 'pay_by_client',
                metadata: [
                    'method' => 'magic_link',
                    'payment_method' => $paymentMethod,
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent')
                ]
            );
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Paiement enregistré avec succès !');
            
            return $this->redirectToRoute('public_invoice_view', [
                'id' => $id,
                'expires' => $request->query->get('expires'),
                'signature' => $request->query->get('signature'),
            ]);
        }
        
        return $this->render('public/invoice/pay.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    // ==================== AVOIRS (CreditNote) ====================

    public function viewCreditNote(
        int $id,
        Request $request,
        CreditNoteRepository $repository
    ): Response {
        $creditNote = $this->verifyAndGetDocument($repository, $id, 'credit_note', 'view', $request);
        
        $this->auditService->log(
            entityType: 'CreditNote',
            entityId: $creditNote->getId(),
            action: 'view_by_client',
            metadata: [
                'method' => 'magic_link',
                'ip' => $request->getClientIp(),
                'user_agent' => $request->headers->get('User-Agent')
            ]
        );
        $this->entityManager->flush();
        
        return $this->render('public/credit_note/view.html.twig', [
            'creditNote' => $creditNote,
        ]);
    }

    public function applyCreditNote(
        int $id,
        Request $request,
        CreditNoteRepository $repository
    ): Response {
        $creditNote = $this->verifyAndGetDocument($repository, $id, 'credit_note', 'apply', $request);
        
        if (!in_array($creditNote->getStatutEnum(), [CreditNoteStatus::ISSUED, CreditNoteStatus::SENT], true)) {
            throw new AccessDeniedHttpException('Cet avoir ne peut plus être appliqué.');
        }
        
        if ($request->isMethod('POST')) {
            $creditNote->setStatutEnum(CreditNoteStatus::APPLIED);
            
            $this->auditService->log(
                entityType: 'CreditNote',
                entityId: $creditNote->getId(),
                action: 'apply_by_client',
                metadata: [
                    'method' => 'magic_link',
                    'ip' => $request->getClientIp(),
                    'user_agent' => $request->headers->get('User-Agent')
                ]
            );
            $this->entityManager->flush();
            
            $this->addFlash('success', 'Avoir appliqué avec succès !');
            
            return $this->redirectToRoute('public_credit_note_view', [
                'id' => $id,
                'expires' => $request->query->get('expires'),
                'signature' => $request->query->get('signature'),
            ]);
        }
        
        return $this->render('public/credit_note/apply.html.twig', [
            'creditNote' => $creditNote,
        ]);
    }

    // ==================== MÉTHODES PRIVÉES ====================

    /**
     * Vérifie la signature de l'URL et récupère le document
     * 
     * @template T
     * @param mixed $repository Repository de l'entité
     * @param int $id ID du document
     * @param string $entityType Type d'entité
     * @param string $action Action demandée
     * @param Request $request Requête HTTP
     * @return T L'entité trouvée
     * @throws NotFoundHttpException Si le document n'existe pas
     * @throws AccessDeniedHttpException Si la signature est invalide ou expirée
     */
    private function verifyAndGetDocument(
        $repository,
        int $id,
        string $entityType,
        string $action,
        Request $request
    ): mixed {
        // Récupérer les paramètres de signature
        $expires = (int) $request->query->get('expires', 0);
        $signature = $request->query->get('signature', '');
        
        // Vérifier la signature
        if (!$this->magicLinkService->verifySignature($entityType, $id, $action, $expires, $signature)) {
            if (time() > $expires) {
                throw new AccessDeniedHttpException('Ce lien a expiré. Veuillez contacter l\'entreprise pour obtenir un nouveau lien.');
            }
            throw new AccessDeniedHttpException('Lien invalide. Impossible de vérifier l\'authenticité de la demande.');
        }
        
        // Récupérer le document
        $document = $repository->find($id);
        if (!$document) {
            throw new NotFoundHttpException('Document introuvable.');
        }
        
        return $document;
    }
}


