<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminTransferProof extends Model
{
    use HasFactory;

    protected $fillable = [
        'admin_approval_id',
        'file',
        'type'
    ];

    public function adminApproval()
    {
        return $this->belongsTo(AdminApproval::class);
    }   
}
