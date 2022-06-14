<?php


namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;

class DormitorySubject extends Pivot
{
    protected $table = 'dormitory_subjects';
}
