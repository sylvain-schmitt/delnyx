<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\AmendmentLine;
use App\Entity\CreditNoteLine;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;

/**
 * Subscriber Doctrine pour calculer automatiquement oldValue, newValue, delta
 *
 * CONFORMITÉ LÉGALE :
 * - Pour chaque ligne d'avenant/avoir, on doit stocker oldValue, newValue, delta
 * - oldValue = valeur d'origine (0.00 si ligne ajoutée, total HT de la ligne source si modification)
 * - newValue = nouvelle valeur (total HT calculé)
 * - delta = newValue - oldValue
 *
 * Ce subscriber calcule automatiquement ces valeurs lors de la création/modification
 */
#[AsEntityListener(event: Events::prePersist, entity: AmendmentLine::class)]
#[AsEntityListener(event: Events::preUpdate, entity: AmendmentLine::class)]
#[AsEntityListener(event: Events::prePersist, entity: CreditNoteLine::class)]
#[AsEntityListener(event: Events::preUpdate, entity: CreditNoteLine::class)]
class CalculateDeltaSubscriber
{
    /**
     * Calcule oldValue, newValue, delta avant la création
     */
    public function prePersist(AmendmentLine|CreditNoteLine $line, PrePersistEventArgs $args): void
    {
        $this->calculateDelta($line);
    }

    /**
     * Calcule oldValue, newValue, delta avant la mise à jour
     */
    public function preUpdate(AmendmentLine|CreditNoteLine $line, PreUpdateEventArgs $args): void
    {
        $this->calculateDelta($line);
    }

    /**
     * Calcule oldValue, newValue, delta pour une ligne
     *
     * LOGIQUE IDENTIQUE À recalculateTotalHt() :
     * - Si sourceLine est défini : unitPrice représente le DELTA (ajustement)
     *   → oldValue = sourceLine.totalHt
     *   → newValue = oldValue + (unitPrice × quantity)
     * - Si sourceLine est NULL : unitPrice représente la nouvelle valeur totale
     *   → oldValue = 0.00
     *   → newValue = unitPrice × quantity
     */
    private function calculateDelta(AmendmentLine|CreditNoteLine $line): void
    {
        $sourceLine = $line->getSourceLine();

        // Calculer newValue selon la même logique que recalculateTotalHt()
        // Seulement si quantity et unitPrice sont définis
        if ($line->getQuantity() !== null && $line->getUnitPrice() !== null) {
            if ($sourceLine) {
                // MODIFICATION : unitPrice représente le DELTA (ajustement)
                // Définir oldValue en premier si pas déjà défini
                if (!$line->getOldValue() || $line->getOldValue() === '0.00') {
                    $oldValue = (float) $sourceLine->getTotalHt();
                    $line->setOldValue(number_format($oldValue, 2, '.', ''));
                }

                // newValue = oldValue + delta
                // newValue = oldValue + delta
                $oldValue = (float) $line->getOldValue();
                $delta = (float) $line->getUnitPrice() * $line->getQuantity();

                // CORRECTIF POUR AVOIRS : Le delta doit être un crédit (négatif)
                if ($line instanceof \App\Entity\CreditNoteLine) {
                    $delta = -abs($delta);
                }

                $newValue = $oldValue + $delta;
                $line->setNewValue(number_format($newValue, 2, '.', ''));

                // CORRECTIF MAJEUR : Le montant de la ligne (totalHt) est le DELTA (l'écart), pas le nouveau total
                // Exemple : Facture 1500 -> 1400. Delta = -100. Montant de l'avoir = -100.
                $line->setTotalHt(number_format($delta, 2, '.', ''));
            } else {
                // AJOUT : unitPrice représente la nouvelle valeur totale
                // Définir oldValue à 0.00 si pas déjà défini
                if (!$line->getOldValue() || $line->getOldValue() === '0.00') {
                    $line->setOldValue('0.00');
                }

                $total = (float) $line->getUnitPrice() * $line->getQuantity();
                // Pour les avoirs, le montant doit être négatif (crédit)
                if ($line instanceof \App\Entity\CreditNoteLine && $total > 0) {
                    $total = -$total;
                }
                $line->setNewValue(number_format($total, 2, '.', ''));
                // Mettre à jour totalHt pour qu'il corresponde à newValue
                $line->setTotalHt(number_format($total, 2, '.', ''));
            }
        } else {
            // Si quantity ou unitPrice n'est pas défini, utiliser totalHt comme newValue
            // Seulement si newValue n'est pas déjà défini
            if (!$line->getNewValue() || $line->getNewValue() === '0.00') {
                $newValue = (float) $line->getTotalHt();
                if ($newValue != 0) {
                    $line->setNewValue(number_format($newValue, 2, '.', ''));
                }
            }

            // Définir oldValue si pas déjà défini
            if (!$line->getOldValue() || $line->getOldValue() === '0.00') {
                if ($sourceLine) {
                    // MODIFICATION : oldValue = total HT de la ligne source
                    $oldValue = (float) $sourceLine->getTotalHt();
                    $line->setOldValue(number_format($oldValue, 2, '.', ''));
                } else {
                    // AJOUT : oldValue = 0.00
                    $line->setOldValue('0.00');
                }
            }
        }

        // Recalculer le delta (sera fait automatiquement)
        $line->recalculateDelta();
    }
}
