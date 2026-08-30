<?php

namespace App\Providers;

use App\Models\AllowedRegistrationEmail;
use App\Models\ChatSession;
use App\Models\ExecutivePreset;
use App\Models\PersonalNote;
use App\Models\PersonalTask;
use App\Policies\AllowedRegistrationEmailPolicy;
use App\Policies\ChatSessionPolicy;
use App\Policies\ExecutivePresetPolicy;
use App\Policies\PersonalNotePolicy;
use App\Policies\PersonalTaskPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Gate::policy(PersonalNote::class, PersonalNotePolicy::class);
        Gate::policy(PersonalTask::class, PersonalTaskPolicy::class);
        Gate::policy(ExecutivePreset::class, ExecutivePresetPolicy::class);
        Gate::policy(ChatSession::class, ChatSessionPolicy::class);
        Gate::policy(AllowedRegistrationEmail::class, AllowedRegistrationEmailPolicy::class);
    }
}
