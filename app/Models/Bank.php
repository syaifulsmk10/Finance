<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bank extends Model
{
    use HasFactory;
    
    protected $fillable = ['name'];

    public function accounts()
    {
        return $this->hasMany(BankAccount::class);
    }

    public function bankAccounts()
    {
        return $this->hasMany(BankAccount::class);
    }


    
}
