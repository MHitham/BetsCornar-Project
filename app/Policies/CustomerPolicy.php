<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    // اسمح للمدير والموظف بحذف سجل العميل.
    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasAnyRole(['admin', 'employee']);
    }
}
