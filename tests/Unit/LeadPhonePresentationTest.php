<?php

namespace Tests\Unit;

use App\Support\LeadPhonePresentation;
use Tests\TestCase;

class LeadPhonePresentationTest extends TestCase
{
    public function test_local_numbers_receive_default_indian_prefix(): void
    {
        foreach (['9558235555'=>'+919558235555','9198765432'=>'+919198765432','09558235555'=>'+919558235555'] as $raw=>$canonical) {
            $phone=LeadPhonePresentation::forNumber($raw);
            $this->assertSame('+91 '.substr($canonical,3),$phone['display']);
            $this->assertSame('tel:'.$canonical,$phone['dial']);$this->assertSame($canonical,$phone['copy']);
        }
    }

    public function test_existing_international_codes_are_preserved(): void
    {
        foreach (['+91 95582 35555'=>'+919558235555','919558235555'=>'+919558235555','00919558235555'=>'+919558235555',
            '+44 20 7946 0958'=>'+442079460958','442079460958'=>'+442079460958','+1 (202) 555-0123'=>'+12025550123','12025550123'=>'+12025550123'] as $raw=>$canonical) {
            $phone=LeadPhonePresentation::forNumber($raw);$this->assertSame((string)$raw,$phone['display']);
            $this->assertSame('tel:'.$canonical,$phone['dial']);$this->assertSame($canonical,$phone['copy']);
        }
    }

    public function test_blank_invalid_and_extension_numbers_are_safe(): void
    {
        foreach([null,'','N/A','not a number','123'] as $raw){$phone=LeadPhonePresentation::forNumber($raw);$this->assertSame(null,$phone['dial']);$this->assertSame(null,$phone['copy']);}
        $this->assertSame('N/A',LeadPhonePresentation::forNumber(null)['display']);
        $phone=LeadPhonePresentation::forNumber('9558235555 ext. 123');
        $this->assertSame('+91 9558235555 ext. 123',$phone['display']);$this->assertSame('tel:+919558235555;ext=123',$phone['dial']);
    }
}
