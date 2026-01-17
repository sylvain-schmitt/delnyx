<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ReminderRule;
use App\Form\ReminderRuleType;
use App\Repository\ReminderRepository;
use App\Repository\ReminderRuleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/admin/reminder', name: 'admin_reminder_')]
#[IsGranted('ROLE_USER')]
class ReminderController extends AbstractController
{
    public function __construct(
        private ReminderRuleRepository $reminderRuleRepository,
        private ReminderRepository $reminderRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Liste des règles de relance
     */
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $rules = $this->reminderRuleRepository->findBy([], ['daysAfterDue' => 'ASC', 'ordre' => 'ASC']);

        return $this->render('admin/reminder/index.html.twig', [
            'rules' => $rules,
        ]);
    }

    /**
     * Historique des relances envoyées
     */
    #[Route('/history', name: 'history')]
    public function history(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $reminders = $this->reminderRepository->findRecentReminders($limit, $offset);
        $total = $this->reminderRepository->countAll();
        $totalPages = (int) ceil($total / $limit);

        return $this->render('admin/reminder/history.html.twig', [
            'reminders' => $reminders,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_reminders' => $total,
        ]);
    }

    /**
     * Créer une nouvelle règle de relance
     */
    #[Route('/new', name: 'new')]
    public function new(Request $request): Response
    {
        $rule = new ReminderRule();

        // Pré-remplir le company_id
        $user = $this->getUser();
        if ($user && method_exists($user, 'getEmail')) {
            $namespace = Uuid::fromString('6ba7b810-9dad-11d1-80b4-00c04fd430c8');
            $companyId = Uuid::v5($namespace, $user->getEmail())->toString();
            $rule->setCompanyId($companyId);
        }

        $form = $this->createForm(ReminderRuleType::class, $rule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($rule);
            $this->entityManager->flush();

            $this->addFlash('success', 'Règle de relance créée avec succès');
            return $this->redirectToRoute('admin_reminder_index');
        }

        return $this->render('admin/reminder/form.html.twig', [
            'form' => $form,
            'rule' => $rule,
            'title' => 'Nouvelle règle de relance',
        ]);
    }

    /**
     * Modifier une règle de relance
     */
    #[Route('/{id}/edit', name: 'edit', requirements: ['id' => '\d+'])]
    public function edit(Request $request, ReminderRule $rule): Response
    {
        $form = $this->createForm(ReminderRuleType::class, $rule);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $rule->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            $this->addFlash('success', 'Règle de relance modifiée avec succès');
            return $this->redirectToRoute('admin_reminder_index');
        }

        return $this->render('admin/reminder/form.html.twig', [
            'form' => $form,
            'rule' => $rule,
            'title' => 'Modifier la règle : ' . $rule->getName(),
        ]);
    }

    /**
     * Supprimer une règle de relance
     */
    #[Route('/{id}/delete', name: 'delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, ReminderRule $rule): Response
    {
        if (!$this->isCsrfTokenValid('delete_reminder_rule_' . $rule->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('admin_reminder_index');
        }

        $this->entityManager->remove($rule);
        $this->entityManager->flush();

        $this->addFlash('success', 'Règle de relance supprimée');
        return $this->redirectToRoute('admin_reminder_index');
    }

    /**
     * Activer/Désactiver une règle
     */
    #[Route('/{id}/toggle', name: 'toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggle(Request $request, ReminderRule $rule): Response
    {
        if (!$this->isCsrfTokenValid('toggle_reminder_rule_' . $rule->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token CSRF invalide');
            return $this->redirectToRoute('admin_reminder_index');
        }

        $rule->setIsActive(!$rule->isActive());
        $rule->setUpdatedAt(new \DateTime());
        $this->entityManager->flush();

        $status = $rule->isActive() ? 'activée' : 'désactivée';
        $this->addFlash('success', sprintf('Règle "%s" %s', $rule->getName(), $status));

        return $this->redirectToRoute('admin_reminder_index');
    }
}
