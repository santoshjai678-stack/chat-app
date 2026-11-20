<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'message',
        'delivered_at',
        'read_at',
        'file_path',
        'file_name',
        'file_type',
    ];

    public function sender()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }

    public function receiver()
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }
}

