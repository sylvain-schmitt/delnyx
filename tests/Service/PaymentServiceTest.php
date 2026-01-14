<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Payment;
use App\Entity\PaymentProvider;
use App\Entity\PaymentStatus;
use App\Entity\Invoice;
use App\Repository\PaymentRepository;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PaymentServiceTest extends TestCase
{
    private PaymentService $paymentService;
    private EntityManagerInterface $entityManager;
    private PaymentRepository $paymentRepository;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->paymentRepository = $this->createMock(PaymentRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->paymentService = new PaymentService(
            $this->entityManager,
            $this->paymentRepository,
            $this->logger,
            'sk_test_fake_key'
        );
    }

    public function testCreatePaymentIntentThrowsException(): void
    {
        // Arrange
        $invoice = new Invoice();

        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Stripe integration not implemented yet - Sprint 3');

        // Act
        $this->paymentService->createPaymentIntent($invoice);
    }

    public function testHandlePaymentSuccess(): void
    {
        // Arrange
        $invoice = new Invoice();
        $reflection = new \ReflectionClass($invoice);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($invoice, 10);

        $payment = new Payment();
        $payment->setInvoice($invoice);
        $payment->setStatus(PaymentStatus::PENDING);

        $this->paymentRepository
            ->expects($this->once())
            ->method('findByProviderPaymentId')
            ->with('pi_test_123')
            ->willReturn($payment);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $this->paymentService->handlePaymentSuccess('pi_test_123');

        // Assert
        $this->assertEquals(PaymentStatus::SUCCEEDED, $payment->getStatus());
        $this->assertNotNull($payment->getPaidAt());
    }

    public function testHandlePaymentSuccessPaymentNotFound(): void
    {
        // Arrange
        $this->paymentRepository
            ->expects($this->once())
            ->method('findByProviderPaymentId')
            ->with('pi_nonexistent')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Payment not found', ['payment_intent_id' => 'pi_nonexistent']);

        // Act
        $this->paymentService->handlePaymentSuccess('pi_nonexistent');

        // No exception should be thrown
        $this->assertTrue(true);
    }

    public function testHandlePaymentFailure(): void
    {
        // Arrange
        $invoice = new Invoice();
        $payment = new Payment();
        $payment->setInvoice($invoice);
        $payment->setStatus(PaymentStatus::PENDING);

        $this->paymentRepository
            ->expects($this->once())
            ->method('findByProviderPaymentId')
            ->with('pi_failed_123')
            ->willReturn($payment);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $this->paymentService->handlePaymentFailure('pi_failed_123', 'Insufficient funds');

        // Assert
        $this->assertEquals(PaymentStatus::FAILED, $payment->getStatus());
        $this->assertEquals('Insufficient funds', $payment->getFailureReason());
    }

    public function testHandlePaymentFailurePaymentNotFound(): void
    {
        // Arrange
        $this->paymentRepository
            ->expects($this->once())
            ->method('findByProviderPaymentId')
            ->with('pi_unknown')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->logger
            ->expects($this->once())
            ->method('error')
            ->with('Payment not found for failure', ['payment_intent_id' => 'pi_unknown']);

        // Act
        $this->paymentService->handlePaymentFailure('pi_unknown', 'Card declined');

        // No exception
        $this->assertTrue(true);
    }

    public function testRefundPaymentThrowsException(): void
    {
        // Assert
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Refund not implemented yet - Sprint 3');

        // Act
        $this->paymentService->refundPayment(1, 100.00);
    }

    public function testCreateManualPayment(): void
    {
        // Arrange
        $invoice = new Invoice();
        $invoice->setMontantTTC("1500.50");

        $reflection = new \ReflectionClass($invoice);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($invoice, 5);

        $data = [
            'method' => 'virement',
            'reference' => 'VIR-2025-001',
            'paid_at' => '2025-11-30 10:00:00',
            'proof_filename' => 'virement.pdf',
        ];

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(Payment::class));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $payment = $this->paymentService->createManualPayment($invoice, $data);

        // Assert
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertEquals($invoice, $payment->getInvoice());
        $this->assertEquals(150050, $payment->getAmount()); // 1500.50 * 100
        $this->assertEquals(1500.50, $payment->getAmountInEuros());
        $this->assertEquals('EUR', $payment->getCurrency());
        $this->assertEquals(PaymentProvider::MANUAL, $payment->getProvider());
        $this->assertEquals(PaymentStatus::SUCCEEDED, $payment->getStatus());
        $this->assertNotNull($payment->getPaidAt());
        
        $metadata = $payment->getMetadata();
        $this->assertEquals('virement', $metadata['payment_method']);
        $this->assertEquals('VIR-2025-001', $metadata['reference']);
        $this->assertEquals('virement.pdf', $metadata['proof_filename']);
    }

    public function testCreateManualPaymentWithDefaults(): void
    {
        // Arrange
        $invoice = new Invoice();
        $invoice->setMontantTTC("500.00");

        $reflection = new \ReflectionClass($invoice);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($invoice, 3);

        $data = []; // Données minimales

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        // Act
        $payment = $this->paymentService->createManualPayment($invoice, $data);

        // Assert
        $this->assertEquals(50000, $payment->getAmount()); // 500.00 * 100
        $metadata = $payment->getMetadata();
        $this->assertEquals('virement', $metadata['payment_method']);
        $this->assertNull($metadata['reference']);
        $this->assertNull($metadata['proof_filename']);
    }

    public function testPaymentAmountConversion(): void
    {
        // Arrange
        $payment = new Payment();

        // Test setAmountFromEuros
        $payment->setAmountFromEuros(123.45);
        $this->assertEquals(12345, $payment->getAmount());
        $this->assertEquals(123.45, $payment->getAmountInEuros());

        // Test rounding
        $payment->setAmountFromEuros(99.999); // Should round to 100.00
        $this->assertEquals(10000, $payment->getAmount());
        $this->assertEquals(100.00, $payment->getAmountInEuros());
    }

    public function testPaymentFormattedAmount(): void
    {
        // Arrange
        $payment = new Payment();
        $payment->setAmountFromEuros(1234.56);

        // Act
        $formatted = $payment->getFormattedAmount();

        // Assert
        $this->assertEquals('1 234,56 €', $formatted);
    }

    public function testPaymentStatusChangesUpdateTimestamps(): void
    {
        // Arrange
        $payment = new Payment();
        $payment->setStatus(PaymentStatus::PENDING);

        $this->assertNull($payment->getPaidAt());
        $this->assertNull($payment->getRefundedAt());

        // Act - Set to SUCCEEDED
        $payment->setStatus(PaymentStatus::SUCCEEDED);

        // Assert
        $this->assertNotNull($payment->getPaidAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $payment->getPaidAt());

        // Act - Set to REFUNDED
        $payment->setStatus(PaymentStatus::REFUNDED);

        // Assert
        $this->assertNotNull($payment->getRefundedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $payment->getRefundedAt());
    }
}
