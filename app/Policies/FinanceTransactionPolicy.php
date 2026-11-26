<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\FinanceTransaction;
use Illuminate\Auth\Access\HandlesAuthorization;

class FinanceTransactionPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FinanceTransaction');
    }

    public function view(AuthUser $authUser, FinanceTransaction $financeTransaction): bool
    {
        return $authUser->can('View:FinanceTransaction');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FinanceTransaction');
    }

    public function update(AuthUser $authUser, FinanceTransaction $financeTransaction): bool
    {
        return $authUser->can('Update:FinanceTransaction');
    }

    public function delete(AuthUser $authUser, FinanceTransaction $financeTransaction): bool
    {
        return $authUser->can('Delete:FinanceTransaction');
    }

    public function deleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('DeleteAny:FinanceTransaction');
    }

    public function restore(AuthUser $authUser, FinanceTransaction $financeTransaction): bool
    {
        return $authUser->can('Restore:FinanceTransaction');
    }

    public function forceDelete(AuthUser $authUser, FinanceTransaction $financeTransaction): bool
    {
        return $authUser->can('ForceDelete:FinanceTransaction');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FinanceTransaction');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FinanceTransaction');
    }

    public function replicate(AuthUser $authUser, FinanceTransaction $financeTransaction): bool
    {
        return $authUser->can('Replicate:FinanceTransaction');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FinanceTransaction');
    }

}