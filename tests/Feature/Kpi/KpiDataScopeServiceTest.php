<?php

namespace Tests\Feature\Kpi;

use App\Models\UserType;
use App\Services\Kpi\KpiDataScopeService;

class KpiDataScopeServiceTest extends KpiFeatureTestCase
{
    public function test_sales_scope_uses_explicit_sales_assignments(): void
    {
        $manager = $this->createUserWithRole(
            'Sales Manager',
            UserType::SALES_MANAGER
        );
        $assigned = $this->createUserWithRole(
            'Assigned Sales Executive',
            UserType::SALES_EXECUTIVE
        );
        $unassigned = $this->createUserWithRole(
            'Unassigned Sales Executive',
            UserType::SALES_EXECUTIVE
        );

        $this->assignSalesExecutive($manager, $assigned);

        $ids = app(KpiDataScopeService::class)
            ->allowedUserIds($manager, 'sales');

        $this->assertContains($manager->id, $ids);
        $this->assertContains($assigned->id, $ids);
        $this->assertNotContains($unassigned->id, $ids);
    }

    public function test_non_manager_users_are_limited_to_self(): void
    {
        $sales = $this->createUserWithRole(
            'Self Sales Executive',
            UserType::SALES_EXECUTIVE
        );
        $accounts = $this->createUserWithRole(
            'Self Accounts Executive',
            UserType::ACCOUNTS_EXECUTIVE
        );

        $service = app(KpiDataScopeService::class);

        $this->assertSame(
            [$sales->id],
            $service->allowedUserIds($sales, 'sales')
        );
        $this->assertSame(
            [$accounts->id],
            $service->allowedUserIds($accounts, 'accounts')
        );
    }

    public function test_accounts_and_operations_managers_use_explicit_kpi_team_memberships_only(): void
    {
        $accountsManager = $this->createUserWithRole(
            'Accounts Manager',
            UserType::ACCOUNTS_MANAGER
        );
        $accountsMember = $this->createUserWithRole(
            'Accounts Member',
            UserType::ACCOUNTS_EXECUTIVE
        );
        $unassignedAccounts = $this->createUserWithRole(
            'Unassigned Accounts',
            UserType::ACCOUNTS_EXECUTIVE
        );
        $operationsManager = $this->createUserWithRole(
            'Operations Manager',
            UserType::OPERATIONS_MANAGER
        );
        $operationsMember = $this->createUserWithRole(
            'Operations Member',
            UserType::OPERATIONS_EXECUTIVE
        );

        $this->assignKpiTeamMember(
            'accounts',
            $accountsManager,
            $accountsMember
        );
        $this->assignKpiTeamMember(
            'operations',
            $operationsManager,
            $operationsMember
        );

        $service = app(KpiDataScopeService::class);
        $accountsIds = $service->allowedUserIds(
            $accountsManager,
            'accounts'
        );
        $operationsIds = $service->allowedUserIds(
            $operationsManager,
            'operations'
        );

        $this->assertContains($accountsManager->id, $accountsIds);
        $this->assertContains($accountsMember->id, $accountsIds);
        $this->assertNotContains($unassignedAccounts->id, $accountsIds);
        $this->assertNotContains($operationsMember->id, $accountsIds);

        $this->assertContains($operationsManager->id, $operationsIds);
        $this->assertContains($operationsMember->id, $operationsIds);
        $this->assertNotContains($accountsMember->id, $operationsIds);
    }

    public function test_admin_can_scope_to_requested_department_users(): void
    {
        $admin = $this->createUserWithRole(
            'KPI Admin',
            UserType::ADMIN
        );
        $sales = $this->createUserWithRole(
            'Admin Sales User',
            UserType::SALES_EXECUTIVE
        );
        $accounts = $this->createUserWithRole(
            'Admin Accounts User',
            UserType::ACCOUNTS_EXECUTIVE
        );

        $ids = app(KpiDataScopeService::class)
            ->allowedUserIds($admin, 'accounts');

        $this->assertContains($accounts->id, $ids);
        $this->assertNotContains($sales->id, $ids);
    }
}
