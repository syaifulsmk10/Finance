<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;

    protected $fillable = ['submission_id', 'file', 'type'];

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }



    
}
