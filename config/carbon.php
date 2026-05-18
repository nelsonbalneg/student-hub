<?php

return [
    'kwh_per_gb' => (float) env('CARBON_KWH_PER_GB', 0.81),
    'grams_co2e_per_kwh' => (float) env('CARBON_GRAMS_CO2E_PER_KWH', 475),
    'default_page_kb' => (int) env('CARBON_DEFAULT_PAGE_KB', 850),
];
