<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Amendment;
use App\Entity\AmendmentStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Voter pour centraliser toutes les autorisations sur les avenants (Amendment)
 * 
 * Actions disponibles :
 * - EDIT : Modifier un avenant (DRAFT uniquement)
 * - DELETE : Supprimer un avenant (jamais autorisé, archivage 10 ans)
 * - SEND : Envoyer un avenant (DRAFT → SENT, ou SENT → SENT pour relance)
 * - SIGN : Signer un avenant (SENT → SIGNED)
 * - CANCEL : Annuler un avenant (DRAFT ou SENT → CANCELLED)
 * - BACK_TO_DRAFT : Retour en brouillon (SENT → DRAFT)
 * - REMIND : Relancer le client (SENT uniquement)
 * - VIEW : Voir un avenant
 * 
 * Note : L'action ISSUE a été supprimée (workflow simplifié : DRAFT → SENT directement)
 * 
 * @package App\Security\Voter
 */
class AmendmentVoter extends Voter
{
    public const EDIT = 'AMENDMENT_EDIT';
    public const DELETE = 'AMENDMENT_DELETE';
    public const SEND = 'AMENDMENT_SEND';
    public const SIGN = 'AMENDMENT_SIGN';
    public const CANCEL = 'AMENDMENT_CANCEL';
    public const BACK_TO_DRAFT = 'AMENDMENT_BACK_TO_DRAFT';
    public const REMIND = 'AMENDMENT_REMIND';
    public const VIEW = 'AMENDMENT_VIEW';

    /**
     * Détermine si le voter peut traiter l'attribut et le sujet
     */
    protected function supports(string $attribute, mixed $subject): bool
    {
        // Vérifier que l'attribut est une action supportée
        if (!in_array($attribute, [
            self::EDIT,
            self::DELETE,
            self::SEND,
            self::SIGN,
            self::CANCEL,
            self::BACK_TO_DRAFT,
            self::REMIND,
            self::VIEW,
        ])) {
            return false;
        }

        // Vérifier que le sujet est une Amendment
        return $subject instanceof Amendment;
    }

    /**
     * Vote sur l'autorisation d'une action
     */
    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // Si l'utilisateur n'est pas authentifié, refuser
        if (!$user instanceof UserInterface) {
            return false;
        }

        /** @var Amendment $amendment */
        $amendment = $subject;
        $status = $amendment->getStatut();

        // Si le statut est null, refuser (avenant invalide)
        if ($status === null) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($amendment, $user),
            self::EDIT => $this->canEdit($amendment, $user, $status),
            self::DELETE => $this->canDelete($amendment, $user, $status),
            self::SEND => $this->canSend($amendment, $user, $status),
            self::SIGN => $this->canSign($amendment, $user, $status),
            self::CANCEL => $this->canCancel($amendment, $user, $status),
            self::BACK_TO_DRAFT => $this->canBackToDraft($amendment, $user, $status),
            self::REMIND => $this->canRemind($amendment, $user, $status),
            default => false,
        };
    }

    /**
     * Vérifie si l'utilisateur peut voir l'avenant
     */
    private function canView(Amendment $amendment, UserInterface $user): bool
    {
        // TODO: Ajouter vérification multi-tenant (companyId)
        // Pour l'instant, tout utilisateur authentifié peut voir
        return true;
    }

    /**
     * Vérifie si l'utilisateur peut modifier l'avenant
     * Modifiable uniquement si : DRAFT
     * SENT, SIGNED et CANCELLED sont immuables (sauf via BACK_TO_DRAFT pour SENT)
     */
    private function canEdit(Amendment $amendment, UserInterface $user, AmendmentStatus $status): bool
    {
        return $status->isModifiable();
    }

    /**
     * Vérifie si l'utilisateur peut supprimer l'avenant
     * Jamais autorisé (archivage 10 ans obligatoire)
     */
    private function canDelete(Amendment $amendment, UserInterface $user, AmendmentStatus $status): bool
    {
        return false; // Archivage 10 ans obligatoire
    }

    /**
     * Vérifie si l'utilisateur peut envoyer l'avenant
     * DRAFT → SENT (premier envoi, génère PDF + numéro)
     * SENT → SENT (renvoi/relance)
     */
    private function canSend(Amendment $amendment, UserInterface $user, AmendmentStatus $status): bool
    {
        return $status->canBeSent();
    }

    /**
     * Vérifie si l'utilisateur peut signer l'avenant
     * SENT → SIGNED uniquement
     */
    private function canSign(Amendment $amendment, UserInterface $user, AmendmentStatus $status): bool
    {
        if (!$status->canBeSigned()) {
            return false;
        }

        // Vérifier que l'avenant peut être signé (lignes, montants, etc.)
        try {
            $amendment->validateCanBeSigned();
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Vérifie si l'utilisateur peut annuler l'avenant
     * DRAFT ou SENT → CANCELLED
     */
    private function canCancel(Amendment $amendment, UserInterface $user, AmendmentStatus $status): bool
    {
        return $status->canBeCancelled();
    }

    /**
     * Vérifie si l'utilisateur peut remettre l'avenant en brouillon
     * SENT → DRAFT uniquement
     */
    private function canBackToDraft(Amendment $amendment, UserInterface $user, AmendmentStatus $status): bool
    {
        return $status === AmendmentStatus::SENT;
    }

    /**
     * Vérifie si l'utilisateur peut relancer le client
     * SENT uniquement, et seulement si le client a un email
     */
    private function canRemind(Amendment $amendment, UserInterface $user, AmendmentStatus $status): bool
    {
        if ($status !== AmendmentStatus::SENT) {
            return false;
        }

        // Vérifier que le client a un email
        $client = $amendment->getQuote()?->getClient();
        return $client && $client->getEmail();
    }
}
