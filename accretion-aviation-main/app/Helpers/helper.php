<?php

namespace App\Helpers;

use App\Models\City;
use App\Models\User;
use App\Models\State;
use App\Models\UserType;
use Illuminate\Support\Str;

function getStatesByCountry($countryId)
{
    if (!Str::isUuid($countryId)) {
        throw new \InvalidArgumentException('Invalid UUID');
    }

    return State::where('country_id', $countryId)
        ->where('status', 1)
        ->select('id', 'name')
        ->get();
}

function getCitiesByState($stateId)
{
    if (!Str::isUuid($stateId)) {
        throw new \InvalidArgumentException('Invalid state ID');
    }

    return City::where('state_id', $stateId)
        ->where('status', 1)
        ->select('id', 'name')
        ->get();
}

/**
 * Get all representative user IDs under current user based on hierarchy and sales executive assignments.
 */
function getRepresentativeIds($currentUser)
{
    $userType = $currentUser->userType;

    // Super Admin, Admin and Operations team = unrestricted
    // (Operations users should see all leads by default)
    if (!$userType || in_array($userType->user_type, UserType::ADMIN_ROLES) || in_array($userType->user_type, UserType::OPERATIONS_ROLES)) {
        return null;
    }

    // Sales Managers - use sales executive assignments
    if (in_array($userType->user_type, [UserType::SENIOR_SALES_MANAGER, UserType::SALES_MANAGER])) {
        $assignedExecutiveIds = \App\Models\SalesExecutiveAssignment::getSalesExecutivesForManager($currentUser->id)->pluck('id');
        $userIds = $assignedExecutiveIds->push($currentUser->id); // Include manager's own leads
        
        return $userIds->toArray();
    }

    // Sales Executives - only their own leads
    if ($userType->user_type === UserType::SALES_EXECUTIVE) {
        return [$currentUser->id];
    }

    // For other roles, use existing hierarchy logic
    // 1. Get all descendant role IDs for this user's type
    $descendantRoleIds = getDescendantRoleIds($userType->id);

    // 2. Get all users who belong to those roles OR the current user
    $query = User::query();

    if (!empty($descendantRoleIds)) {
        $query->where(function($q) use ($currentUser, $descendantRoleIds) {
            $q->where('id', $currentUser->id) // Include current user
              ->orWhereIn('user_type_id', $descendantRoleIds); // Include descendants
        });
    } else {
        // No descendants, only return current user
        $query->where('id', $currentUser->id);
    }

    return $query->pluck('id');
}

/**
 * Recursively get all descendant role IDs for a given role ID.
 */
function getDescendantRoleIds($roleId)
{
    $children = UserType::where('parent_id', $roleId)->pluck('id')->toArray();
    $all = $children;

    foreach ($children as $childId) {
        $all = array_merge($all, getDescendantRoleIds($childId));
    }

    return $all;
}

/**
 * Extract phone number without country code
 * This mirrors AirpointsIntegrationService::extractPhoneWithoutCountryCode
 */
function extractPhoneWithoutCountryCode($phone)
{
    if (empty($phone)) {
        return '';
    }

    // Remove all non-numeric characters
    $cleaned = preg_replace('/[^0-9]/', '', $phone);

    // Common country codes to remove
    $countryCodes = ['91', '1', '44', '61', '971'];

    foreach ($countryCodes as $code) {
        if (str_starts_with($cleaned, $code)) {
            // Remove country code and return
            return substr($cleaned, strlen($code));
        }
    }

    // If no country code detected, return last 10 digits (common mobile length)
    if (strlen($cleaned) > 10) {
        return substr($cleaned, -10);
    }

    return $cleaned;
}
