<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\PumpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pump extends Model
{
    /** @use HasFactory<PumpFactory> */
    use HasFactory, LogsActivity;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Nozzle, $this>
     */
    public function nozzles(): HasMany
    {
        return $this->hasMany(Nozzle::class)->orderBy('number');
    }

    public function activityLabel(): string
    {
        return 'Pump '.$this->name;
    }
}
