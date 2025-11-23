<?php

namespace App\Tests\Unit;

use App\Entity\CreditNote;
use App\Entity\CreditNoteLine;
use App\Service\CreditNoteNumberGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\AbstractQuery;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class CreditNoteComplianceTest extends TestCase
{
    public function testCreditNoteLineDeltaCalculation(): void
    {
        $line = new CreditNoteLine();
        
        // Cas 1: Correction de prix (100 -> 80)
        $line->setOldValue('100.00');
        $line->setNewValue('80.00');
        // recalculateDelta est appelé automatiquement par les setters
        
        $this->assertEquals('-20.00', $line->getDelta());
        // On ne teste pas totalHt ici car il est calculé via recalculateTotalHt (qui utilise unitPrice/quantity)
        // et non via recalculateDelta directement.

        // Cas 2: Correction de prix (100 -> 120) - Augmentation
        $line->setOldValue('100.00');
        $line->setNewValue('120.00');
        
        $this->assertEquals('20.00', $line->getDelta());
        
        // Cas 3: Annulation complète (100 -> 0)
        $line->setOldValue('100.00');
        $line->setNewValue('0.00');
        
        $this->assertEquals('-100.00', $line->getDelta());
    }

    public function testCreditNoteNumberGeneratorFormat(): void
    {
        // Mock EntityManager and dependencies
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $connection = $this->createMock(Connection::class);
        $repository = $this->createMock(EntityRepository::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        // Fix: Mock Query instead of AbstractQuery
        $query = $this->createMock(\Doctrine\ORM\Query::class);

        $entityManager->method('getConnection')->willReturn($connection);
        $entityManager->method('getRepository')->willReturn($repository);
        
        // Setup QueryBuilder mock chain
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('setMaxResults')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);
        $query->method('setLockMode')->willReturnSelf();
        
        // Mock result: Last credit note was AV-2025-0001
        $lastCreditNote = new CreditNote();
        $lastCreditNote->setNumber('AV-' . date('Y') . '-0001');
        $query->method('getOneOrNullResult')->willReturn($lastCreditNote);

        $generator = new CreditNoteNumberGenerator($entityManager);
        $creditNote = new CreditNote();

        $number = $generator->generate($creditNote);
        
        // Expect AV-YYYY-0002
        $expected = 'AV-' . date('Y') . '-0002';
        $this->assertEquals($expected, $number);
    }
}
