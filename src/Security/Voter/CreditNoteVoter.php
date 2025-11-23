<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\CreditNote;
use App\Entity\CreditNoteStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Voter pour centraliser toutes les autorisations sur les avoirs (CreditNote)
 * 
 * Actions disponibles :
 * - EDIT : Modifier un avoir (DRAFT uniquement)
 * - DELETE : Supprimer un avoir (jamais autorisé, archivage 10 ans)
 * - ISSUE : Émettre un avoir (DRAFT → ISSUED)
 * - SEND : Envoyer un avoir (ISSUED → SENT, répétable)
 * - CANCEL : Annuler un avoir (ISSUED → CANCELLED)
 * - VIEW : Voir un avoir
 * 
 * @package App\Security\Voter
 */
class CreditNoteVoter extends Voter
{
    public const EDIT = 'CREDIT_NOTE_EDIT';
    public const DELETE = 'CREDIT_NOTE_DELETE';
    public const ISSUE = 'CREDIT_NOTE_ISSUE';
    public const SEND = 'CREDIT_NOTE_SEND';
    public const CANCEL = 'CREDIT_NOTE_CANCEL';
    public const VIEW = 'CREDIT_NOTE_VIEW';
    public const APPLY = 'CREDIT_NOTE_APPLY';

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
            self::CANCEL,
            self::VIEW,
            self::APPLY,
        ])) {
            return false;
        }

        // Vérifier que le sujet est une CreditNote
        return $subject instanceof CreditNote;
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

        /** @var CreditNote $creditNote */
        $creditNote = $subject;
        $status = $creditNote->getStatut();

        // Si le statut est null, refuser (avoir invalide)
        if ($status === null) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($creditNote, $user),
            self::EDIT => $this->canEdit($creditNote, $user, $status),
            self::DELETE => $this->canDelete($creditNote, $user, $status),
            self::ISSUE => $this->canIssue($creditNote, $user, $status),
            self::SEND => $this->canSend($creditNote, $user, $status),
            self::CANCEL => $this->canCancel($creditNote, $user, $status),
            self::APPLY => $this->canApply($creditNote, $user, $status),
            default => false,
        };
    }

    /**
     * Vérifie si l'utilisateur peut voir l'avoir
     */
    private function canView(CreditNote $creditNote, UserInterface $user): bool
    {
        // TODO: Ajouter vérification multi-tenant (companyId)
        // Pour l'instant, tout utilisateur authentifié peut voir
        return true;
    }

    /**
     * Vérifie si l'utilisateur peut modifier l'avoir
     * Modifiable uniquement si : DRAFT
     * ISSUED, SENT et CANCELLED sont immuables
     */
    private function canEdit(CreditNote $creditNote, UserInterface $user, CreditNoteStatus $status): bool
    {
        // Refuser toute modification si émis (immuable)
        if ($status->isEmitted()) {
            return false;
        }

        // Autoriser uniquement DRAFT
        return $status === CreditNoteStatus::DRAFT;
    }

    /**
     * Vérifie si l'utilisateur peut supprimer l'avoir
     * Jamais autorisé (archivage 10 ans obligatoire)
     */
    private function canDelete(CreditNote $creditNote, UserInterface $user, CreditNoteStatus $status): bool
    {
        return false; // Archivage 10 ans obligatoire
    }

    /**
     * Vérifie si l'utilisateur peut émettre l'avoir
     * DRAFT → ISSUED
     */
    private function canIssue(CreditNote $creditNote, UserInterface $user, CreditNoteStatus $status): bool
    {
        // Autoriser uniquement depuis DRAFT
        if ($status !== CreditNoteStatus::DRAFT) {
            return false;
        }

        // Vérifier que l'avoir peut être émis (lignes, montants, etc.)
        try {
            $creditNote->validateCanBeIssued();
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Vérifie si l'utilisateur peut envoyer l'avoir
     * ISSUED → SENT (répétable)
     */
    private function canSend(CreditNote $creditNote, UserInterface $user, CreditNoteStatus $status): bool
    {
        // Autoriser depuis ISSUED et SENT (pour permettre les renvois)
        return in_array($status, [CreditNoteStatus::ISSUED, CreditNoteStatus::SENT]);
    }

    /**
     * Vérifie si l'utilisateur peut annuler l'avoir
     * DRAFT → CANCELLED ou ISSUED → CANCELLED
     */
    private function canCancel(CreditNote $creditNote, UserInterface $user, CreditNoteStatus $status): bool
    {
        // Autoriser depuis DRAFT ou ISSUED
        return in_array($status, [CreditNoteStatus::DRAFT, CreditNoteStatus::ISSUED]);
    }

    /**
     * Vérifie si l'utilisateur peut appliquer l'avoir
     * ISSUED ou SENT
     */
    private function canApply(CreditNote $creditNote, UserInterface $user, CreditNoteStatus $status): bool
    {
        return in_array($status, [CreditNoteStatus::ISSUED, CreditNoteStatus::SENT]);
    }
}

