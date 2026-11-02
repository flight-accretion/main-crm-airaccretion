<?php

namespace App\Services\WhatsApp\Msg91;

use App\Models\Msg91WhatsAppSender;
use App\Models\User;
use App\Models\UserType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class Msg91CampaignAccess
{
    public function canUseModule(?User $user): bool
    {
        return $user && in_array($user->userType?->user_type, array_merge(
            UserType::SALES_ROLES,
            UserType::ADMIN_ROLES,
            [UserType::OWNER]
        ), true);
    }

    public function canManage(?User $user): bool
    {
        return $user && in_array($user->userType?->user_type, UserType::ADMIN_ROLES, true);
    }

    /**
     * Sales users may manage only templates tied to their own verified sender.
     * Admin roles manage company-wide templates and sender access.
     */
    public function canManageTemplates(?User $user): bool
    {
        return $user
            && in_array($user->userType?->user_type, array_merge(UserType::SALES_ROLES, UserType::ADMIN_ROLES), true);
    }

    /** Only Super Admin controls whether an approved template is visible in CRM. */
    public function canManageTemplateVisibility(?User $user): bool
    {
        return $user?->userType?->user_type === UserType::SUPER_ADMIN;
    }

    public function canViewCompany(?User $user): bool
    {
        return $user && in_array($user->userType?->user_type, array_merge(UserType::ADMIN_ROLES, [UserType::OWNER]), true);
    }

    public function senderFor(User $user): ?Msg91WhatsAppSender
    {
        return Msg91WhatsAppSender::query()->where('user_id', $user->id)->first();
    }

    public function assertCanSend(User $user): Msg91WhatsAppSender
    {
        abort_unless($this->canUseModule($user), 403, 'WhatsApp campaigns are not available for this role.');
        abort_unless((bool) config('services.msg91_campaigns.enabled', false), 403, 'WhatsApp campaigns are disabled.');

        $sender = $this->senderFor($user);
        abort_unless($sender && $sender->isVerifiedActive(), 403, 'Your WhatsApp sender number is not verified and active.');

        return $sender;
    }

    public function canCompose(?User $user): bool
    {
        if (!$user) {
            return false;
        }

        try {
            $this->assertCanSend($user);
            return true;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    /** @return array<int, string>|null */
    public function visibleRepresentativeIds(User $user): ?array
    {
        if ($this->canViewCompany($user)) {
            return null;
        }

        // The CRM hierarchy helper is namespaced under App\Helpers. Calling it
        // unqualified here makes PHP look for an MSG91-local helper instead.
        $representatives = \App\Helpers\getRepresentativeIds($user);

        if ($representatives instanceof Collection) {
            return $representatives
                ->map(static fn ($id) => (string) $id)
                ->values()
                ->all();
        }

        return array_values(array_map('strval', (array) $representatives));
    }

    public function scopeLeads(Builder $query, User $user): Builder
    {
        $ids = $this->visibleRepresentativeIds($user);
        return $ids === null ? $query : $query->whereIn('leads.representative_user_id', $ids);
    }

    public function canViewCampaign(User $user, $campaign): bool
    {
        if (!$this->canUseModule($user)) {
            return false;
        }

        if ($this->canViewCompany($user)) {
            return true;
        }

        if ((string) $campaign->created_by === (string) $user->id) {
            return true;
        }

        $visible = $this->visibleRepresentativeIds($user) ?? [];
        return in_array((string) $campaign->created_by, $visible, true);
    }
}
