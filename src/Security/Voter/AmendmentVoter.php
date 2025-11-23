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
 * - ISSUE : Émettre un avenant (DRAFT → ISSUED)
 * - SEND : Envoyer un avenant (ISSUED → SENT)
 * - SIGN : Signer un avenant (SENT → SIGNED)
 * - CANCEL : Annuler un avenant (DRAFT → CANCELLED)
 * - VIEW : Voir un avenant
 * 
 * @package App\Security\Voter
 */
class AmendmentVoter extends Voter
{
    public const EDIT = 'AMENDMENT_EDIT';
    public const DELETE = 'AMENDMENT_DELETE';
    public const ISSUE = 'AMENDMENT_ISSUE';
    public const SEND = 'AMENDMENT_SEND';
    public const SIGN = 'AMENDMENT_SIGN';
    public const CANCEL = 'AMENDMENT_CANCEL';
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
            self::ISSUE,
            self::SEND,
            self::SIGN,
            self::CANCEL,
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
            self::ISSUE => $this->canIssue($amendment, $user, $status),
            self::SEND => $this->canSend($amendment, $user, $status),
            self::SIGN => $this->canSign($amendment, $user, $status),
            self::CANCEL => $this->canCancel($amendment, $user, $status),
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
     * ISSUED, SENT, SIGNED et CANCELLED sont immuables
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
     * Vérifie si l'utilisateur peut émettre l'avenant
     * DRAFT → ISSUED
     */
    private function canIssue(Amendment $amendment, UserInterface $user, AmendmentStatus $status): bool
    {
        return $status->canBeIssued();
    }

    /**
     * Vérifie si l'utilisateur peut envoyer l'avenant
     * ISSUED → SENT (ou renvoie si déjà SENT)
     */
    private function canSend(Amendment $amendment, UserInterface $user, AmendmentStatus $status): bool
    {
        return $status->canBeSent();
    }

    /**
     * Vérifie si l'utilisateur peut signer l'avenant
     * SENT → SIGNED
     */
    private function canSign(Amendment $amendment, UserInterface $user, AmendmentStatus $status): bool
    {
        // Autoriser uniquement depuis SENT
        if ($status !== AmendmentStatus::SENT) {
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
     * DRAFT → CANCELLED
     */
    private function canCancel(Amendment $amendment, UserInterface $user, AmendmentStatus $status): bool
    {
        // Autoriser uniquement depuis DRAFT
        return $status === AmendmentStatus::DRAFT;
    }
}
