<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, LogsActivity;

    /**
     * Common roles at a filling station, offered as suggestions on the form.
     */
    public const DESIGNATIONS = ['Manager', 'Cashier', 'Pump attendant', 'Security guard', 'Helper', 'Tyre and oil service'];

    /**
     * Uploadable documents and the column holding each file's path.
     *
     * @var array<string, string>
     */
    public const DOCUMENTS = [
        'photo' => 'photo_path',
        'cnic_front' => 'cnic_front_path',
        'cnic_back' => 'cnic_back_path',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'designation',
        'phone',
        'cnic_number',
        'joined_on',
        'monthly_salary',
        'is_active',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'joined_on' => 'date',
            'monthly_salary' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<SalaryAdvance, $this>
     */
    public function advances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class);
    }

    /**
     * @return HasMany<SalarySlip, $this>
     */
    public function salarySlips(): HasMany
    {
        return $this->hasMany(SalarySlip::class);
    }

    public function hasDocument(string $document): bool
    {
        return isset(self::DOCUMENTS[$document]) && filled($this->{self::DOCUMENTS[$document]});
    }

    /**
     * Owner-only URL of a stored document, or null when none is uploaded.
     * The file path is part of the cache key, so a new upload shows at once.
     */
    public function documentUrl(string $document): ?string
    {
        if (! $this->hasDocument($document)) {
            return null;
        }

        return route('employees.documents', [$this, $document]).'?v='.substr(md5($this->{self::DOCUMENTS[$document]}), 0, 8);
    }

    /**
     * "Muhammad Aslam" → "MA", for the avatar tile.
     */
    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim($this->name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $word) => mb_strtoupper(mb_substr($word, 0, 1)))
            ->join('');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function activityLabel(): string
    {
        return 'Employee '.$this->name;
    }
}
