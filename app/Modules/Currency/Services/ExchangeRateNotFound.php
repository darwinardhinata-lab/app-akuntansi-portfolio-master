<?php

// FIX: multicurrency — kurs hilang selalu gagal eksplisit, tanpa nilai asumsi.
namespace App\Modules\Currency\Services;

class ExchangeRateNotFound extends \RuntimeException {}