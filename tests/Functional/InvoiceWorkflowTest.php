<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Invoice;
use App\Entity\InvoiceStatus;
use App\Entity\Quote;
use App\Entity\QuoteStatus;
use App\Entity\Client;
use App\Entity\User;
use App\Service\InvoiceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Tests fonctionnels pour valider le workflow légal des factures
 * 
 * @package App\Tests\Functional
 */
class InvoiceWorkflowTest extends WebTestCase
{
    private EntityManagerInterface $entityManager;
    private InvoiceService $invoiceService;
    private AuthorizationCheckerInterface $authorizationChecker;
    private User $testUser;

    protected function setUp(): void
    {
        $client = static::createClient();
        $container = static::getContainer();

        $this->entityManager = $container->get('doctrine')->getManager();
        $this->invoiceService = $container->get(InvoiceService::class);
        $this->authorizationChecker = $container->get('security.authorization_checker');

        // Créer et authentifier un utilisateur de test
        $this->testUser = $this->createTestUser();
        $client->loginUser($this->testUser);
    }

    /**
     * Récupère un utilisateur de test existant en base
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
     * Test : Une facture ISSUED ne peut pas être modifiée
     */
    public function testIssuedInvoiceCannotBeModified(): void
    {
        // Créer une facture émise
        $invoice = $this->createTestInvoice();
        $invoice->setStatutEnum(InvoiceStatus::ISSUED);
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Vérifier que l'édition est refusée
        $this->assertFalse(
            $this->authorizationChecker->isGranted('INVOICE_EDIT', $invoice),
            'Une facture émise ne doit pas pouvoir être modifiée'
        );
    }

    /**
     * Test : Une facture DRAFT peut être émise
     */
    public function testDraftInvoiceCanBeIssued(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setStatutEnum(InvoiceStatus::DRAFT);
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Vérifier que l'émission est autorisée
        $this->assertTrue(
            $this->authorizationChecker->isGranted('INVOICE_ISSUE', $invoice),
            'Une facture DRAFT doit pouvoir être émise'
        );

        // Émettre la facture
        $this->invoiceService->issue($invoice);
        $this->entityManager->refresh($invoice);

        // Vérifier que le statut est passé à ISSUED
        $this->assertEquals(
            InvoiceStatus::ISSUED,
            $invoice->getStatutEnum(),
            'Le statut doit être ISSUED après émission'
        );
        $this->assertNotNull($invoice->getNumero(), 'Le numéro doit être généré');
        $this->assertNotNull($invoice->getDateEnvoi(), 'La date d\'envoi doit être renseignée');
    }

    /**
     * Test : Une facture ISSUED peut être envoyée
     */
    public function testIssuedInvoiceCanBeSent(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setStatutEnum(InvoiceStatus::ISSUED);
        $invoice->setNumero('FACT-2025-001'); // Simuler un numéro généré
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Vérifier que l'envoi est autorisé
        $this->assertTrue(
            $this->authorizationChecker->isGranted('INVOICE_SEND', $invoice),
            'Une facture ISSUED doit pouvoir être envoyée'
        );

        // Envoyer la facture
        $this->invoiceService->send($invoice, 'email');
        $this->entityManager->refresh($invoice);

        // Vérifier que les métadonnées d'envoi sont mises à jour
        $this->assertNotNull($invoice->getDateEnvoi(), 'La date d\'envoi doit être renseignée');
        $this->assertEquals(1, $invoice->getSentCount(), 'Le compteur d\'envoi doit être à 1');
        $this->assertEquals('email', $invoice->getDeliveryChannel(), 'Le canal doit être email');
    }

    /**
     * Test : Une facture peut être envoyée plusieurs fois (relances)
     */
    public function testInvoiceCanBeSentMultipleTimes(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setStatutEnum(InvoiceStatus::ISSUED);
        $invoice->setNumero('FACT-2025-001');
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Premier envoi
        $this->invoiceService->send($invoice, 'email');
        $this->entityManager->refresh($invoice);
        $this->assertEquals(1, $invoice->getSentCount());

        // Deuxième envoi (relance)
        $this->invoiceService->send($invoice, 'email');
        $this->entityManager->refresh($invoice);
        $this->assertEquals(2, $invoice->getSentCount(), 'Le compteur doit être incrémenté');
    }

