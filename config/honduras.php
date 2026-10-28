<?php
// Compatibility list; department-aware selectors use App\Support\Honduras.
$regions = require __DIR__.'/honduras-regions.php';
return ['cities' => array_values(array_unique(array_merge(...array_values($regions))))];
