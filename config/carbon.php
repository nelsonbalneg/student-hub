<?php

return [
    // SWDM v4 operational energy: data centers 0.055 + networks 0.059 + devices 0.080.
    // https://sustainablewebdesign.org/estimating-digital-emissions/
    // This simplified estimate excludes embodied emissions and uses decimal GB.
    'kwh_per_gb' => (float) env('CARBON_KWH_PER_GB', 0.194),
    'grams_co2e_per_kwh' => (float) env('CARBON_GRAMS_CO2E_PER_KWH', 494),
    'default_page_kb' => (int) env('CARBON_DEFAULT_PAGE_KB', 850),
];
