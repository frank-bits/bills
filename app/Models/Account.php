<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'balance',
        'due',
        'avoid_interest_date',
        'account_type_id',
    'monthly_due_date_day',
    ];

    protected $casts = [
        'balance' => 'decimal:2',
    'due' => 'date',
        'avoid_interest_date' => 'date',
    'monthly_due_date_day' => 'integer',
    ];

    protected $appends = ['next_due_date'];

    public function accountType()
    {
        return $this->belongsTo(AccountType::class, 'account_type_id');
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Convenience accessor
    public function getTypeNameAttribute(): ?string
    {
        return $this->accountType?->name;
    }

    public function getNextDueDateAttribute(): ?string
    {
        $day = $this->monthly_due_date_day;
        if (! $day) {
            return null;
        }

        $today = Carbon::today();
        // This month's candidate
        $candidate = Carbon::create(
            $today->year,
            $today->month,
            min($day, $today->daysInMonth)
        );

        if ($candidate->lessThan($today)) {
            $next = $today->copy()->addMonth();
            $candidate = Carbon::create(
                $next->year,
                $next->month,
                min($day, $next->daysInMonth)
            );
        }

        return $candidate->toDateString();
    }
}
