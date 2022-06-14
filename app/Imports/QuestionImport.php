<?php

namespace App\Imports;


use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;


class QuestionImport implements ToCollection, WithHeadingRow
{
    /**
    * @param Collection $collection
    */

    public function headingRow(): int
    {
        return 8;
    }

    public function collection(Collection $collection)
    {
       return $collection;
    }
}
