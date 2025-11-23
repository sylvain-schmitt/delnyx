<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Quote;
use App\Entity\QuoteStatus;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Voter pour centraliser toutes les autorisations sur les devis (Quote)
 * 
 * Actions disponibles :
 * - EDIT : Modifier un devis
 * - DELETE : Supprimer un devis (jamais autorisé, archivage 10 ans)
 * - ISSUE : Émettre un devis (DRAFT → ISSUED)
 * - SEND : Envoyer un devis (ISSUED → SENT)
 * - ACCEPT : Accepter un devis (SENT → ACCEPTED)
 * - SIGN : Signer un devis (SENT/ACCEPTED → SIGNED)
 * - CANCEL : Annuler un devis (DRAFT → CANCELLED)
 * - REFUSE : Refuser un devis (SENT/ACCEPTED → REFUSED)
 * - GENERATE_INVOICE : Générer une facture depuis un devis (SIGNED uniquement)
 * - VIEW : Voir un devis
 * 
 * @package App\Security\Voter
 */
class QuoteVoter extends Voter
{
    public const EDIT = 'QUOTE_EDIT';
    public const DELETE = 'QUOTE_DELETE';
    public const ISSUE = 'QUOTE_ISSUE';
    public const SEND = 'QUOTE_SEND';
    public const ACCEPT = 'QUOTE_ACCEPT';
    public const SIGN = 'QUOTE_SIGN';
    public const CANCEL = 'QUOTE_CANCEL';
    public const REFUSE = 'QUOTE_REFUSE';
    public const GENERATE_INVOICE = 'QUOTE_GENERATE_INVOICE';
    public const VIEW = 'QUOTE_VIEW';

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
            self::ACCEPT,
            self::SIGN,
            self::CANCEL,
            self::REFUSE,
            self::GENERATE_INVOICE,
            self::VIEW,
        ])) {
            return false;
        }

        // Vérifier que le sujet est une Quote
        return $subject instanceof Quote;
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

        /** @var Quote $quote */
        $quote = $subject;
        $status = $quote->getStatut();

        // Si le statut est null, refuser (devis invalide)
        if ($status === null) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($quote, $user),
            self::EDIT => $this->canEdit($quote, $user, $status),
            self::DELETE => $this->canDelete($quote, $user, $status),
            self::ISSUE => $this->canIssue($quote, $user, $status),
            self::SEND => $this->canSend($quote, $user, $status),
            self::ACCEPT => $this->canAccept($quote, $user, $status),
            self::SIGN => $this->canSign($quote, $user, $status),
            self::CANCEL => $this->canCancel($quote, $user, $status),
            self::REFUSE => $this->canRefuse($quote, $user, $status),
            self::GENERATE_INVOICE => $this->canGenerateInvoice($quote, $user, $status),
            default => false,
        };
    }

    /**
     * Vérifie si l'utilisateur peut voir le devis
     */
    private function canView(Quote $quote, UserInterface $user): bool
    {
        // TODO: Ajouter vérification multi-tenant (companyId)
        // Pour l'instant, tout utilisateur authentifié peut voir
        return true;
    }

    /**
     * Vérifie si l'utilisateur peut modifier le devis
     * Modifiable uniquement si : DRAFT, SENT, ACCEPTED
     */
    private function canEdit(Quote $quote, UserInterface $user, QuoteStatus $status): bool
    {
        return $status->isModifiable();
    }

    /**
     * Vérifie si l'utilisateur peut supprimer le devis
     * Jamais autorisé (archivage 10 ans obligatoire)
     */
    private function canDelete(Quote $quote, UserInterface $user, QuoteStatus $status): bool
    {
        return false; // Archivage 10 ans obligatoire
    }

    /**
     * Vérifie si l'utilisateur peut émettre le devis
     * DRAFT → ISSUED
     */
    private function canIssue(Quote $quote, UserInterface $user, QuoteStatus $status): bool
    {
        return $status->canBeIssued();
    }

    /**
     * Vérifie si l'utilisateur peut envoyer le devis
     * ISSUED → SENT (ou renvoie si déjà SENT)
     */
    private function canSend(Quote $quote, UserInterface $user, QuoteStatus $status): bool
    {
        return $status->canBeSent();
    }

    /**
     * Vérifie si l'utilisateur peut accepter le devis
     * SENT → ACCEPTED
     */
    private function canAccept(Quote $quote, UserInterface $user, QuoteStatus $status): bool
    {
        return $status->canBeAccepted();
    }

    /**
     * Vérifie si l'utilisateur peut signer le devis
     * SENT → SIGNED ou ACCEPTED → SIGNED
     */
    private function canSign(Quote $quote, UserInterface $user, QuoteStatus $status): bool
    {
        if (!$status->canBeSigned()) {
            return false;
        }

        // Vérifier que le devis peut être signé (lignes, montants, etc.)
        try {
            $quote->validateCanBeSigned();
            return true;
        } catch (\RuntimeException $e) {
            return false;
        }
    }

    /**
     * Vérifie si l'utilisateur peut annuler le devis
     * DRAFT, SENT, ACCEPTED → CANCELLED
     */
    private function canCancel(Quote $quote, UserInterface $user, QuoteStatus $status): bool
    {
        return $status->canBeCancelled();
    }

    /**
     * Vérifie si l'utilisateur peut refuser le devis
     * SENT, ACCEPTED → REFUSED
     */
    private function canRefuse(Quote $quote, UserInterface $user, QuoteStatus $status): bool
    {
        return $status->canBeRefused();
    }

    /**
     * Vérifie si l'utilisateur peut générer une facture depuis le devis
     * Seul un devis SIGNED peut être facturé
     */
    private function canGenerateInvoice(Quote $quote, UserInterface $user, QuoteStatus $status): bool
    {
        if (!$status->canGenerateInvoice()) {
            return false;
        }

        // Vérifier qu'il n'y a pas déjà une facture générée depuis ce devis
        // TODO: Vérifier si une facture existe déjà pour ce devis
        // if ($quote->getInvoice() !== null) {
        //     return false;
        // }

        return true;
    }
}

