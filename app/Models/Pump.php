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

    /**
     * Natural, case-insensitive comparison so "Machine 2" sorts before "Machine 10"
     * and stray spaces never change the order.
     */
    public static function compareNames(string $a, string $b): int
    {
        $partsA = preg_split('/(\d+)/', mb_strtolower(trim($a)), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
        $partsB = preg_split('/(\d+)/', mb_strtolower(trim($b)), -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

        foreach ($partsA as $i => $partA) {
            if (! isset($partsB[$i])) {
                return 1;
            }

            $partB = $partsB[$i];

            // Numbers compare by value, so "04" equals 4 and comes before 10.
            $result = ctype_digit($partA) && ctype_digit($partB)
                ? (int) $partA <=> (int) $partB
                : strcmp(trim($partA), trim($partB));

            if ($result !== 0) {
                return $result;
            }
        }

        return count($partsA) <=> count($partsB);
    }
}
