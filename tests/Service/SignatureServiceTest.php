<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Signature;
use App\Entity\Quote;
use App\Entity\Amendment;
use App\Entity\QuoteStatus;
use App\Repository\SignatureRepository;
use App\Service\SignatureService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SignatureServiceTest extends TestCase
{
    private SignatureService $signatureService;
    private EntityManagerInterface $entityManager;
    private SignatureRepository $signatureRepository;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->signatureRepository = $this->createMock(SignatureRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->signatureService = new SignatureService(
            $this->entityManager,
            $this->signatureRepository,
            $this->logger
        );
    }

    public function testCreateSignatureForQuote(): void
    {
        // Arrange
        $quote = new Quote();
        $quote->setNumero('DEV-2025-001');
        
        // Utiliser reflection pour définir l'ID (car setId n'existe pas)
        $reflection = new \ReflectionClass($quote);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($quote, 1);

        $signatureData = ['name' => 'John Doe'];
        $signerInfo = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'ip' => '127.0.0.1',
            'userAgent' => 'Mozilla/5.0',
        ];

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Signature::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $signature = $this->signatureService->createSignature(
            $quote,
            $signatureData,
            $signerInfo,
            'text'
        );

        // Assert
        $this->assertInstanceOf(Signature::class, $signature);
        $this->assertEquals('quote', $signature->getDocumentType());
        $this->assertEquals(1, $signature->getDocumentId());
        $this->assertEquals('John Doe', $signature->getSignerName());
        $this->assertEquals('john@example.com', $signature->getSignerEmail());
        $this->assertEquals('text', $signature->getSignatureMethod());
        $this->assertEquals($signatureData, $signature->getSignatureData());
        $this->assertEquals('127.0.0.1', $signature->getIpAddress());
        $this->assertEquals('Mozilla/5.0', $signature->getUserAgent());
    }

    public function testCreateSignatureForAmendment(): void
    {
        // Arrange
        $amendment = new Amendment();
        $amendment->setNumero('DEV-2025-001-A1');
        
        $reflection = new \ReflectionClass($amendment);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($amendment, 2);

        $signatureData = ['data' => 'base64encodedimage...'];
        $signerInfo = [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
        ];

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $signature = $this->signatureService->createSignature(
            $amendment,
            $signatureData,
            $signerInfo,
            'draw'
        );

        // Assert
        $this->assertEquals('amendment', $signature->getDocumentType());
        $this->assertEquals(2, $signature->getDocumentId());
        $this->assertEquals('draw', $signature->getSignatureMethod());
    }

    public function testVerifySignatureValid(): void
    {
        // Arrange
        $signature = new Signature();
        $signature->setSignerName('John Doe');
        $signature->setSignerEmail('john@example.com');

        $reflection = new \ReflectionClass($signature);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($signature, 1);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($signature);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Signature::class)
            ->willReturn($repository);

        // Act
        $isValid = $this->signatureService->verifySignature(1);

        // Assert
        $this->assertTrue($isValid);
    }

    public function testVerifySignatureInvalidMissingData(): void
    {
        // Arrange
        $signature = new Signature();
        $signature->setSignerName(''); // Nom vide

        $reflection = new \ReflectionClass($signature);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($signature, 1);

        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($signature);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Signature::class)
            ->willReturn($repository);

        // Act
        $isValid = $this->signatureService->verifySignature(1);

        // Assert
        $this->assertFalse($isValid);
    }

    public function testVerifySignatureNotFound(): void
    {
        // Arrange
        $repository = $this->createMock(\Doctrine\ORM\EntityRepository::class);
        $repository
            ->expects($this->once())
            ->method('find')
            ->with(999)
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('getRepository')
            ->with(Signature::class)
            ->willReturn($repository);

        // Act
        $isValid = $this->signatureService->verifySignature(999);

        // Assert
        $this->assertFalse($isValid);
    }

    public function testGetDocumentSignaturesForQuote(): void
    {
        // Arrange
        $quote = new Quote();
        $reflection = new \ReflectionClass($quote);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($quote, 1);

        $signatures = [
            $this->createConfiguredMock(Signature::class, ['getDocumentType' => 'quote']),
            $this->createConfiguredMock(Signature::class, ['getDocumentType' => 'quote']),
        ];

        $this->signatureRepository
            ->expects($this->once())
            ->method('findByDocument')
            ->with('quote', 1)
            ->willReturn($signatures);

        // Act
        $result = $this->signatureService->getDocumentSignatures($quote);

        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals($signatures, $result);
    }

    public function testGetDocumentSignaturesForAmendment(): void
    {
        // Arrange
        $amendment = new Amendment();
        $reflection = new \ReflectionClass($amendment);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($amendment, 5);

        $this->signatureRepository
            ->expects($this->once())
            ->method('findByDocument')
            ->with('amendment', 5)
            ->willReturn([]);

        // Act
        $result = $this->signatureService->getDocumentSignatures($amendment);

        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testExportSignatureCertificateThrowsException(): void
    {
        // Arrange
        $signature = new Signature();

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Export PDF not implemented yet - Sprint 2');

        // Act
        $this->signatureService->exportSignatureCertificate($signature);
    }
}
