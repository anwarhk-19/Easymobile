<?php

namespace App\Policies;

use App\Models\SupportTicket;
use App\Models\User;

class SupportTicketPolicy
{
    public function view(User $user, SupportTicket $ticket): bool
    {
        return $user->isStaff() || $ticket->user_id === $user->id;
    }

    public function manage(User $user, SupportTicket $ticket): bool
    {
        return $user->isStaff();
    }
}
