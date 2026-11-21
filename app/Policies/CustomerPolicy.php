<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;

class CustomerPolicy
{
    // ارفض حذف سجل العميل لغير المدير بإجابة 403.
    public function delete(User $user, Customer $customer): bool
    {
        return $user->hasRole('admin');
    }
}
