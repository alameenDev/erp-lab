<?php

namespace App\Services;

use App\Models\Invoice;

class ResultHistoryService
{
    /** Completed results only; never cross patient/laboratory boundaries. */
    public function forInvoice(Invoice $invoice): array
    {
        if (! $invoice->patient_id_fk) return [];
        $cutoff = $invoice->result_date ?: $invoice->created_at;
        $query = Invoice::where('patient_id_fk', $invoice->patient_id_fk)
            ->where('lab_id_fk', $invoice->lab_id_fk)
            ->where('is_done', true)
            ->where('id', '!=', $invoice->id)
            ->where('created_at', '<=', $invoice->created_at)
            ->whereRaw('COALESCE(result_date, created_at) <= ?', [$cutoff]);
        $history = [];
        foreach (app(ResultTrendService::class)->series($query) as $series) {
            // A display name is not a safe identifier for patient result matching.
            if (empty($series['test_id'])) continue;
            $key = $series['type'].'_'.$series['test_id'];
            foreach ($series['points'] as $point) {
                $history[$key][] = [
                    'date' => $point['date'], 'date_source' => $point['date_source'],
                    'invoice_id' => $point['invoice_id'], 'item_id' => $point['item_id'],
                    'field' => $series['field'] ?: $series['name'],
                    'value' => $point['value'], 'unit' => $series['unit'],
                ];
            }
        }
        foreach ($history as &$rows) {
            usort($rows, fn ($a, $b) => strcmp($b['date'], $a['date']) ?: ($b['invoice_id'] <=> $a['invoice_id']));
        }
        unset($rows);
        return $history;
    }
}
