<?php

namespace Tests\Unit;

use App\Http\Controllers\KpiOutreachController;
use App\Models\KpiOutreachAssignment;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class KpiOutreachControllerSignatureTest extends TestCase
{
    public function test_outreach_action_parameters_use_assignment_model(): void
    {
        foreach (['dnp', 'remark', 'createLead'] as $method) {
            $parameter = (new ReflectionMethod(KpiOutreachController::class, $method))
                ->getParameters()[1];

            $this->assertSame(
                KpiOutreachAssignment::class,
                $parameter->getType()?->getName(),
                "{$method} must type-hint the outreach assignment model."
            );
        }
    }
}
