<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'purpose',
        'submission_date',
        'due_date',
        'finish_status',
        'amount',
        'bank_name',        // Nama bank
        'account_name',     // Nama pemegang akun
        'account_number',  
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function adminApprovals()
    {
        return $this->hasMany(AdminApproval::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function items()
    {
        return $this->hasMany(SubmissionItem::class);
    }

  
}
