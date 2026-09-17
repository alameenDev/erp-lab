<?php

namespace App\Services;

use App\Models\LoyaltyTransaction;
use App\Models\Patient;
use App\Models\User;

class LoyaltyService
{
    /**
     * Default program configuration. Each lab can override any of these
     * keys via lab_settings.loyalty_config (merged on top of this array).
     */
    public static function defaultConfig(): array
    {
        return [
            'enabled' => true,
            'require_otp' => false,
            'points_per_currency' => 0.01, // e.g. 1000 spent -> 10 points at 1x
            'welcome_bonus' => 50,
            'checkup_bonus' => 20,
            'review_bonus' => 15,
            'referral_bonus' => 30,
            'points_expiry_months' => 12,
            'tiers' => [
                ['key' => 'silver', 'label_ar' => 'الفئة الفضية', 'label_en' => 'Silver', 'min_yearly_points' => 0, 'multiplier' => 1.0],
                ['key' => 'gold', 'label_ar' => 'الفئة الذهبية', 'label_en' => 'Gold', 'min_yearly_points' => 500, 'multiplier' => 1.25],
                ['key' => 'platinum', 'label_ar' => 'الفئة البلاتينية', 'label_en' => 'Platinum', 'min_yearly_points' => 1500, 'multiplier' => 1.5],
            ],
            'redemption_catalog' => [
                ['key' => 'random_sugar_free', 'label_ar' => 'فحص سكر عشوائي مجاناً', 'points' => 80],
                ['key' => 'vitamin_discount', 'label_ar' => 'خصم على فحص فيتامين D أو B12', 'points' => 100],
                ['key' => 'cbc_free', 'label_ar' => 'فحص دم عام (CBC) مجاناً', 'points' => 200],
            ],
        ];
    }

    /**
     * Effective config for a lab: defaults merged with that lab's overrides.
     */
    public function config(User $lab): array
    {
        $defaults = self::defaultConfig();
        $override = $lab->labSetting?->loyalty_config ?? [];

        $merged = array_merge($defaults, is_array($override) ? $override : []);

        // Tiers/catalog are whole-list overrides if provided, otherwise defaults.
        if (empty($merged['tiers']) || ! is_array($merged['tiers'])) {
            $merged['tiers'] = $defaults['tiers'];
        }
        if (empty($merged['redemption_catalog']) || ! is_array($merged['redemption_catalog'])) {
            $merged['redemption_catalog'] = $defaults['redemption_catalog'];
        }

        return $merged;
    }

    /**
     * Current non-expired point balance, computed from the ledger
     * (source of truth) rather than trusting the cached column.
     */
    public function balance(Patient $patient): int
    {
        return (int) LoyaltyTransaction::where('patient_id_fk', $patient->id)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->sum('points');
    }

    /**
     * Points earned within the current rolling membership year
     * (used to determine tier). Redemptions/expiry are excluded.
     */
    public function yearlyEarnedPoints(Patient $patient): int
    {
        return (int) LoyaltyTransaction::where('patient_id_fk', $patient->id)
            ->where('points', '>', 0)
            ->where('created_at', '>=', now()->subYear())
            ->sum('points');
    }

    public function currentTier(array $config, int $yearlyPoints): array
    {
        $tiers = $config['tiers'];
        usort($tiers, fn ($a, $b) => $a['min_yearly_points'] <=> $b['min_yearly_points']);

        $current = $tiers[0];
        foreach ($tiers as $tier) {
            if ($yearlyPoints >= $tier['min_yearly_points']) {
                $current = $tier;
            }
        }

        return $current;
    }

    public function nextTier(array $config, int $yearlyPoints): ?array
    {
        $tiers = $config['tiers'];
        usort($tiers, fn ($a, $b) => $a['min_yearly_points'] <=> $b['min_yearly_points']);

        foreach ($tiers as $tier) {
            if ($tier['min_yearly_points'] > $yearlyPoints) {
                return $tier;
            }
        }

        return null;
    }

