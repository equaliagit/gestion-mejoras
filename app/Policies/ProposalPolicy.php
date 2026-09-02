<?php

namespace App\Policies;

use App\Enums\Visibility;
use App\Models\Proposal;
use App\Models\ProposalStatus;
use App\Models\User;
use App\Support\Permissions;

/**
 * Quién puede hacer qué con una propuesta concreta.
 *
 * Va en pareja con VisibilityScope: el scope decide qué propuestas SALEN en
 * las consultas, y esta política decide qué se puede HACER con una que ya
 * tienes delante. Las dos cosas hacen falta.
 */
class ProposalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(Permissions::VIEW_PUBLIC);
    }

    public function view(User $user, Proposal $proposal): bool
    {
        if ($this->isAuthor($user, $proposal)) {
            return true;
        }

        // Un borrador ajeno no existe para nadie más, ni para el comité.
        if ($proposal->isDraft()) {
            return false;
        }

        if ($user->canSeeRestrictedProposals()) {
            return true;
        }

        return $proposal->visibility === Visibility::Public;
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(Permissions::CREATE);
    }

    /** El proponente puede corregir su propuesta mientras sea borrador. */
    public function update(User $user, Proposal $proposal): bool
    {
        return $this->isAuthor($user, $proposal) && $proposal->isDraft();
    }

    public function delete(User $user, Proposal $proposal): bool
    {
        return $this->isAuthor($user, $proposal) && $proposal->isDraft();
    }

    /** Asignarse la propuesta, pedir información, prepararla para el comité. */
    public function review(User $user, Proposal $proposal): bool
    {
        return ! $proposal->isDraft() && $user->hasPermissionTo(Permissions::REVIEW);
    }

    /** Aprobar, rechazar o aplazar: solo con la propuesta ya en el comité. */
    public function decide(User $user, Proposal $proposal): bool
    {
        return $user->hasPermissionTo(Permissions::DECIDE)
            && $proposal->status?->hasCode(ProposalStatus::IN_COMMITTEE);
    }

    /** Fechas de implantación y cierre. */
    public function implement(User $user, Proposal $proposal): bool
    {
        if (! $proposal->status?->hasCode(ProposalStatus::APPROVED)) {
            return false;
        }

        return $user->hasPermissionTo(Permissions::IMPLEMENT)
            || $proposal->implementer_id === $user->id;
    }

    /** Escribir en el hilo visible para el proponente. */
    public function comment(User $user, Proposal $proposal): bool
    {
        return $this->view($user, $proposal) && ! $proposal->isDraft();
    }

    /** Escribir comentarios de evaluación, que el proponente no ve. */
    public function commentInternally(User $user, Proposal $proposal): bool
    {
        return ! $proposal->isDraft() && $user->hasPermissionTo(Permissions::REVIEW);
    }

    /**
     * Saber quién firma una propuesta anónima: nadie, nunca.
     * Está escrito como método para que quede explícito y para que cualquier
     * intento futuro de saltárselo tenga que pasar por aquí.
     */
    public function revealAuthor(User $user, Proposal $proposal): bool
    {
        return ! $proposal->isAnonymous();
    }

    private function isAuthor(User $user, Proposal $proposal): bool
    {
        return $proposal->user_id === $user->id;
    }
}
