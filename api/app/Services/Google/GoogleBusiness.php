<?php

namespace App\Services\Google;

use App\Models\User;

class GoogleBusiness
{
    /** Demo accounts always get sample data, whatever GOOGLE_DRIVER says. */
    public static function for(User $user): GoogleBusinessProvider
    {
        return $user->is_demo ? app(DemoGoogleBusiness::class) : app(GoogleBusinessProvider::class);
    }

    public static function usesSample(User $user): bool
    {
        return self::for($user) instanceof DemoGoogleBusiness;
    }
}
