<?php

namespace App\Controller\Public;

use App\Entity\Quote;
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
use App\Service\EmailService;
use App\Service\DepositService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

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
        private \App\Repository\CompanySettingsRepository $companySettingsRepository,
        private \App\Repository\UserRepository $userRepository,
        private \App\Service\StripeService $stripeService,
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
        SignatureService $signatureService,
        DepositService $depositService,
        EmailService $emailService,
        InvoiceService $invoiceService
    ): Response {
        $quote = $this->verifyAndGetDocument($repository, $id, 'quote', 'sign', $request);

        // Vérifier que le devis peut être signé (DRAFT ou SENT)
        if (!in_array($quote->getStatut(), [QuoteStatus::DRAFT, QuoteStatus::SENT], true)) {
            // Si déjà signé (ou autre état), générer un nouveau magic link pour la vue
            $viewLink = $this->magicLinkService->generateViewLink($quote);
            return $this->redirect($viewLink);
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

                // Notification à l'administrateur
                try {
                    $emailService->sendQuoteSignedNotificationAdmin($quote);
                } catch (\Exception $e) {
                    error_log('Erreur notification admin devis signé: ' . $e->getMessage());
                }

                // === AUTOMATISATION ACCOMPTE ===
                // Si le devis a un pourcentage d'acompte > 0, créer automatiquement l'accompte et envoyer l'email
                $this->createAndSendDepositIfNeeded($quote, $depositService, $emailService);

                // === AUTOMATISATION FACTURE (Si pas d'acompte) ===
                if ($quote->getAcomptePourcentage() <= 0) {
                    try {
                        // Créer la facture
                        $invoice = $invoiceService->createFromQuote($quote, true); // true = issue immediately

                        // Envoyer la facture par email
                        $invoiceService->send($invoice);

                        $this->addFlash('success', 'La facture a été générée et envoyée automatiquement.');
                    } catch (\Exception $e) {
                        error_log('Erreur création automatique facture: ' . $e->getMessage());
                        // On ne bloque pas le flux de signature, mais on log l'erreur
                    }
                }

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
            // Si déjà signé/refusé, générer un nouveau magic link pour la vue
            $viewLink = $this->magicLinkService->generateViewLink($quote);
            return $this->redirect($viewLink);
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
        SignatureService $signatureService,
        \App\Service\AmendmentBillingService $billingService
    ): Response {
        $amendment = $this->verifyAndGetDocument($repository, $id, 'amendment', 'sign', $request);

        // Vérifier que l'avenant peut être signé (DRAFT ou SENT)
        if (!in_array($amendment->getStatut(), [AmendmentStatus::DRAFT, AmendmentStatus::SENT], true)) {
            // Si déjà signé (ou annulé), générer un nouveau magic link pour la vue
            $viewLink = $this->magicLinkService->generateViewLink($amendment);
            return $this->redirect($viewLink);
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

                // Facturation automatique : créer facture (positif) ou avoir (négatif)
                try {
                    $billingResult = $billingService->handleSignedAmendment($amendment);

                    if ($billingResult instanceof Invoice) {
                        $this->addFlash('success', sprintf(
                            'Facture complémentaire %s créée automatiquement.',
                            $billingResult->getNumero()
                        ));
                    } elseif ($billingResult instanceof CreditNote) {
                        $this->addFlash('success', sprintf(
                            'Avoir %s créé automatiquement.',
                            $billingResult->getNumber()
                        ));
                    }
                } catch (\Exception $e) {
                    // Log l'erreur mais ne bloque pas la signature
                    error_log('Erreur facturation auto avenant: ' . $e->getMessage());
                }

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
            // Si déjà signé/annulé, générer un nouveau magic link pour la vue
            $viewLink = $this->magicLinkService->generateViewLink($amendment);
            return $this->redirect($viewLink);
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
        PaymentService $paymentService,
        EmailService $emailService
    ): Response {
        $invoice = $this->verifyAndGetDocument($repository, $id, 'invoice', 'pay', $request);

        // Récupérer les paramètres de l'entreprise
        $companySettings = null;
        if ($invoice->getCompanyId()) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($invoice->getCompanyId());
        }

        // Fallback sur le premier si non trouvé
        if (!$companySettings) {
            $companySettings = $this->companySettingsRepository->findOneBy([]);
        }

        if (!in_array($invoice->getStatutEnum(), [InvoiceStatus::ISSUED, InvoiceStatus::SENT], true)) {
            throw new AccessDeniedHttpException('Cette facture ne peut plus être payée.');
        }

        if ($request->isMethod('POST')) {
            $provider = $request->request->get('provider'); // 'stripe' ou 'manual'

            if ($provider === 'manual') {
                if (!$request->request->get('confirm_manual')) {
                    $this->addFlash('error', 'Veuillez confirmer votre engagement de paiement.');
                } else {
                    // 1. Envoyer notification à l'admin
                    $emailService->sendManualPaymentNotification($invoice);

                    // 2. Envoyer instructions au client
                    $emailService->sendManualPaymentInstructions($invoice);

                    // 3. Afficher page de confirmation
                    $owner = $this->userRepository->findOneBy([]);
                    $ownerName = $owner ? $owner->getNomComplet() : 'Sylvain Schmitt';

                    return $this->render('public/invoice/manual_payment_confirmation.html.twig', [
                        'invoice' => $invoice,
                        'companySettings' => $companySettings,
                        'ownerName' => $ownerName,
                    ]);
                }
            } elseif ($provider === 'stripe') {
                // ... Stripe Logic ...
                if (!$request->request->get('confirm_payment')) {
                    $this->addFlash('error', 'Veuillez accepter les conditions de paiement.');
                } else {
                    // Générer les URLs de retour pour Stripe
                    $successUrl = $this->generateUrl('public_invoice_payment_success', [
                        'id' => $id,
                        'expires' => $request->query->get('expires'),
                        'signature' => $request->query->get('signature'),
                        'session_id' => '{CHECKOUT_SESSION_ID}', // Placeholder Stripe
                    ], UrlGeneratorInterface::ABSOLUTE_URL);

                    $cancelUrl = $this->generateUrl('public_invoice_pay', [ // Retour à "pay" au lieu de "view" pour réessayer
                        'id' => $id,
                        'expires' => $request->query->get('expires'),
                        'signature' => $request->query->get('signature'),
                    ], UrlGeneratorInterface::ABSOLUTE_URL);

                    try {
                        // Créer la session Stripe et rediriger
                        $checkoutUrl = $paymentService->createPaymentIntent($invoice, $successUrl, $cancelUrl);

                        // Utiliser RedirectResponse directement pour éviter toute modification de l'URL
                        return new \Symfony\Component\HttpFoundation\RedirectResponse($checkoutUrl, 303);
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Erreur lors de l\'initialisation du paiement : ' . $e->getMessage());
                    }
                }
            }
        }

        // Récupérer le nom de l'utilisateur (propriétaire)
        $owner = $this->userRepository->findOneBy([]);
        $ownerName = $owner ? $owner->getNomComplet() : 'Sylvain Schmitt';

        return $this->render('public/invoice/pay.html.twig', [
            'invoice' => $invoice,
            'companySettings' => $companySettings,
            'ownerName' => $ownerName,
        ]);
    }

    #[Route('/public/invoice/{id}/payment/success', name: 'public_invoice_payment_success')]
    public function paymentSuccess(
        int $id,
        Request $request,
        InvoiceRepository $repository,
        InvoiceService $invoiceService,
        EmailService $emailService
    ): Response {
        // On utilise l'action 'pay' car c'est la suite logique et la signature est la même
        $invoice = $this->verifyAndGetDocument($repository, $id, 'invoice', 'pay', $request);

        // Calculer le montant qui a probablement été payé (le solde avant marquage PAID)
        $amountPaid = (float) $invoice->getBalanceDue();
        if ($amountPaid <= 0 && $invoice->getStatutEnum() === InvoiceStatus::PAID) {
            // Déjà payé, on essaye de retrouver le dernier paiement réussi
            $lastPayment = $this->entityManager->getRepository(\App\Entity\Payment::class)->findOneBy(['invoice' => $invoice], ['id' => 'DESC']);
            $amountPaid = $lastPayment ? $lastPayment->getAmountInEuros() : (float)$invoice->getMontantTTC();
        }

        // Si la facture n'est pas encore marquée comme payée, on la met à jour
        // (Fallback si le webhook n'a pas encore été reçu ou en environnement local)
        if ($invoice->getStatutEnum() !== InvoiceStatus::PAID) {
            try {
                $invoiceService->markPaidByExternalPayment($invoice, $amountPaid);
                $this->entityManager->flush();
            } catch (\Exception $e) {
                // Log l'erreur mais ne bloque pas l'affichage de la page de succès
                error_log('Error marking invoice as paid: ' . $e->getMessage());
            }
        }

        // Fallback: Synchroniser l'abonnement si c'est un paiement Stripe Subscription
        // Utile si le webhook n'a pas (encore) été reçu
        try {
            $payment = $this->entityManager->getRepository(\App\Entity\Payment::class)->findOneBy(['invoice' => $invoice], ['id' => 'DESC']);
            if ($payment && $payment->getProviderPaymentId() && str_starts_with($payment->getProviderPaymentId(), 'cs_')) {
                $session = $this->stripeService->retrieveSession($payment->getProviderPaymentId());
                if ($session && $session->mode === 'subscription') {
                    error_log('DEBUG: Fallback sync for session ' . $session->id);
                    $this->stripeService->createOrUpdateSubscriptionFromSession($session);
                }
            }
        } catch (\Exception $e) {
            error_log('Error syncing subscription fallback: ' . $e->getMessage());
        }

        return $this->render('public/invoice/payment_success.html.twig', [
            'invoice' => $invoice,
            'amountPaid' => $amountPaid,
        ]);
    }

    // ==================== ACCOMPTES (Deposit) ====================

    #[Route('/public/deposit/{id}/pay', name: 'public_deposit_pay')]
    public function payDeposit(
        int $id,
        Request $request,
        \App\Repository\DepositRepository $repository,
        \App\Service\DepositService $depositService,
        \App\Service\EmailService $emailService
    ): Response {
        $deposit = $this->verifyAndGetDocument($repository, $id, 'deposit', 'pay', $request);

        // Vérifier que l'accompte est en attente
        if ($deposit->getStatus() !== \App\Entity\DepositStatus::PENDING) {
            return $this->render('public/deposit/already_paid.html.twig', [
                'deposit' => $deposit,
            ]);
        }

        // Récupérer les paramètres de l'entreprise
        $companySettings = null;
        if ($deposit->getQuote()->getCompanyId()) {
            $companySettings = $this->companySettingsRepository->findByCompanyId($deposit->getQuote()->getCompanyId());
        }

        if (!$companySettings) {
            $companySettings = $this->companySettingsRepository->findOneBy([]);
        }

        // Si c'est une soumission de formulaire
        if ($request->isMethod('POST')) {
            $provider = $request->request->get('provider', 'stripe');

            if ($provider === 'manual') {
                if (!$request->request->get('confirm_payment')) {
                    $this->addFlash('error', 'Veuillez confirmer votre engagement de paiement.');
                } else {
                    // 0. Créer la facture d'acompte SI non existante (ISSUED)
                    $depositService->getOrCreateDepositInvoice($deposit, \App\Entity\InvoiceStatus::ISSUED);

                    // 1. Envoyer notification à l'admin
                    $emailService->sendManualDepositPaymentNotification($deposit);

                    // 2. Envoyer instructions au client
                    $emailService->sendManualDepositPaymentInstructions($deposit);

                    // 3. Afficher page de confirmation
                    $owner = $this->userRepository->findOneBy([]);
                    $ownerName = $owner ? $owner->getNomComplet() : 'Sylvain Schmitt';

                    return $this->render('public/deposit/manual_payment_confirmation.html.twig', [
                        'deposit' => $deposit,
                        'quote' => $deposit->getQuote(),
                        'companySettings' => $companySettings,
                        'ownerName' => $ownerName,
                    ]);
                }
            } else {
                // Stripe provider
                if (!$request->request->get('confirm_payment')) {
                    $this->addFlash('error', 'Veuillez accepter les conditions de paiement.');
                } else {
                    // Construire les URLs de retour avec les mêmes paramètres de signature
                    $expires = $request->query->getInt('expires');
                    $signature = $request->query->get('signature');

                    $successUrl = $this->generateUrl('public_deposit_payment_success', [
                        'id' => $id,
                        'expires' => $expires,
                        'signature' => $signature,
                    ], UrlGeneratorInterface::ABSOLUTE_URL);

                    $cancelUrl = $this->generateUrl('public_deposit_pay', [
                        'id' => $id,
                        'expires' => $expires,
                        'signature' => $signature,
                    ], UrlGeneratorInterface::ABSOLUTE_URL);

                    try {
                        $checkoutUrl = $depositService->createPaymentSession($deposit, $successUrl, $cancelUrl);
                        return new \Symfony\Component\HttpFoundation\RedirectResponse($checkoutUrl, 303);
                    } catch (\Exception $e) {
                        $this->addFlash('error', 'Erreur lors de l\'initialisation du paiement : ' . $e->getMessage());
                    }
                }
            }
        }

        return $this->render('public/deposit/pay.html.twig', [
            'deposit' => $deposit,
            'quote' => $deposit->getQuote(),
            'companySettings' => $companySettings,
        ]);
    }

    #[Route('/public/deposit/{id}/payment/success', name: 'public_deposit_payment_success')]
    public function depositPaymentSuccess(
        int $id,
        Request $request,
        \App\Repository\DepositRepository $repository,
        \App\Service\DepositService $depositService,
        \App\Service\EmailService $emailService,
        \App\Service\StripeService $stripeService
    ): Response {
        $deposit = $this->verifyAndGetDocument($repository, $id, 'deposit', 'pay', $request);

        // Fallback: Synchroniser l'ID client Stripe si manquant (utile si webhook local échoue)
        $client = $deposit->getQuote()->getClient();
        if ($client && !$client->getStripeCustomerId() && $deposit->getStripeSessionId()) {
            try {
                $session = $stripeService->retrieveSession($deposit->getStripeSessionId());
                if ($session && $session->customer) {
                    $client->setStripeCustomerId((string) $session->customer);
                    $repository->getEntityManager()->flush();
                }
            } catch (\Exception $e) {
                // On continue même si la synchro échoue
                error_log('Erreur synchro fallback Stripe Customer ID: ' . $e->getMessage());
            }
        }

        // Marquer comme payé si pas déjà fait
        if ($deposit->getStatus() !== \App\Entity\DepositStatus::PAID) {
            try {
                $depositService->markPaid($deposit);

                // Envoyer l'email de confirmation
                $quote = $deposit->getQuote();
                if ($client && $client->getEmail()) {
                    $emailService->sendDepositPaymentConfirmation($deposit);
                }

                // === AUTOMATISATION FACTURE FINALE ===
                // Une fois l'acompte payé, on peut générer la facture finale si nécessaire
                if ($quote && $quote->getStatut() === QuoteStatus::SIGNED && !$quote->getInvoice()) {
                    try {
                        $finalInvoice = $this->invoiceService->createFromQuote($quote, true);
                        $this->invoiceService->send($finalInvoice);
                    } catch (\Exception $e) {
                        error_log('Erreur création automatique facture finale: ' . $e->getMessage());
                    }
                }
            } catch (\Exception $e) {
                error_log('Error marking deposit as paid: ' . $e->getMessage());
            }
        }

        return $this->render('public/deposit/payment_success.html.twig', [
            'deposit' => $deposit,
            'quote' => $deposit->getQuote(),
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

            // Rediriger vers la vue de l'avoir avec une NOUVELLE signature valide pour l'action 'view'
            $viewUrl = $this->magicLinkService->generatePublicLink($creditNote, 'view');
            return $this->redirect($viewUrl);
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

        // Récupération robuste de la signature (gère le mangling fréquent ;signature en email/proxy)
        $signature = $request->query->get('signature');
        if (!$signature) {
            $signature = $request->query->get(';signature') ?? '';
        }

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

    /**
     * Crée automatiquement un accompte et envoie l'email au client si nécessaire
     */
    private function createAndSendDepositIfNeeded(
        Quote $quote,
        DepositService $depositService,
        EmailService $emailService
    ): void {

        $acomptePourcentage = (float) $quote->getAcomptePourcentage();

        error_log("=== PUBLIC CONTROLLER DEPOSIT AUTO ===");
        error_log("Quote ID: " . $quote->getId());
        error_log("Acompte Pourcentage: " . $acomptePourcentage);

        if ($acomptePourcentage <= 0) {
            error_log("Condition FAILED - acomptePourcentage <= 0");
            return;
        }

        try {
            error_log("Creating deposit...");
            // Créer l'accompte
            $deposit = $depositService->createDeposit($quote, $acomptePourcentage);
            error_log("Deposit created with ID: " . $deposit->getId());

            // Générer le lien de paiement
            $paymentUrl = $this->magicLinkService->generateDepositPayLink($deposit);
            error_log("Payment URL: " . $paymentUrl);

            // Envoyer l'email au client
            $client = $quote->getClient();
            if ($client && $client->getEmail()) {
                error_log("Sending email to: " . $client->getEmail());
                $emailService->sendDepositRequest($deposit, $quote, $paymentUrl);
                error_log("Email sent successfully");
            }
        } catch (\Exception $e) {
            error_log("ERROR creating deposit: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
        }
    }
}
