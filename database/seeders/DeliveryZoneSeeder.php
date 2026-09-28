<?php

namespace Database\Seeders;

use App\Models\DeliveryZone;
use Illuminate\Database\Seeder;

/**
 * TDD §3.4 module 25: "Zimbabwe's administrative hierarchy seeded at
 * launch." This is a representative starter set (province -> a few major
 * cities/areas each), not the exhaustive gazetteer — a full ward-level
 * dataset is flagged in CHANGELOG.md as a pre-launch (Run 1.9 migration
 * stage) data-entry task, not an engineering one.
 */
class DeliveryZoneSeeder extends Seeder
{
    public function run(): void
    {
        $provinces = [
            'Harare' => [
                'Harare' => ['Avondale', 'Borrowdale', 'CBD', 'Mbare'],
            ],
            'Bulawayo' => [
                'Bulawayo' => ['CBD', 'Hillside', 'Nkulumane'],
            ],
            'Manicaland' => [
                'Mutare' => ['CBD', 'Dangamvura'],
            ],
            'Mashonaland Central' => [
                'Bindura' => ['CBD'],
            ],
            'Mashonaland East' => [
                'Marondera' => ['CBD'],
            ],
            'Mashonaland West' => [
                'Chinhoyi' => ['CBD'],
            ],
            'Masvingo' => [
                'Masvingo' => ['CBD'],
            ],
            'Matabeleland North' => [
                'Victoria Falls' => ['CBD'],
            ],
            'Matabeleland South' => [
                'Gwanda' => ['CBD'],
            ],
            'Midlands' => [
                'Gweru' => ['CBD'],
                'Kwekwe' => ['CBD'],
            ],
        ];

        foreach ($provinces as $provinceName => $cities) {
            $province = DeliveryZone::create(['level' => 'province', 'name' => $provinceName]);

            foreach ($cities as $cityName => $areas) {
                $city = DeliveryZone::create(['parent_id' => $province->id, 'level' => 'city', 'name' => $cityName]);

                foreach ($areas as $areaName) {
                    DeliveryZone::create(['parent_id' => $city->id, 'level' => 'area', 'name' => $areaName]);
                }
            }
        }
    }
}
