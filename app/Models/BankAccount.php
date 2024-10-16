<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'bank_id',
        'account_name',
        'account_number',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Relasi dengan Bank
    public function bank()
    {
        return $this->belongsTo(Bank::class);
    }
}
