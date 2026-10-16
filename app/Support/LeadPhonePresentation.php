<?php

namespace App\Support;

use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

class LeadPhonePresentation
{
    public static function forNumber($value): array
    {
        $raw = trim((string) $value);
        $result = ['display' => $raw !== '' ? $raw : 'N/A', 'dial' => null, 'copy' => null];
        if ($raw === '') return $result;
        $phones = PhoneNumberUtil::getInstance();
        $digits = PhoneNormalizer::digits($raw);
        $explicit = preg_match('/^(?:\+|00)/', $raw) === 1;
        try {
            $phone = $phones->parse($raw, 'IN');
            if ($phone->hasExtension()) $digits = substr($digits, 0, -strlen($phone->getExtension()));
            // Legacy CRM data may already contain a country code without a leading +.
            if (!$explicit && strlen($digits) > 10 && $digits[0] !== '0') {
                try {
                    $candidate = $phones->parse('+' . $digits, null);
                    if ($phones->isPossibleNumber($candidate)) {
                        if ($phone->hasExtension()) $candidate->setExtension($phone->getExtension());
                        $phone = $candidate;
                    }
                } catch (NumberParseException $error) {
                    // Fall back to the default Indian numbering plan.
                }
            }
            if (!$phones->isPossibleNumber($phone)) return $result;
            $number = $phones->format($phone, PhoneNumberFormat::E164);
            $extension = $phone->hasExtension() ? ';ext=' . $phone->getExtension() : '';
            if (!$explicit && $phone->getCountryCode() === 91 && strlen($digits) <= 11) {
                $result['display'] = '+91 ' . $phones->getNationalSignificantNumber($phone);
                if ($phone->hasExtension()) $result['display'] .= ' ext. ' . $phone->getExtension();
            }
            $result['dial'] = 'tel:' . $number . $extension;
            $result['copy'] = $number . ($phone->hasExtension() ? ' ext. ' . $phone->getExtension() : '');
        } catch (NumberParseException $error) {
            // Keep unparseable source data visible without offering an invalid dial link.
        }
        return $result;
    }
}
