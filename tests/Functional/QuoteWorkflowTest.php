<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Quote;
use App\Entity\QuoteStatus;
use App\Entity\Client;
use App\Entity\User;
use App\Repository\QuoteRepository;
use App\Service\QuoteService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Tests fonctionnels pour valider le workflow légal des devis
 * 
 * @package App\Tests\Functional
 */
class QuoteWorkflowTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private QuoteService $quoteService;
    private AuthorizationCheckerInterface $authorizationChecker;
    private User $testUser;

    protected function setUp(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get('doctrine')->getManager();
        $this->quoteService = $container->get(QuoteService::class);
        $this->authorizationChecker = $container->get('security.authorization_checker');

        // Créer et authentifier un utilisateur de test avec un email unique
        $this->testUser = $this->createTestUser();
        $client->loginUser($this->testUser);
    }

    /**
     * Récupère un utilisateur de test existant en base
     * Utilise le premier utilisateur trouvé en base (peu importe le rôle)
     */
    private function createTestUser(): User
    {
        // Vider le cache Doctrine pour s'assurer de voir les utilisateurs existants
        $this->entityManager->clear();

        // Chercher le premier utilisateur existant
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy([], ['id' => 'ASC']);

        // Si un utilisateur existe, on l'utilise
        if ($user) {
            // S'assurer qu'il a au moins ROLE_USER (ajouté automatiquement par Symfony)
            // et lui donner ROLE_ADMIN si nécessaire pour les tests
            $roles = $user->getRoles();
            if (!in_array('ROLE_ADMIN', $roles)) {
                $roles[] = 'ROLE_ADMIN';
                $user->setRoles($roles);
                $this->entityManager->flush();
            }
            return $user;
        }

        // Sinon, on en crée un nouveau avec un email unique
        $email = 'test' . time() . '_' . uniqid() . '@example.com';
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setEmail($email);
        $user->setRoles(['ROLE_ADMIN']);
        $hashedPassword = $passwordHasher->hashPassword($user, 'password');
        $user->setPassword($hashedPassword);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * Test : Un devis SIGNED ne peut pas être modifié
     */
    public function testSignedQuoteCannotBeModified(): void
    {
        // Créer un devis signé
        $quote = $this->createTestQuote();
        $quote->setStatut(QuoteStatus::SIGNED);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        // Vérifier que l'édition est refusée
        $this->assertFalse(
            $this->authorizationChecker->isGranted('QUOTE_EDIT', $quote),
            'Un devis signé ne doit pas pouvoir être modifié'
        );
    }

    /**
     * Test : Un devis DRAFT peut être envoyé
     */
    public function testDraftQuoteCanBeSent(): void
    {
        $quote = $this->createTestQuote();
        $quote->setStatut(QuoteStatus::DRAFT);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        // Vérifier que l'envoi est autorisé
        $this->assertTrue(
            $this->authorizationChecker->isGranted('QUOTE_SEND', $quote),
            'Un devis DRAFT doit pouvoir être envoyé'
        );

        // Envoyer le devis
        $this->quoteService->send($quote);
        $this->entityManager->refresh($quote);

        // Vérifier que le statut est passé à SENT
        $this->assertEquals(
            QuoteStatus::SENT,
            $quote->getStatut(),
            'Le statut doit être SENT après envoi'
        );
    }

    /**
     * Test : Un devis SENT peut être accepté
     */
    public function testSentQuoteCanBeAccepted(): void
    {
        $quote = $this->createTestQuote();
        $quote->setStatut(QuoteStatus::SENT);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        // Vérifier que l'acceptation est autorisée
        $this->assertTrue(
            $this->authorizationChecker->isGranted('QUOTE_ACCEPT', $quote),
            'Un devis SENT doit pouvoir être accepté'
        );

        // Accepter le devis
        $this->quoteService->accept($quote);
        $this->entityManager->refresh($quote);

        // Vérifier que le statut est passé à ACCEPTED
        $this->assertEquals(
            QuoteStatus::ACCEPTED,
            $quote->getStatut(),
            'Le statut doit être ACCEPTED après acceptation'
        );
        $this->assertNotNull($quote->getDateAcceptation(), 'La date d\'acceptation doit être renseignée');
    }

    /**
     * Test : Un devis SENT ou ACCEPTED peut être signé
     */
    public function testSentOrAcceptedQuoteCanBeSigned(): void
    {
        // Test avec SENT
        $quote = $this->createTestQuote();
        $quote->setStatut(QuoteStatus::SENT);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        $this->assertTrue(
            $this->authorizationChecker->isGranted('QUOTE_SIGN', $quote),
            'Un devis SENT doit pouvoir être signé'
        );

        $this->quoteService->sign($quote);
        $this->entityManager->refresh($quote);

        $this->assertEquals(
            QuoteStatus::SIGNED,
            $quote->getStatut(),
            'Le statut doit être SIGNED après signature'
        );
        $this->assertNotNull($quote->getDateSignature(), 'La date de signature doit être renseignée');
    }

    /**
     * Test : Un devis SIGNED peut générer une facture
     */
    public function testSignedQuoteCanGenerateInvoice(): void
    {
        $quote = $this->createTestQuote();
        $quote->setStatut(QuoteStatus::SIGNED);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        // Vérifier que la génération de facture est autorisée
        $this->assertTrue(
            $this->authorizationChecker->isGranted('QUOTE_GENERATE_INVOICE', $quote),
            'Un devis SIGNED doit pouvoir générer une facture'
        );
    }

    /**
     * Test : Un devis ACCEPTED ne peut PAS générer une facture (pas contractuel)
     */
    public function testAcceptedQuoteCannotGenerateInvoice(): void
    {
        $quote = $this->createTestQuote();
        $quote->setStatut(QuoteStatus::ACCEPTED);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        // Vérifier que la génération de facture est refusée
        $this->assertFalse(
            $this->authorizationChecker->isGranted('QUOTE_GENERATE_INVOICE', $quote),
            'Un devis ACCEPTED ne doit PAS pouvoir générer une facture (pas contractuel)'
        );
    }

    /**
     * Test : Un devis peut être annulé depuis DRAFT, SENT ou ACCEPTED
     */
    public function testQuoteCanBeCancelled(): void
    {
        $quote = $this->createTestQuote();
        $quote->setStatut(QuoteStatus::SENT);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        $this->assertTrue(
            $this->authorizationChecker->isGranted('QUOTE_CANCEL', $quote),
            'Un devis SENT doit pouvoir être annulé'
        );

        $this->quoteService->cancel($quote, 'Test d\'annulation');
        $this->entityManager->refresh($quote);

        $this->assertEquals(
            QuoteStatus::CANCELLED,
            $quote->getStatut(),
            'Le statut doit être CANCELLED après annulation'
        );
    }

    /**
     * Test : Un devis SIGNED ne peut pas être annulé
     */
    public function testSignedQuoteCannotBeCancelled(): void
    {
        $quote = $this->createTestQuote();
        $quote->setStatut(QuoteStatus::SIGNED);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        $this->assertFalse(
            $this->authorizationChecker->isGranted('QUOTE_CANCEL', $quote),
            'Un devis SIGNED ne doit pas pouvoir être annulé'
        );
    }

    /**
     * Test : Un devis peut être refusé depuis SENT ou ACCEPTED
     */
    public function testQuoteCanBeRefused(): void
    {
        $quote = $this->createTestQuote();
        $quote->setStatut(QuoteStatus::SENT);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        $this->assertTrue(
            $this->authorizationChecker->isGranted('QUOTE_REFUSE', $quote),
            'Un devis SENT doit pouvoir être refusé'
        );

        $this->quoteService->refuse($quote, 'Test de refus');
        $this->entityManager->refresh($quote);

        $this->assertEquals(
            QuoteStatus::REFUSED,
            $quote->getStatut(),
            'Le statut doit être REFUSED après refus'
        );
    }

    /**
     * Test : Un devis peut être expiré automatiquement si dateValidite dépassée
     */
    public function testQuoteCanBeExpired(): void
    {
        $quote = $this->createTestQuote();
        $quote->setStatut(QuoteStatus::SENT);
        // Date de validité dans le passé
        $quote->setDateValidite(new \DateTime('-1 day'));
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        $expired = $this->quoteService->expireIfNeeded($quote);
        $this->entityManager->refresh($quote);

        $this->assertTrue($expired, 'Le devis doit être expiré');
        $this->assertEquals(
            QuoteStatus::EXPIRED,
            $quote->getStatut(),
            'Le statut doit être EXPIRED après expiration'
        );
    }

    /**
     * Test : Un devis SIGNED ne peut pas être expiré
     */
    public function testSignedQuoteCannotBeExpired(): void
    {
        $quote = $this->createTestQuote();
        $quote->setStatut(QuoteStatus::SIGNED);
        $quote->setDateValidite(new \DateTime('-1 day'));
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        $expired = $this->quoteService->expireIfNeeded($quote);
        $this->entityManager->refresh($quote);

        $this->assertFalse($expired, 'Un devis SIGNED ne doit pas pouvoir être expiré');
        $this->assertEquals(
            QuoteStatus::SIGNED,
            $quote->getStatut(),
            'Le statut doit rester SIGNED'
        );
    }

    /**
     * Test : Un devis ne peut pas être envoyé sans lignes
     */
    public function testQuoteCannotBeSentWithoutLines(): void
    {
        $quote = $this->createTestQuote();
        // Supprimer toutes les lignes
        foreach ($quote->getLines() as $line) {
            $quote->removeLine($line);
        }
        $quote->setStatut(QuoteStatus::DRAFT);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Un devis ne peut pas être envoyé sans ligne');

        $this->quoteService->send($quote);
    }

    /**
     * Test : Un devis ne peut pas être signé sans lignes
     */
    public function testQuoteCannotBeSignedWithoutLines(): void
    {
        $quote = $this->createTestQuote();
        // Supprimer toutes les lignes
        foreach ($quote->getLines() as $line) {
            $quote->removeLine($line);
        }
        $quote->setStatut(QuoteStatus::SENT);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        // Le voter vérifie validateCanBeSigned() et refuse la permission si le devis n'a pas de lignes
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->expectExceptionMessage('Vous n\'avez pas la permission de signer ce devis.');

        $this->quoteService->sign($quote);
    }

    /**
     * Crée un devis de test avec une ligne
     */
    private function createTestQuote(): Quote
    {
        // Créer un client de test avec un email unique
        $client = new Client();
        $client->setNom('Test');
        $client->setPrenom('Client');
        $client->setEmail('client' . time() . '_' . uniqid() . '@example.com');
        $this->entityManager->persist($client);

        // Créer un devis de test
        $quote = new Quote();
        $quote->setClient($client);
        $quote->setCompanyId('test-company-id-12345'); // company_id obligatoire
        $quote->setStatut(QuoteStatus::DRAFT);
        $quote->setTauxTVA('20.00');
        $quote->setMontantHT('100.00');
        $quote->setMontantTTC('120.00');
        $quote->setDateValidite(new \DateTime('+30 days'));

        // Ajouter une ligne de test
        $line = new \App\Entity\QuoteLine();
        $line->setDescription('Service de test');
        $line->setQuantity(1); // int, pas string
        $line->setUnitPrice('100.00');
        $line->setTotalHt('100.00');
        $quote->addLine($line);

        return $quote;
    }

    protected function tearDown(): void
    {
        // Nettoyer la base de données après chaque test
        // On ne supprime PAS l'utilisateur de test car on le réutilise
        if ($this->entityManager->getConnection()->isConnected()) {
            // Supprimer dans l'ordre pour respecter les contraintes de clés étrangères
            $this->entityManager->getConnection()->executeStatement('DELETE FROM amendment_lines');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM amendments');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM quote_lines');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM quotes');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM clients');
        }

        parent::tearDown();
    }
}
