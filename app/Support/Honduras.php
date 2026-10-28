<?php
namespace App\Support;
class Honduras
{
    public static function departments(): array { return array_keys(config('honduras-regions')); }
    public static function cities(string $department = ''): array
    {
        if ($department === '') { return array_values(array_unique(array_merge(...array_values(self::groups())))); }
        return array_merge(config('honduras-regions')[$department] ?? [], $department === 'Francisco Morazán' ? ['Tegucigalpa', 'Comayagüela'] : []);
    }
    public static function groups(): array
    {
        $groups = [];
        foreach (self::departments() as $department) { $groups[$department] = self::cities($department); }
        return $groups;
    }
    public static function departmentFor(string $city): ?string
    {
        $matches = array_keys(array_filter(self::groups(), fn ($cities) => in_array($city, $cities, true)));
        return count($matches) === 1 ? $matches[0] : null;
    }
}