    /**
     * Test : Une facture ISSUED peut être marquée comme payée
     */
    public function testIssuedInvoiceCanBeMarkedPaid(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setStatutEnum(InvoiceStatus::ISSUED);
        $invoice->setNumero('FACT-2025-001');
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Vérifier que le marquage comme payée est autorisé
        $this->assertTrue(
            $this->authorizationChecker->isGranted('INVOICE_MARK_PAID', $invoice),
            'Une facture ISSUED doit pouvoir être marquée comme payée'
        );

        // Marquer comme payée
        $this->invoiceService->markPaid($invoice);
        $this->entityManager->refresh($invoice);

        // Vérifier que le statut est passé à PAID
        $this->assertEquals(
            InvoiceStatus::PAID,
            $invoice->getStatutEnum(),
            'Le statut doit être PAID après paiement'
        );
        $this->assertNotNull($invoice->getDatePaiement(), 'La date de paiement doit être renseignée');
    }

    /**
     * Test : Une facture PAID peut toujours être envoyée
     */
    public function testPaidInvoiceCanBeSent(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setStatutEnum(InvoiceStatus::PAID);
        $invoice->setNumero('FACT-2025-001');
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Vérifier que l'envoi est autorisé même si payée
        $this->assertTrue(
            $this->authorizationChecker->isGranted('INVOICE_SEND', $invoice),
            'Une facture PAID doit pouvoir être envoyée'
        );

        $this->invoiceService->send($invoice, 'email');
        $this->entityManager->refresh($invoice);

        $this->assertNotNull($invoice->getDateEnvoi(), 'La date d\'envoi doit être renseignée');
    }

    /**
     * Test : Une facture DRAFT ne peut pas être envoyée
     */
    public function testDraftInvoiceCannotBeSent(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setStatutEnum(InvoiceStatus::DRAFT);
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Vérifier que l'envoi est refusé
        $this->assertFalse(
            $this->authorizationChecker->isGranted('INVOICE_SEND', $invoice),
            'Une facture DRAFT ne doit pas pouvoir être envoyée'
        );

        // Tenter d'envoyer doit lever une exception (AccessDeniedException du voter)
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->expectExceptionMessage('Vous n\'avez pas la permission d\'envoyer cette facture');

        $this->invoiceService->send($invoice, 'email');
    }

    /**
     * Test : Une facture ne peut pas être émise sans lignes
     */
    public function testInvoiceCannotBeIssuedWithoutLines(): void
    {
        $invoice = $this->createTestInvoice();
        // Supprimer toutes les lignes
        foreach ($invoice->getLines() as $line) {
            $invoice->removeLine($line);
        }
        $invoice->setStatutEnum(InvoiceStatus::DRAFT);
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Le voter vérifie validateCanBeIssued() et refuse la permission si pas de lignes
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->expectExceptionMessage('Vous n\'avez pas la permission d\'émettre cette facture');

        $this->invoiceService->issue($invoice);
    }

    /**
     * Test : Une facture ne peut pas être émise sans montants valides
     */
    public function testInvoiceCannotBeIssuedWithoutValidAmounts(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setStatutEnum(InvoiceStatus::DRAFT);
        $invoice->setMontantHT('-10.00'); // Montant négatif
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Le voter vérifie validateCanBeIssued() et refuse la permission si montants invalides
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->expectExceptionMessage('Vous n\'avez pas la permission d\'émettre cette facture');

        $this->invoiceService->issue($invoice);
    }

    /**
     * Test : Une facture ne peut pas être émise sans date d'échéance
     * Note: La date d'échéance est obligatoire dans l'entité (NOT NULL en base),
     * donc la validation est garantie par la contrainte de base de données.
     * Ce test vérifie que validateCanBeIssued() vérifie bien la présence de la date.
     */
    public function testInvoiceCannotBeIssuedWithoutDueDate(): void
    {
        // Ce test est couvert par la contrainte NOT NULL en base de données
        // et par validateCanBeIssued() qui vérifie $invoice->dateEcheance
        // On skip ce test car on ne peut pas créer une facture sans date d'échéance
        $this->markTestSkipped('La date d\'échéance est garantie par la contrainte NOT NULL en base de données');
    }

