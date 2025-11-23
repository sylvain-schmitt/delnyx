<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Amendment;
use App\Entity\AmendmentStatus;
use App\Entity\Quote;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;

/**
 * EventSubscriber pour recalculer le montant du devis quand un avenant est signé
 * 
 * Règle métier : quote.total += amendment.total quand l'avenant est signé
 */
#[AsEntityListener(event: Events::postUpdate, method: 'postUpdate')]
class RecalculateQuoteFromAmendmentSubscriber
{
    public function postUpdate(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Amendment) {
            $this->handleAmendmentSignature($entity, $args);
        }
    }

    private function handleAmendmentSignature(Amendment $amendment, LifecycleEventArgs $args): void
    {
        // Si l'avenant vient d'être signé, recalculer le total du devis
        if ($amendment->getStatut() === AmendmentStatus::SIGNED) {
            $quote = $amendment->getQuote();
            if ($quote) {
                $this->recalculateQuoteTotal($quote, $args);
            }
        }
    }

    /**
     * Recalcule le montant total du devis en ajoutant les avenants signés
     */
    private function recalculateQuoteTotal(Quote $quote, LifecycleEventArgs $args): void
    {
        $em = $args->getObjectManager();
        
        // Récupérer tous les avenants signés pour ce devis
        $amendments = $em->getRepository(Amendment::class)->createQueryBuilder('a')
            ->where('a.quote = :quote')
            ->andWhere('a.statut = :signed')
            ->setParameter('quote', $quote)
            ->setParameter('signed', AmendmentStatus::SIGNED)
            ->getQuery()
            ->getResult();

        // Calculer le total des avenants
        $totalAvenantsHT = 0.0;
        $totalAvenantsTVA = 0.0;
        $totalAvenantsTTC = 0.0;

        foreach ($amendments as $amendment) {
            $totalAvenantsHT += (float) $amendment->getMontantHT();
            $totalAvenantsTVA += (float) $amendment->getMontantTVA();
            $totalAvenantsTTC += (float) $amendment->getMontantTTC();
        }

        // Recalculer le total du devis : montant initial + avenants
        // Le montant initial est calculé depuis les lignes du devis
        $quote->recalculateTotalsFromLines();
        
        $montantHTInitial = (float) $quote->getMontantHT();
        $montantTVAInitial = (float) $quote->getMontantTVA();
        $montantTTCInitial = (float) $quote->getMontantTTC();

        // Ajouter les montants des avenants
        $quote->setMontantHT(number_format($montantHTInitial + $totalAvenantsHT, 2, '.', ''));
        $quote->setMontantTVA(number_format($montantTVAInitial + $totalAvenantsTVA, 2, '.', ''));
        $quote->setMontantTTC(number_format($montantTTCInitial + $totalAvenantsTTC, 2, '.', ''));

        $em->flush();
    }
}

