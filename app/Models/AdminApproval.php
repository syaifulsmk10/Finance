<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminApproval extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'submission_id', 'status', 'notes', 'approved_at', 'deleted_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function submission()
    {
        return $this->belongsTo(Submission::class);
    }

    public function transferProof()
    {
        return $this->hasOne(AdminTransferProof::class);
    }
}
