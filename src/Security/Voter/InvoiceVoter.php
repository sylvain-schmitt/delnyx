<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Invoice;
use App\Entity\InvoiceStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Voter pour centraliser toutes les autorisations sur les factures (Invoice)
 * 
 * Actions disponibles :
 * - EDIT : Modifier une facture
 * - DELETE : Supprimer une facture (jamais autorisé, archivage 10 ans)
 * - ISSUE : Émettre une facture (DRAFT → ISSUED)
 * - MARK_PAID : Marquer comme payée (ISSUED → PAID)
 * - CREATE_CREDITNOTE : Créer un avoir (ISSUED/PAID → CreditNote)
 * - VIEW : Voir une facture
 * 
 * @package App\Security\Voter
 */
class InvoiceVoter extends Voter
{
    public const EDIT = 'INVOICE_EDIT';
    public const DELETE = 'INVOICE_DELETE';
    public const ISSUE = 'INVOICE_ISSUE';
    public const SEND = 'INVOICE_SEND';
    public const MARK_PAID = 'INVOICE_MARK_PAID';
    public const CREATE_CREDITNOTE = 'INVOICE_CREATE_CREDITNOTE';
    public const VIEW = 'INVOICE_VIEW';

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
            self::MARK_PAID,
            self::CREATE_CREDITNOTE,
            self::VIEW,
        ])) {
            return false;
        }

        // Vérifier que le sujet est une Invoice
        return $subject instanceof Invoice;
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

        /** @var Invoice $invoice */
        $invoice = $subject;
        $status = $invoice->getStatutEnum();

        // Si le statut est null, refuser (facture invalide)
        if ($status === null) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($invoice, $user),
            self::EDIT => $this->canEdit($invoice, $user, $status),
            self::DELETE => $this->canDelete($invoice, $user, $status),
            self::ISSUE => $this->canIssue($invoice, $user, $status),
            self::SEND => $this->canSend($invoice, $user, $status),
            self::MARK_PAID => $this->canMarkPaid($invoice, $user, $status),
            self::CREATE_CREDITNOTE => $this->canCreateCreditNote($invoice, $user, $status),
            default => false,
        };
    }

    /**
     * Vérifie si l'utilisateur peut voir la facture
     */
    private function canView(Invoice $invoice, UserInterface $user): bool
    {
        // TODO: Ajouter vérification multi-tenant (companyId)
        // Pour l'instant, tout utilisateur authentifié peut voir
        return true;
    }

    /**
     * Vérifie si l'utilisateur peut modifier la facture
     * Modifiable uniquement si : DRAFT
     */
    private function canEdit(Invoice $invoice, UserInterface $user, InvoiceStatus $status): bool
    {
        return $status->isModifiable();
    }

    /**
     * Vérifie si l'utilisateur peut supprimer la facture
     * Jamais autorisé (archivage 10 ans obligatoire)
     */
    private function canDelete(Invoice $invoice, UserInterface $user, InvoiceStatus $status): bool
    {
        return false; // Archivage 10 ans obligatoire
    }

    /**
     * Vérifie si l'utilisateur peut émettre la facture
     * DRAFT → ISSUED
     */
    private function canIssue(Invoice $invoice, UserInterface $user, InvoiceStatus $status): bool
    {
        if (!$status->canBeIssued()) {
            return false;
        }

        // Vérifier que la facture peut être émise (lignes, montants, etc.)
        try {
            $invoice->validateCanBeIssued();
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Vérifie si l'utilisateur peut envoyer la facture
     * ISSUED → SENT (peut être fait plusieurs fois si PAID)
     */
    private function canSend(Invoice $invoice, UserInterface $user, InvoiceStatus $status): bool
    {
        // Seules les factures émises (ISSUED ou PAID) peuvent être envoyées
        return $status->canBeSent();
    }

    /**
     * Vérifie si l'utilisateur peut marquer la facture comme payée
     * ISSUED → PAID
     */
    private function canMarkPaid(Invoice $invoice, UserInterface $user, InvoiceStatus $status): bool
    {
        return $status->canBeMarkedPaid();
    }

    /**
     * Vérifie si l'utilisateur peut créer un avoir pour cette facture
     * ISSUED ou PAID → CreditNote
     */
    private function canCreateCreditNote(Invoice $invoice, UserInterface $user, InvoiceStatus $status): bool
    {
        if (!$status->canCreateCreditNote()) {
            return false;
        }

        // Vérifier qu'il n'y a pas déjà un avoir total
        // TODO: Vérifier si un avoir total existe déjà
        // if ($invoice->hasTotalCreditNote()) {
        //     return false;
        // }

        return true;
    }
}

