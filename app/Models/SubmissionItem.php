<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SubmissionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'submission_id',
        'description',
        'quantity',
        'price',
    ];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }
}

