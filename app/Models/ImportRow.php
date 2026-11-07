<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ImportRow extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['payload' => 'array', 'errors' => 'array', 'possible_duplicate' => 'boolean'];
}
