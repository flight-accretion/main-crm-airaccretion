<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\SalesExecutiveAssignment;
use App\Models\User;
use App\Models\UserType;

class BookingSalesContactResolver
{
    public function resolve(Lead $lead, ?User $actor = null): array
    {
        $lead->loadMissing([
            'representative.userType',
        ]);

        $representative = $lead->representative;
        $role = optional($representative?->userType)->user_type;

        $sales = $representative ?: $actor;
        $manager = null;

        if ($representative && $role === UserType::SALES_EXECUTIVE) {
            $assignment = SalesExecutiveAssignment::query()
                ->where('sales_executive_id', $representative->id)
                ->where('status', 1)
                ->with('manager')
                ->latest('assigned_date')
                ->first();

            $manager = optional($assignment)->manager ?: $actor;
        }

        if (
            $representative
            && in_array($role, [
                UserType::SALES_MANAGER,
                UserType::SENIOR_SALES_MANAGER,
            ], true)
        ) {
            $manager = $representative;
        }

        if (!$manager) {
            $manager = $actor ?: $sales;
        }

        return [
            'sales' => $sales,
            'manager' => $manager,

            'sales_name' => $this->value(optional($sales)->name, 'Sales Representative'),
            'sales_email' => $this->value(optional($sales)->email, ''),
            'sales_phone' => $this->value(optional($sales)->contact_number, 'N/A'),

            'manager_name' => $this->value(optional($manager)->name, 'Manager'),
            'manager_email' => $this->value(optional($manager)->email, ''),
            'manager_phone' => $this->value(optional($manager)->contact_number, 'N/A'),
        ];
    }

    private function value($value, string $fallback): string
    {
        $value = trim((string) $value);

        return $value === '' ? $fallback : $value;
    }
}