<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReferenceMedicineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReferenceMedicine extends Model
{
    /** @use HasFactory<ReferenceMedicineFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'medicine_name',
        'principal_name',
    ];
}
