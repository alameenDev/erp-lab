<?php

namespace App\Services;

use App\Models\{Invoice, User};
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class OwnerAccountingReport extends AccountingReportService
{
    public function __construct(User $owner, private array $range, private array $accountIds)
    {
        parent::__construct($owner, $range);
    }

    public function scope(): Builder
    {
        return Invoice::whereIn('invoices.lab_id_fk', $this->accountIds);
    }

    protected function dateRange($query, string $column): void
    {
        $query->where($column, '>=', Carbon::parse($this->range['from'], 'Asia/Baghdad')->startOfDay()->setTimezone(config('app.timezone')))
            ->where($column, '<', Carbon::parse($this->range['to'], 'Asia/Baghdad')->addDay()->startOfDay()->setTimezone(config('app.timezone')));
    }

    protected function period(string $date, bool $monthly): string
    {
        return Carbon::parse($date, config('app.timezone'))->setTimezone('Asia/Baghdad')->format($monthly ? 'Y-m' : 'Y-m-d');
    }
}
