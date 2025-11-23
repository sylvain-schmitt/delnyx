<?php

use App\Entity\CreditNote;
use App\Entity\CreditNoteStatus;
use App\Kernel;
use Doctrine\ORM\EntityManagerInterface;

require_once __DIR__ . '/vendor/autoload.php';

(new \Symfony\Component\Dotenv\Dotenv())->bootEnv(__DIR__ . '/.env');

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

/** @var EntityManagerInterface $em */
$em = $container->get('doctrine')->getManager();

$id = 3; // ID from previous DB check
$cn = $em->getRepository(CreditNote::class)->find($id);

if (!$cn) {
    echo "CreditNote #$id not found.\n";
    exit(1);
}

echo "Current status: " . ($cn->getStatut()?->value ?? 'NULL') . "\n";

try {
    echo "Attempting to set status to APPLIED...\n";
    $cn->setStatut(CreditNoteStatus::APPLIED);
    
    echo "Flushing...\n";
    $em->flush();
    
    echo "Flush complete.\n";
    echo "New status: " . ($cn->getStatut()?->value ?? 'NULL') . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
