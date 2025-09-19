<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction',
        'amount',
        'date',
        'account_type_id',
        'account_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function accountType()
    {
        return $this->belongsTo(AccountType::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function scopeForAccount($query, ?int $accountId)
    {
        return $accountId ? $query->where('account_id', $accountId) : $query;
    }
}
