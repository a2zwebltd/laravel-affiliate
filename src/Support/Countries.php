<?php

namespace A2ZWeb\Affiliate\Support;

class Countries
{
    /**
     * @return array<string, string> ISO-2 code => country name, sorted alphabetically by name.
     */
    public static function list(): array
    {
        $list = [];

        foreach (countries() as $country) {
            $code = $country['iso_3166_1_alpha2'] ?? null;
            $name = $country['name'] ?? null;

            if ($code && $name) {
                $list[$code] = $name;
            }
        }

        asort($list, SORT_NATURAL | SORT_FLAG_CASE);

        return $list;
    }
}
