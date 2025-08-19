<?php

namespace App\Controller;

use App\Form\ContactType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;

final class ContactController extends AbstractController
{
    #[Route('/contact', name: 'app_contact')]
    public function index(Request $request, MailerInterface $mailer): Response
    {
        $form = $this->createForm(ContactType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            try {
                // Email de notification pour vous
                $notificationEmail = (new Email())
                    ->from(new Address('contact@delnyx.fr', 'Site Delnyx'))
                    ->to('contact@delnyx.fr')
                    ->subject('Nouvelle demande de contact - ' . ($data['sujet'] ?: 'Sans sujet'))
                    ->html($this->renderView('emails/contact_notification.html.twig', [
                        'data' => $data,
                        'ip' => $request->getClientIp(),
                        'userAgent' => $request->headers->get('User-Agent'),
                    ]));

                // Email de confirmation pour le client
                $confirmationEmail = (new Email())
                    ->from(new Address('contact@delnyx.fr', 'Delnyx - Développeur Web'))
                    ->to($data['email'])
                    ->subject('Confirmation de réception - Votre demande chez Delnyx')
                    ->html($this->renderView('emails/contact_confirmation.html.twig', [
                        'data' => $data,
                    ]));

                // Envoi des emails
                $mailer->send($notificationEmail);
                $mailer->send($confirmationEmail);

                // Message de succès et redirection
                $this->addFlash('success', '✅ Votre message a bien été envoyé ! Vous recevrez une réponse sous 24h maximum.');

                return $this->redirectToRoute('app_contact');
            } catch (\Exception $e) {
                $this->addFlash('error', '❌ Une erreur est survenue lors de l\'envoi. Veuillez réessayer ou nous contacter directement.');
            }
        }

        return $this->render('contact/index.html.twig', [
            'contactForm' => $form,
        ]);
    }
}
