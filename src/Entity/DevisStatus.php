<?php

namespace App\Entity;

/**
 * Enum pour les statuts des devis
 * 
 * @package App\Entity
 */
enum DevisStatus: string
{
    case BROUILLON = 'brouillon';
    case ENVOYE = 'envoye';
    case ACCEPTE = 'accepte';
    case REFUSE = 'refuse';
    case EXPIRE = 'expire';
    case ANNULE = 'annule';

    /**
     * Retourne le libellé du statut
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::BROUILLON => 'Brouillon',
            self::ENVOYE => 'Envoyé',
            self::ACCEPTE => 'Accepté',
            self::REFUSE => 'Refusé',
            self::EXPIRE => 'Expiré',
            self::ANNULE => 'Annulé',
        };
    }

    /**
     * Retourne la couleur Bootstrap pour l'affichage
     */
    public function getColor(): string
    {
        return match ($this) {
            self::BROUILLON => 'secondary',
            self::ENVOYE => 'info',
            self::ACCEPTE => 'success',
            self::REFUSE => 'danger',
            self::EXPIRE => 'warning',
            self::ANNULE => 'dark',
        };
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
     * Retourne les choix pour EasyAdmin (retourne les enums directement)
     */
    public static function getEasyAdminChoices(): array
    {
        $choices = [];
        foreach (self::cases() as $case) {
            $choices[$case->getLabel()] = $case;
        }
        return $choices;
    }

    /**
     * Vérifie si le devis est dans un état final (ne peut plus être modifié)
     */
    public function isFinal(): bool
    {
        return in_array($this, [self::ACCEPTE, self::REFUSE, self::EXPIRE, self::ANNULE]);
    }

    /**
     * Vérifie si le devis peut être envoyé
     */
    public function canBeSent(): bool
    {
        return $this === self::BROUILLON;
    }

    /**
     * Vérifie si le devis peut être accepté
     */
    public function canBeAccepted(): bool
    {
        return $this === self::ENVOYE;
    }
}