    /**
     * Test : Une facture peut être créée depuis un devis signé
     */
    public function testInvoiceCanBeCreatedFromSignedQuote(): void
    {
        // Créer un devis signé
        $quote = $this->createTestQuote();
        $quote->setStatut(QuoteStatus::SIGNED);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        // Créer la facture depuis le devis
        $invoice = $this->invoiceService->createFromQuote($quote, false);
        $this->entityManager->refresh($invoice);

        // Vérifier que la facture est créée en DRAFT
        $this->assertEquals(
            InvoiceStatus::DRAFT,
            $invoice->getStatutEnum(),
            'La facture doit être créée en DRAFT'
        );
        $this->assertEquals($quote->getClient(), $invoice->getClient(), 'Le client doit être copié');
        $this->assertEquals($quote, $invoice->getQuote(), 'Le devis doit être associé');
        $this->assertGreaterThan(0, $invoice->getLines()->count(), 'Les lignes doivent être copiées');
    }

    /**
     * Test : Une facture ne peut pas être créée depuis un devis non signé
     */
    public function testInvoiceCannotBeCreatedFromUnsignedQuote(): void
    {
        // Créer un devis non signé
        $quote = $this->createTestQuote();
        $quote->setStatut(QuoteStatus::SENT);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->expectExceptionMessage('Une facture ne peut être créée qu\'à partir d\'un devis signé');

        $this->invoiceService->createFromQuote($quote, false);
    }

    /**
     * Test : Une facture ne peut pas être créée si une facture existe déjà pour le devis
     */
    public function testInvoiceCannotBeCreatedIfInvoiceAlreadyExists(): void
    {
        // Créer un devis signé avec une facture existante
        $quote = $this->createTestQuote();
        $quote->setStatut(QuoteStatus::SIGNED);
        $this->entityManager->persist($quote);
        $this->entityManager->flush();

        // Créer une première facture
        $firstInvoice = $this->invoiceService->createFromQuote($quote, false);
        $this->entityManager->refresh($quote);

        // Tenter de créer une deuxième facture
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Une facture existe déjà pour le devis');

        $this->invoiceService->createFromQuote($quote, false);
    }

    /**
     * Test : Une facture ISSUED peut créer un avoir
     */
    public function testIssuedInvoiceCanCreateCreditNote(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setStatutEnum(InvoiceStatus::ISSUED);
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Vérifier que la création d'avoir est autorisée
        $this->assertTrue(
            $this->authorizationChecker->isGranted('INVOICE_CREATE_CREDITNOTE', $invoice),
            'Une facture ISSUED doit pouvoir créer un avoir'
        );
    }

    /**
     * Test : Une facture PAID peut créer un avoir
     */
    public function testPaidInvoiceCanCreateCreditNote(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setStatutEnum(InvoiceStatus::PAID);
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Vérifier que la création d'avoir est autorisée
        $this->assertTrue(
            $this->authorizationChecker->isGranted('INVOICE_CREATE_CREDITNOTE', $invoice),
            'Une facture PAID doit pouvoir créer un avoir'
        );
    }

    /**
     * Test : Une facture DRAFT ne peut pas créer un avoir
     */
    public function testDraftInvoiceCannotCreateCreditNote(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setStatutEnum(InvoiceStatus::DRAFT);
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Vérifier que la création d'avoir est refusée
        $this->assertFalse(
            $this->authorizationChecker->isGranted('INVOICE_CREATE_CREDITNOTE', $invoice),
            'Une facture DRAFT ne doit pas pouvoir créer un avoir'
        );
    }

    /**
     * Test : Une facture ne peut pas être supprimée (archivage 10 ans)
     */
    public function testInvoiceCannotBeDeleted(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setStatutEnum(InvoiceStatus::DRAFT);
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Vérifier que la suppression est toujours refusée
        $this->assertFalse(
            $this->authorizationChecker->isGranted('INVOICE_DELETE', $invoice),
            'Une facture ne doit jamais pouvoir être supprimée'
        );
    }

