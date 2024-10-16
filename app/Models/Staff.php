<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Staff extends Model
{
    use HasFactory;

    protected $fillable = ['manager_id', 'staff_id'];

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function staffMember()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
