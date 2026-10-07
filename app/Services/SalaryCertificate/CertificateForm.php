<?php

namespace App\Services\SalaryCertificate;

use App\Models\SalaryCertificate;
use App\Models\User;
use App\Support\TaxProfile;

/**
 * The figures a certificate review starts from: what the user confirmed
 * before, or what was read from the document plus the user's tax profile.
 */
final class CertificateForm
{
    public static function defaults(SalaryCertificate $certificate, ?User $user): array
    {
        if ($certificate->data) {
            return self::complete($certificate->data);
        }

        $found = $certificate->extracted['figures'] ?? [];
        $meta = $certificate->extracted['meta'] ?? [];
        $profile = TaxProfile::for($user);

        $components = [];
        foreach (array_keys(config('salary_certificate.components')) as $key) {
            $components[$key] = (float) ($found[$key] ?? 0);
        }
        $perks = [];
        foreach (array_keys(config('salary.perks')) as $key) {
            $perks[$key] = (float) ($found[$key] ?? 0);
        }
        // Your own and your employer's PF contributions both count as investment for the rebate.
        $investments = array_fill_keys(array_keys(config('tax.instruments')), 0.0);
        $investments['provident_fund'] = (float) ($found['employee_pf'] ?? 0) + (float) ($found['employer_pf'] ?? 0);

        return self::complete($profile->taxInput() + [
            'year' => $meta['year'] ?? config('tax.default_year'),
            'employer' => $meta['employer'] ?? $profile->get('employer'),
            'employee' => $meta['employee'] ?? $user?->name,
            'tin' => $meta['tin'] ?? null,
            'components' => $components,
            'perks' => $perks,
            'tds' => (float) ($found['tds'] ?? 0),
            'investments' => $investments,
            'filing' => 'standard',
            'notes' => null,
        ]);
    }

    /** Every key present, unknown keys dropped, amounts as numbers. */
    public static function complete(array $data): array
    {
        $groups = [
            'components' => array_keys(config('salary_certificate.components')),
            'perks' => array_keys(config('salary.perks')),
            'investments' => array_keys(config('tax.instruments')),
        ];
        foreach ($groups as $group => $keys) {
            $clean = [];
            foreach ($keys as $key) {
                $clean[$key] = max(0.0, round((float) ($data[$group][$key] ?? 0)));
            }
            $data[$group] = $clean;
        }
        $data['tds'] = max(0.0, round((float) ($data['tds'] ?? 0)));
        $data['disabled_children'] = (int) ($data['disabled_children'] ?? 0);
        $data['new_taxpayer'] = (bool) ($data['new_taxpayer'] ?? false);
        $data['category'] ??= 'general';
        $data['filing'] ??= 'standard';
        $data['year'] ??= config('tax.default_year');
        foreach (['employer', 'employee', 'tin', 'notes'] as $text) {
            $data[$text] = filled($data[$text] ?? null) ? trim((string) $data[$text]) : null;
        }

        return $data;
    }

    /** Total salary received, as Schedule 1 counts it: every component and perk. */
    public static function gross(array $data): float
    {
        return array_sum($data['components']) + array_sum($data['perks']);
    }
}
