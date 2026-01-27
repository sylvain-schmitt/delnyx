<?php

namespace App\Controller\Admin;

use App\Entity\Deposit;
use App\Entity\DepositStatus;
use App\Service\EmailService;
use App\Service\MagicLinkService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/deposit')]
#[IsGranted('ROLE_USER')]
class DepositController extends AbstractController
{
    public function __construct(
        private readonly EmailService $emailService,
        private readonly MagicLinkService $magicLinkService,
        private readonly LoggerInterface $logger,
    ) {}

    #[Route('/{id}/resend-email', name: 'admin_deposit_resend_email', methods: ['POST'])]
    public function resendEmail(Request $request, Deposit $deposit): Response
    {
        if (!$this->isCsrfTokenValid('resend_email_' . $deposit->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $deposit->getQuote()->getId()]);
        }

        if ($deposit->getStatus() !== DepositStatus::PENDING) {
            $this->addFlash('error', 'Impossible de renvoyer l\'email pour un acompte qui n\'est pas en attente.');
            return $this->redirectToRoute('admin_quote_show', ['id' => $deposit->getQuote()->getId()]);
        }

        try {
            $quote = $deposit->getQuote();

            // Générer le lien de paiement magique
            $paymentLink = $this->magicLinkService->generateDepositPayLink($deposit);

            // S'assurer que la facture d'acompte existe
            $depositService = $this->container->get(\App\Service\DepositService::class);
            $depositService->getOrCreateDepositInvoice($deposit, \App\Entity\InvoiceStatus::ISSUED);

            // Renvoyer l'email
            $this->emailService->sendDepositRequest($deposit, $quote, $paymentLink);

            $this->addFlash('success', 'L\'email de demande d\'acompte a été renvoyé au client.');
            /** @var \App\Entity\User|null $user */
            $user = $this->getUser();
            $this->logger->info('Email demande acompte renvoyé manuellement', [
                'deposit_id' => $deposit->getId(),
                'user_id' => $user?->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Erreur renvoi email acompte', [
                'deposit_id' => $deposit->getId(),
                'error' => $e->getMessage()
            ]);
            $this->addFlash('error', 'Une erreur est survenue lors de l\'envoi de l\'email.');
        }

        return $this->redirectToRoute('admin_quote_show', ['id' => $deposit->getQuote()->getId()]);
    }
}
