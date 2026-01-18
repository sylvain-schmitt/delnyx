<?php

namespace App\Controller\Public;

use App\Entity\Quote;
use App\Entity\Amendment;
use App\Entity\Invoice;
use App\Entity\CreditNote;
use App\Entity\QuoteStatus;
use App\Entity\InvoiceStatus;
use App\Entity\AmendmentStatus;
use App\Entity\CreditNoteStatus;
use App\Repository\QuoteRepository;
use App\Repository\AmendmentRepository;
use App\Repository\InvoiceRepository;
use App\Repository\CreditNoteRepository;
use App\Service\MagicLinkService;
use App\Service\AuditService;
use App\Service\QuoteService;
use App\Service\InvoiceService;
use App\Service\SignatureService;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
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
    ) {}

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
        QuoteRepository $repository,
        SignatureService $signatureService
    ): Response {
        $quote = $this->verifyAndGetDocument($repository, $id, 'quote', 'sign', $request);

        // Vérifier que le devis peut être signé (DRAFT ou SENT)
        if (!in_array($quote->getStatut(), [QuoteStatus::DRAFT, QuoteStatus::SENT], true)) {
            throw new AccessDeniedHttpException('Ce devis ne peut plus être signé.');
        }

        if ($request->isMethod('POST')) {
            // Méthode de signature : uniquement draw
            $signatureMethod = 'draw';
            $signatureData = [];
            $isValid = false;

            // Validation de la signature dessinée
            $dataJson = $request->request->get('signature_data');
            if (!empty($dataJson)) {
                $data = json_decode($dataJson, true);
                if (isset($data['data']) && str_starts_with($data['data'], 'data:image/png')) {
                    $signatureData = $data;
                    $isValid = true;
                } else {
                    $this->addFlash('error', 'Données de signature invalides.');
                }
            } else {
                $this->addFlash('error', 'Veuillez dessiner votre signature.');
            }

            if ($isValid && !empty($signatureData)) {
                // Créer la signature avec SignatureService
                $signerInfo = [
                    'name' => $quote->getClient()->getNomComplet(),
                    'email' => $quote->getClient()->getEmail(),
                    'ip' => $request->getClientIp(),
                    'userAgent' => $request->headers->get('User-Agent'),
                ];

                $signature = $signatureService->createSignature(
                    $quote,
                    $signatureData,
                    $signerInfo,
                    $signatureMethod
                );

                // Changer le statut du devis à SIGNED directement (bypasse le Voter car magic link validé)
                $quote->setStatut(QuoteStatus::SIGNED);
                $quote->setDateSignature(new \DateTime());
                $quote->setSignatureClient($signerInfo['name']);

                // Enregistrer l'audit
                $this->auditService->log(
                    entityType: 'Quote',
                    entityId: $quote->getId(),
                    action: 'sign_by_client',
                    metadata: [
                        'method' => $signatureMethod,
                        'signature_id' => $signature->getId(),
                        'ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent')
                    ]
                );
                $this->entityManager->flush();

                $this->addFlash('success', 'Devis signé avec succès !');

                // Générer un nouveau magic link pour la vue (l'ancien était pour sign)
                $viewLink = $this->magicLinkService->generateViewLink($quote);
                return $this->redirect($viewLink);
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
        if ($quote->getStatut() !== QuoteStatus::SENT) {
            throw new AccessDeniedHttpException('Ce devis ne peut plus être refusé.');
        }

        if ($request->isMethod('POST')) {
            $refuseReason = $request->request->get('refuse_reason', '');

            // Changer le statut à REFUSED directement (bypasse le Voter car magic link validé)
            $quote->setStatut(QuoteStatus::REFUSED);

            // Enregistrer la raison dans les notes si fournie
            if (!empty($refuseReason)) {
                $currentNotes = $quote->getNotes() ?? '';
                $quote->setNotes(
                    ($currentNotes ? $currentNotes . "\n\n" : '') .
                        "Refus le " . date('d/m/Y H:i') . " : " . $refuseReason
                );
            }

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

            // Générer un nouveau magic link pour la vue
            $viewLink = $this->magicLinkService->generateViewLink($quote);
            return $this->redirect($viewLink);
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
        AmendmentRepository $repository,
        SignatureService $signatureService
    ): Response {
        $amendment = $this->verifyAndGetDocument($repository, $id, 'amendment', 'sign', $request);

        // Vérifier que l'avenant peut être signé (DRAFT ou SENT)
        if (!in_array($amendment->getStatut(), [AmendmentStatus::DRAFT, AmendmentStatus::SENT], true)) {
            throw new AccessDeniedHttpException('Cet avenant ne peut plus être signé.');
        }

        if ($request->isMethod('POST')) {
            // Méthode de signature : uniquement draw
            $signatureMethod = 'draw';
            $signatureData = [];
            $isValid = false;

            // Validation de la signature dessinée
            $dataJson = $request->request->get('signature_data');
            if (!empty($dataJson)) {
                $data = json_decode($dataJson, true);
                if (isset($data['data']) && str_starts_with($data['data'], 'data:image/png')) {
                    $signatureData = $data;
                    $isValid = true;
                } else {
                    $this->addFlash('error', 'Données de signature invalides.');
                }
            } else {
                $this->addFlash('error', 'Veuillez dessiner votre signature.');
            }

            if ($isValid && !empty($signatureData)) {
                // Créer la signature avec SignatureService
                $client = $amendment->getQuote()->getClient();

                $signerInfo = [
                    'name' => $client->getNomComplet(),
                    'email' => $client->getEmail(),
                    'ip' => $request->getClientIp(),
                    'userAgent' => $request->headers->get('User-Agent'),
                ];

                $signature = $signatureService->createSignature(
                    $amendment,
                    $signatureData,
                    $signerInfo,
                    $signatureMethod
                );

                // Changer le statut de l'avenant à SIGNED
                $amendment->setStatut(AmendmentStatus::SIGNED);
                $amendment->setDateSignature(new \DateTimeImmutable());

                // Enregistrer l'audit
                $this->auditService->log(
                    entityType: 'Amendment',
                    entityId: $amendment->getId(),
                    action: 'sign_by_client',
                    metadata: [
                        'method' => $signatureMethod,
                        'signature_id' => $signature->getId(),
                        'ip' => $request->getClientIp(),
                        'user_agent' => $request->headers->get('User-Agent')
                    ]
                );
                $this->entityManager->flush();

                $this->addFlash('success', 'Avenant signé avec succès !');

                // Générer un nouveau magic link pour la vue
                $viewLink = $this->magicLinkService->generateViewLink($amendment);
                return $this->redirect($viewLink);
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

        if ($amendment->getStatut() !== AmendmentStatus::SENT) {
            throw new AccessDeniedHttpException('Cet avenant ne peut plus être refusé.');
        }

        if ($request->isMethod('POST')) {
            $refuseReason = $request->request->get('refuse_reason', '');

            $amendment->setStatut(AmendmentStatus::CANCELLED);

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

            // Générer un nouveau magic link pour la vue
            $viewLink = $this->magicLinkService->generateViewLink($amendment);
            return $this->redirect($viewLink);
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
        InvoiceRepository $repository,
        PaymentService $paymentService
    ): Response {
        $invoice = $this->verifyAndGetDocument($repository, $id, 'invoice', 'pay', $request);

        if (!in_array($invoice->getStatutEnum(), [InvoiceStatus::ISSUED, InvoiceStatus::SENT], true)) {
            throw new AccessDeniedHttpException('Cette facture ne peut plus être payée.');
        }

        if ($request->isMethod('POST')) {
            // Générer les URLs de retour pour Stripe
            $successUrl = $this->generateUrl('public_invoice_payment_success', [
                'id' => $id,
                'expires' => $request->query->get('expires'),
                'signature' => $request->query->get('signature'),
                'session_id' => '{CHECKOUT_SESSION_ID}', // Placeholder Stripe
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            $cancelUrl = $this->generateUrl('public_invoice_view', [
                'id' => $id,
                'expires' => $request->query->get('expires'),
                'signature' => $request->query->get('signature'),
            ], UrlGeneratorInterface::ABSOLUTE_URL);

            try {
                // Créer la session Stripe et rediriger
                $checkoutUrl = $paymentService->createPaymentIntent($invoice, $successUrl, $cancelUrl);
                return $this->redirect($checkoutUrl);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de l\'initialisation du paiement : ' . $e->getMessage());
            }
        }

        return $this->render('public/invoice/pay.html.twig', [
            'invoice' => $invoice,
        ]);
    }

    #[Route('/public/invoice/{id}/payment/success', name: 'public_invoice_payment_success')]
    public function paymentSuccess(
        int $id,
        Request $request,
        InvoiceRepository $repository
    ): Response {
        // On utilise l'action 'pay' car c'est la suite logique et la signature est la même
        $invoice = $this->verifyAndGetDocument($repository, $id, 'invoice', 'pay', $request);

        return $this->render('public/invoice/payment_success.html.twig', [
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

        // Si l'avoir est déjà appliqué (REFUNDED), on affiche la vue avec un message
        if ($creditNote->getStatutEnum() === CreditNoteStatus::REFUNDED) {
            $this->addFlash('info', 'Cet avoir a déjà été appliqué.');
            return $this->render('public/credit_note/view.html.twig', [
                'creditNote' => $creditNote,
            ]);
        }

        // Sinon, vérifier si le statut permet l'application (ISSUED ou SENT)
        // Utilisation de false pour la comparaison non stricte
        if (!in_array($creditNote->getStatutEnum(), [CreditNoteStatus::ISSUED, CreditNoteStatus::SENT], false)) {
            throw new AccessDeniedHttpException('Cet avoir ne peut plus être appliqué.');
        }

        if ($request->isMethod('POST')) {
            $creditNote->setStatutEnum(CreditNoteStatus::REFUNDED);

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
