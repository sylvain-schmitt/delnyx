<?php

declare(strict_types=1);

namespace App\Controller\Public;

use App\Entity\Appointment;
use App\Entity\Client;
use App\Form\BookingType;
use App\Repository\ClientRepository;
use App\Repository\CompanySettingsRepository;
use App\Service\Google\GoogleCalendarService;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PublicAppointmentController extends AbstractController
{
    public function __construct(
        private readonly GoogleCalendarService $googleCalendarService,
        private readonly CompanySettingsRepository $companySettingsRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClientRepository $clientRepository,
        private readonly EmailService $emailService
    ) {}

    #[Route('/booking', name: 'public_booking')]
    public function index(Request $request): Response
    {
        $settings = $this->companySettingsRepository->findOneBy([]);
        if (!$settings || !$settings->isGoogleCalendarEnabled()) {
            throw $this->createNotFoundException('Booking system is disabled.');
        }

        $dateStr = $request->query->get('date', (new \DateTime())->format('Y-m-d'));
        $date = new \DateTime($dateStr);

        $start = (clone $date)->setTime(0, 0);
        $end = (clone $date)->setTime(23, 59);

        // On ne permet pas de réserver dans le passé
        if ($start < new \DateTime('today')) {
            $date = new \DateTime('today');
            $start = (clone $date)->setTime(0, 0);
            $end = (clone $date)->setTime(23, 59);
        }

        $freeSlots = $this->googleCalendarService->getFreeSlots($settings, $start, $end);

        // Traduction des dates en français
        $fmt = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);
        $fmtDay = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::NONE, \IntlDateFormatter::NONE, null, null, 'EEE');

        $days = [];
        for ($i = 0; $i < 14; $i++) {
            $d = (new \DateTime())->modify("+$i days");
            $days[] = [
                'date' => $d->format('Y-m-d'),
                'label' => $fmt->format($d),
                'day' => $fmtDay->format($d),
                'active' => $d->format('Y-m-d') === $date->format('Y-m-d')
            ];
        }

        return $this->render('public/booking/index.html.twig', [
            'date' => $date,
            'dateLabel' => $fmt->format($date),
            'slots' => $freeSlots,
            'settings' => $settings,
            'days' => $days,
        ]);
    }

    #[Route('/booking/confirm', name: 'public_booking_confirm', methods: ['GET', 'POST'])]
    public function confirm(Request $request): Response
    {
        $startStr = $request->query->get('start');
        $endStr = $request->query->get('end');

        if (!$startStr || !$endStr) {
            return $this->redirectToRoute('public_booking');
        }

        $appointment = new Appointment();
        $appointment->setStartAt(new \DateTimeImmutable($startStr));
        $appointment->setEndAt(new \DateTimeImmutable($endStr));
        $appointment->setSummary('Rendez-vous client Delnyx');

        $form = $this->createForm(BookingType::class, $appointment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();

            // Trouver ou créer le client
            $client = $this->clientRepository->findOneBy(['email' => $email]);
            if (!$client) {
                $client = new Client();
                $client->setEmail($email);
                $client->setPrenom($form->get('firstName')->getData());
                $client->setNom($form->get('lastName')->getData());
                $client->setTelephone($form->get('phone')->getData());
                $this->entityManager->persist($client);
            }

            $appointment->setClient($client);
            $appointment->setSummary('RDV Delnyx - ' . $client->getNomComplet());

            $this->entityManager->persist($appointment);
            $this->entityManager->flush();

            // Sync vers Google
            try {
                $googleEventId = $this->googleCalendarService->createEvent($appointment);
                if ($googleEventId) {
                    $appointment->setGoogleEventId($googleEventId);
                    $this->entityManager->flush();
                }
            } catch (\Exception $e) {
                // On log l'erreur mais on ne bloque pas la confirmation
                error_log("BOOKING Google Sync Error: " . $e->getMessage());
            }

            // Envoi des emails
            try {
                error_log("BOOKING DEBUG: Attempting to send emails for appointment " . $appointment->getId());
                $this->emailService->sendAppointmentConfirmation($appointment);
                $this->emailService->sendAppointmentNotificationAdmin($appointment);
                error_log("BOOKING DEBUG: Emails sent successfully for appointment " . $appointment->getId());
            } catch (\Exception $e) {
                // On log l'erreur mais on ne bloque pas
                error_log("BOOKING Email Error: " . $e->getMessage());
            }

            $this->addFlash('success', 'Votre rendez-vous a bien été enregistré. Vous allez recevoir une confirmation par email.');
            return $this->redirectToRoute('public_booking_success');
        }

        $fmt = new \IntlDateFormatter('fr_FR', \IntlDateFormatter::LONG, \IntlDateFormatter::NONE);

        return $this->render('public/booking/confirm.html.twig', [
            'form' => $form->createView(),
            'appointment' => $appointment,
            'dateLabel' => $fmt->format($appointment->getStartAt()),
        ]);
    }

    #[Route('/booking/success', name: 'public_booking_success')]
    public function success(): Response
    {
        return $this->render('public/booking/success.html.twig');
    }
}
