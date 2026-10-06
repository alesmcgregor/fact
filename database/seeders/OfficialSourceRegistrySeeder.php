<?php

namespace Database\Seeders;

use App\Models\Country;
use App\Models\Institution;
use Illuminate\Database\Seeder;

class OfficialSourceRegistrySeeder extends Seeder
{
    public function run(): void
    {
        $registry = [
            'TR' => [
                'name_key' => 'country.turkiye',
                'flag' => '🇹🇷',
                'institutions' => [
                    ['name' => 'Presidency of the Republic of Türkiye', 'type' => 'presidency', 'url' => 'https://www.tccb.gov.tr/', 'domain' => 'www.tccb.gov.tr', 'language' => 'tr'],
                    ['name' => 'Ministry of Foreign Affairs', 'type' => 'foreign_ministry', 'url' => 'https://www.mfa.gov.tr/', 'domain' => 'www.mfa.gov.tr', 'language' => 'en'],
                    ['name' => 'Ministry of National Defence', 'type' => 'defense_ministry', 'url' => 'https://www.msb.gov.tr/', 'domain' => 'www.msb.gov.tr', 'language' => 'tr'],
                    ['name' => 'Official Gazette', 'type' => 'official_gazette', 'url' => 'https://www.resmigazete.gov.tr/', 'domain' => 'www.resmigazete.gov.tr', 'language' => 'tr'],
                ],
            ],
            'AZ' => [
                'name_key' => 'country.azerbaijan',
                'flag' => '🇦🇿',
                'institutions' => [
                    ['name' => 'President of the Republic of Azerbaijan', 'type' => 'presidency', 'url' => 'https://president.az/', 'domain' => 'president.az', 'language' => 'en'],
                    ['name' => 'Ministry of Foreign Affairs', 'type' => 'foreign_ministry', 'url' => 'https://mfa.gov.az/', 'domain' => 'mfa.gov.az', 'language' => 'en'],
                    ['name' => 'Ministry of Defence', 'type' => 'defense_ministry', 'url' => 'https://mod.gov.az/', 'domain' => 'mod.gov.az', 'language' => 'en'],
                    ['name' => 'National Assembly', 'type' => 'parliament', 'url' => 'https://meclis.gov.az/', 'domain' => 'meclis.gov.az', 'language' => 'en'],
                    ['name' => 'Government Portal', 'type' => 'government_portal', 'url' => 'https://www.gov.az/', 'domain' => 'www.gov.az', 'language' => 'en'],
                ],
            ],
        ];

        foreach ($registry as $code => $countryData) {
            $country = Country::query()->updateOrCreate(
                ['code' => $code],
                ['name_key' => $countryData['name_key'], 'flag' => $countryData['flag'], 'active' => true],
            );

            foreach ($countryData['institutions'] as $priority => $institutionData) {
                $institution = Institution::query()->updateOrCreate(
                    ['country_id' => $country->id, 'name' => $institutionData['name']],
                    ['type' => $institutionData['type'], 'priority' => ($priority + 1) * 10, 'active' => true],
                );

                $institution->sources()->updateOrCreate(
                    ['url' => $institutionData['url']],
                    [
                        'label' => $institutionData['name'],
                        'domain' => $institutionData['domain'],
                        'language' => $institutionData['language'],
                        'verification_status' => 'verified',
                        'priority' => ($priority + 1) * 10,
                        'active' => true,
                        'last_verified_at' => now(),
                    ],
                );
            }
        }
    }
}
