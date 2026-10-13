<?php

namespace App\Enums;

/**
 * Categories of the Office Expense register. Each belongs to a group:
 * utilities, maintenance, operational, or payroll (which is written by
 * payroll only).
 */
enum ExpenseCategory: string
{
    // Utilities
    case Electricity = 'electricity';
    case Gas = 'gas';
    case Water = 'water';
    case Internet = 'internet';

    // Maintenance
    case Maintenance = 'maintenance';
    case Repairs = 'repairs';
    case Calibration = 'calibration';

    // Operational
    case Generator = 'generator';
    case Cleaning = 'cleaning';
    case Security = 'security';
    case Office = 'office';
    case Rent = 'rent';
    case Transport = 'transport';
    case BankCharges = 'bank_charges';
    case Licenses = 'licenses';
    case Other = 'other';

    // Payroll
    case Salary = 'salary';
    case StaffAdvance = 'staff_advance';

    public function label(): string
    {
        return match ($this) {
            self::Internet => 'Phone & internet',
            self::Repairs => 'Repairs',
            self::Calibration => 'Dispenser calibration',
            self::Generator => 'Generator fuel',
            self::Office => 'Office & refreshments',
            self::BankCharges => 'Bank charges',
            self::Licenses => 'Licences & govt fees',
            self::Salary => 'Salaries',
            self::StaffAdvance => 'Staff advances',
            default => ucfirst($this->value),
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::Electricity, self::Gas, self::Water, self::Internet => 'Utilities',
            self::Maintenance, self::Repairs, self::Calibration => 'Maintenance',
            self::Salary, self::StaffAdvance => 'Payroll',
            default => 'Operational',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Electricity => 'lightning-charge',
            self::Gas => 'fire',
            self::Water => 'droplet',
            self::Internet => 'wifi',
            self::Maintenance => 'tools',
            self::Repairs => 'wrench-adjustable',
            self::Calibration => 'speedometer',
            self::Generator => 'battery-charging',
            self::Cleaning => 'stars',
            self::Security => 'shield-check',
            self::Office => 'cup-hot',
            self::Rent => 'house',
            self::Transport => 'truck',
            self::BankCharges => 'bank',
            self::Licenses => 'patch-check',
            self::Salary => 'cash-coin',
            self::StaffAdvance => 'person-up',
            self::Other => 'three-dots',
        };
    }

    /**
     * Salaries and advances are recorded through payroll, never by hand.
     */
    public function isPayroll(): bool
    {
        return in_array($this, self::payroll(), true);
    }

    /**
     * @return list<self>
     */
    public static function payroll(): array
    {
        return [self::Salary, self::StaffAdvance];
    }

    /**
     * Categories offered on the expense form.
     *
     * @return list<self>
     */
    public static function manual(): array
    {
        return array_values(array_filter(self::cases(), fn (self $category) => ! $category->isPayroll()));
    }

    /**
     * Manual categories grouped for the form and the register.
     *
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $groups = [];

        foreach (self::manual() as $category) {
            $groups[$category->group()][] = $category;
        }

        return $groups;
    }

    /**
     * @return list<string>
     */
    public static function groups(): array
    {
        return ['Utilities', 'Maintenance', 'Operational', 'Payroll'];
    }

    /**
     * @return list<string>
     */
    public static function valuesIn(string $group): array
    {
        return array_values(array_map(
            fn (self $category) => $category->value,
            array_filter(self::cases(), fn (self $category) => $category->group() === $group),
        ));
    }
}
