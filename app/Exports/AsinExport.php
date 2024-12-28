<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

class AsinExport implements FromArray
{
    protected $asins;

    public function __construct(array $asins)
    {
        $this->asins = $asins;
    }

    public function array(): array
    {
        // Return ASINs as a column
        return array_map(function($asin) {
            return [$asin]; // Each ASIN in its own row
        }, $this->asins);
    }
}