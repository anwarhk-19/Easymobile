<?php

namespace App\Policies;

use App\Models\RepairRequest;
use App\Models\User;

class RepairRequestPolicy
{
    public function view(User $user, RepairRequest $repair): bool
    {
        return $user->isStaff() || $repair->customer_id === $user->id || $this->isAssignedTechnician($user, $repair);
    }

    public function actAsCustomer(User $user, RepairRequest $repair): bool
    {
        return $repair->customer_id === $user->id;
    }

    public function actAsTechnician(User $user, RepairRequest $repair): bool
    {
        return $this->isAssignedTechnician($user, $repair);
    }

    /**
     * Sensitive device identifiers (IMEI) are visible only to the assigned technician once the job is accepted,
     * and only if the customer chose to share them; admins can view for dispute handling.
     */
    public function viewSensitive(User $user, RepairRequest $repair): bool
    {
        return $user->isAdmin() || $repair->customer_id === $user->id
            || ($this->isAssignedTechnician($user, $repair) && $repair->accepted_at !== null && $repair->isOpen());
    }

    private function isAssignedTechnician(User $user, RepairRequest $repair): bool
    {
        return $user->role === 'technician' && $user->technicianProfile?->id === $repair->technician_profile_id;
    }
}
