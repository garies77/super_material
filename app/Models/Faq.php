<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Faq extends Model
{
    protected $guarded = [];

    public function faqable(): MorphTo
    {
        return $this->morphTo();
    }
}
