<?php

declare(strict_types=1);

namespace App\Entity;

/**
 * Enum pour les statuts des avenants (amendments)
 */
enum AmendmentStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case SIGNED = 'signed';
    case CANCELLED = 'cancelled';

    /**
     * Retourne le libellé du statut
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Brouillon',
            self::SENT => 'Envoyé',
            self::SIGNED => 'Signé',
            self::CANCELLED => 'Annulé',
        };
    }

    /**
     * Retourne la couleur Bootstrap pour l'affichage
     */
    public function getColor(): string
    {
        return match ($this) {
            self::DRAFT => 'warning',
            self::SENT => 'info',
            self::SIGNED => 'success',
            self::CANCELLED => 'dark',
        };
    }

    /**
     * Vérifie si l'avenant est dans un état final (ne peut plus être modifié)
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::SENT, self::SIGNED, self::CANCELLED]);
    }

    /**
     * Vérifie si l'avenant peut être modifié
     * DRAFT uniquement peut être modifié
     */
    public function isModifiable(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Détermine si l'avenant est émis (immutable après envoi)
     * Un avenant SENT a un PDF généré et un numéro attribué
     */
    public function isEmitted(): bool
    {
        return in_array($this, [self::SENT, self::SIGNED, self::CANCELLED]);
    }

    /**
     * Vérifie si l'avenant peut être émis
     * Note : Workflow simplifié - L'émission se fait lors de l'envoi
     * Cette méthode est conservée pour backward compatibility mais retourne false
     */
    public function canBeIssued(): bool
    {
        return false; // Workflow simplifié : pas d'étape ISSUED intermédiaire
    }

    /**
     * Retourne les choix pour les formulaires
     */
    public static function getChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case->value;
        }
        return $choices;
    }

    /**
     * Vérifie si l'avenant peut être envoyé
     * DRAFT → SENT (premier envoi, génère PDF)
     * SENT → SENT (renvoi/relance)
     */
    public function canBeSent(): bool
    {
        return $this === self::DRAFT || $this === self::SENT;
    }

    /**
     * Vérifie si l'avenant peut être signé
     * SENT → SIGNED uniquement
     */
    public function canBeSigned(): bool
    {
        return $this === self::SENT;
    }

    /**
     * Vérifie si l'avenant peut être annulé
     * DRAFT ou SENT → CANCELLED
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [self::DRAFT, self::SENT]);
    }
}