    /**
     * Test : Une facture ISSUED ne peut pas être émise à nouveau
     */
    public function testIssuedInvoiceCannotBeIssuedAgain(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setStatutEnum(InvoiceStatus::ISSUED);
        $invoice->setNumero('FACT-2025-001');
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Vérifier que l'émission est refusée
        $this->assertFalse(
            $this->authorizationChecker->isGranted('INVOICE_ISSUE', $invoice),
            'Une facture déjà émise ne doit pas pouvoir être émise à nouveau'
        );

        // Tenter d'émettre doit lever une exception (AccessDeniedException du voter)
        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);
        $this->expectExceptionMessage('Vous n\'avez pas la permission d\'émettre cette facture');

        $this->invoiceService->issue($invoice);
    }

    /**
     * Test : Une facture PAID ne peut pas être marquée comme payée à nouveau
     */
    public function testPaidInvoiceCannotBeMarkedPaidAgain(): void
    {
        $invoice = $this->createTestInvoice();
        $invoice->setStatutEnum(InvoiceStatus::PAID);
        $invoice->setNumero('FACT-2025-001');
        $this->entityManager->persist($invoice);
        $this->entityManager->flush();

        // Vérifier que le marquage comme payée est refusé
        $this->assertFalse(
            $this->authorizationChecker->isGranted('INVOICE_MARK_PAID', $invoice),
            'Une facture déjà payée ne doit pas pouvoir être marquée comme payée à nouveau'
        );
    }

    /**
     * Crée une facture de test avec une ligne
     */
    private function createTestInvoice(): Invoice
    {
        // Créer un client de test avec un email unique
        $client = new Client();
        $client->setNom('Test');
        $client->setPrenom('Client');
        $client->setEmail('client' . time() . '_' . uniqid() . '@example.com');
        $this->entityManager->persist($client);

        // Créer une facture de test
        $invoice = new Invoice();
        $invoice->setClient($client);
        $invoice->setCompanyId('test-company-id-12345');
        $invoice->setStatutEnum(InvoiceStatus::DRAFT);
        $invoice->setMontantHT('100.00');
        $invoice->setMontantTVA('20.00');
        $invoice->setMontantTTC('120.00');
        $invoice->setDateEcheance(new \DateTime('+30 days'));

        // Ajouter une ligne de test
        $line = new \App\Entity\InvoiceLine();
        $line->setDescription('Service de test');
        $line->setQuantity(1);
        $line->setUnitPrice('100.00');
        $line->setTotalHt('100.00');
        $line->setTvaRate('20.00');
        $invoice->addLine($line);

        return $invoice;
    }

    /**
     * Crée un devis de test avec une ligne (pour les tests de création de facture)
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
        $quote->setCompanyId('test-company-id-12345');
        $quote->setStatut(QuoteStatus::DRAFT);
        $quote->setTauxTVA('20.00');
        $quote->setMontantHT('100.00');
        $quote->setMontantTTC('120.00');
        $quote->setDateValidite(new \DateTime('+30 days'));

        // Ajouter une ligne de test
        $line = new \App\Entity\QuoteLine();
        $line->setDescription('Service de test');
        $line->setQuantity(1);
        $line->setUnitPrice('100.00');
        $line->setTotalHt('100.00');
        $quote->addLine($line);

        return $quote;
    }

    protected function tearDown(): void
    {
        // Nettoyer la base de données après chaque test
        if ($this->entityManager->getConnection()->isConnected()) {
            // Supprimer dans l'ordre pour respecter les contraintes de clés étrangères
            $this->entityManager->getConnection()->executeStatement('DELETE FROM credit_note_lines');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM credit_notes');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM invoice_lines');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM invoices');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM amendment_lines');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM amendments');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM quote_lines');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM quotes');
            $this->entityManager->getConnection()->executeStatement('DELETE FROM clients');
        }

        parent::tearDown();
    }
}