    /**
     * Recompute and persist the patient's cached loyalty summary columns.
     */
    public function refreshSummary(Patient $patient, User $lab): array
    {
        $config = $this->config($lab);
        $balance = $this->balance($patient);
        $yearly = $this->yearlyEarnedPoints($patient);
        $tier = $this->currentTier($config, $yearly);

        $patient->loyalty_points = $balance;
        $patient->loyalty_tier = $tier['key'];
        $patient->loyalty_year_points = $yearly;
        $patient->save();

        return [
            'balance' => $balance,
            'yearly_points' => $yearly,
            'tier' => $tier,
            'next_tier' => $this->nextTier($config, $yearly),
        ];
    }

    /**
     * Award points to a patient and record the ledger entry.
     * $points is the BASE amount before the tier multiplier is applied
     * (multiplier uses the patient's tier at the time of earning).
     */
    public function awardPoints(
        Patient $patient,
        User $lab,
        string $type,
        int $points,
        ?string $description = null,
        $reference = null
    ): ?LoyaltyTransaction {
        $config = $this->config($lab);
        if (! ($config['enabled'] ?? true) || $points <= 0) {
            return null;
        }

        $yearly = $this->yearlyEarnedPoints($patient);
        $tier = $this->currentTier($config, $yearly);
        $finalPoints = (int) round($points * ($tier['multiplier'] ?? 1));
        if ($finalPoints <= 0) {
            return null;
        }

        $transaction = LoyaltyTransaction::create([
            'patient_id_fk' => $patient->id,
            'lab_id_fk' => $lab->id,
            'type' => $type,
            'points' => $finalPoints,
            'description' => $description,
            'reference_type' => $reference ? get_class($reference) : null,
            'reference_id' => $reference?->id,
            'expires_at' => now()->addMonths((int) ($config['points_expiry_months'] ?? 12)),
        ]);

        $this->refreshSummary($patient, $lab);

        return $transaction;
    }

    /**
     * Award points proportional to a paid amount (purchase earning).
     */
    public function awardForPayment(Patient $patient, User $lab, float $amount, $paymentReference = null): ?LoyaltyTransaction
    {
        $config = $this->config($lab);
        $base = (int) floor($amount * ($config['points_per_currency'] ?? 0.01));
        if ($base <= 0) {
            return null;
        }

        return $this->awardPoints($patient, $lab, 'purchase', $base, 'نقاط شراء', $paymentReference);
    }

    /**
     * One-time welcome bonus the first time a patient opens their portal link.
     */
    public function awardWelcomeBonusIfNeeded(Patient $patient, User $lab): void
    {
        if ($patient->loyalty_joined_at) {
            return;
        }

        $config = $this->config($lab);
        $patient->loyalty_joined_at = now();
        $patient->save();

        $bonus = (int) ($config['welcome_bonus'] ?? 0);
        if ($bonus > 0) {
            $this->awardPoints($patient, $lab, 'welcome', $bonus, 'نقاط ترحيبية');
        }
    }

    /**
     * Redeem a catalog item by key. Returns the catalog entry on success.
     *
     * @throws \RuntimeException when the item is unknown or balance is short
     */
    public function redeem(Patient $patient, User $lab, string $catalogKey): array
    {
        $config = $this->config($lab);
        $item = collect($config['redemption_catalog'])->firstWhere('key', $catalogKey);
        if (! $item) {
            throw new \RuntimeException('صنف الاستبدال غير موجود');
        }

        $balance = $this->balance($patient);
        if ($balance < $item['points']) {
            throw new \RuntimeException('رصيد النقاط غير كافٍ');
        }

        LoyaltyTransaction::create([
            'patient_id_fk' => $patient->id,
            'lab_id_fk' => $lab->id,
            'type' => 'redemption',
            'points' => -1 * (int) $item['points'],
            'description' => 'استبدال: '.($item['label_ar'] ?? $catalogKey),
        ]);

        $this->refreshSummary($patient, $lab);

        return $item;
    }
}
