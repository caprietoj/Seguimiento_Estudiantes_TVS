<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;
use App\Support\Scope;

class StudentPolicy
{
    public function view(User $user, Student $student): bool
    {
        return Scope::canViewStudent($user, $student);
    }
}
