<?php

namespace App\Providers;

// use Illuminate\Support\Facades\Gate;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

use Laravel\Passport\Passport;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        \App\Models\Child::class              => \App\Policies\ChildPolicy::class,
        \App\Models\Conversation::class       => \App\Policies\ConversationPolicy::class,
        \App\Models\ChatAttachment::class     => \App\Policies\ChatAttachmentPolicy::class,
        \App\Models\ChildMemory::class        => \App\Policies\ChildMemoryPolicy::class,
        \App\Models\Appointment::class        => \App\Policies\AppointmentPolicy::class,
        \App\Models\SpecialistReview::class   => \App\Policies\SpecialistReviewPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
     

        //
    }
}
