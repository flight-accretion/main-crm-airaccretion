<?php

namespace Tests\Feature;

use Tests\TestCase;

class CustomerRefundTwoStepRouteTest extends TestCase
{
    public function test_customer_refund_two_step_endpoints_are_registered_and_protected(): void
    {
        $routes = app('router')->getRoutes();

        foreach ([
            'admin.rides.customer-refund.two-step.show' => 'GET',
            'admin.rides.customer-refund.two-step.assessment' => 'POST',
            'admin.rides.customer-refund.two-step.payment' => 'POST',
        ] as $name => $method) {
            $route = $routes->getByName($name);

            $this->assertNotNull($route, $name . ' must be registered.');
            $this->assertContains($method, $route->methods());
            $this->assertContains(
                'role:ADMIN_ROLES,ACCOUNTS_ROLES,OPERATIONS_ROLES,OWNER',
                $route->middleware()
            );
        }
    }
}
