<?php
// backend/app/Actions/Mortality/CreateMortalityAction.php

namespace App\Actions\Mortality;

use App\Models\Flock;
use App\Models\FlockMortality;

class CreateMortalityAction
{
    public function __construct(
        private readonly \App\Services\PushNotificationService $notificationService
    ) {}

    /**
     * @param  array<string, mixed>  $data  (validated: entry_date, quantity, reason?, notes?)
     *
     * @throws \Exception 422 if flock is not active
     */
    public function execute(Flock $flock, int $userId, array $data): FlockMortality
    {
        // Closed/cancelled flocks get a specific message; other non-active statuses (e.g. draft)
        // fall through to the second check with a different message.
        if (in_array($flock->status, ['closed', 'cancelled'])) {
            throw new \Exception('لا يمكن تسجيل نفوق على فوج مغلق أو ملغى', 422);
        }

        if ($flock->status !== 'active') {
            throw new \Exception('لا يمكن تسجيل نفوق على فوج غير نشط', 422);
        }

        $mortality = FlockMortality::create([
            'farm_id'        => $flock->farm_id,
            'flock_id'       => $flock->id,
            'entry_date'     => $data['entry_date'],
            'quantity'       => $data['quantity'],
            'reason'         => $data['reason'] ?? null,
            'notes'          => $data['notes'] ?? null,
            'worker_id'      => $userId,
            'editable_until' => now()->addMinutes(15),
            'created_by'     => $userId,
            'updated_by'     => $userId,
        ]);

        $this->notifyRecipients($flock, $userId, $mortality);

        return $mortality;
    }

    private function notifyRecipients(Flock $flock, int $userId, FlockMortality $mortality): void
    {
        $creator = \App\Models\User::find($userId);
        if (!$creator) return;

        $farm = $flock->farm;
        $recipients = collect();

        // Logic requested by user:
        // 1. Worker records -> Notify Manager & Partners
        // 2. Manager records -> Notify Partners

        if ($creator->hasRole('worker')) {
            // Get Managers and Partners of this farm
            $recipients = $farm->members()
                ->whereHas('roles', function($q) {
                    $q->whereIn('name', ['manager', 'partner']);
                })->get();
        } elseif ($creator->hasRole('manager')) {
            // Get Managers (including self) and Partners of this farm
            $recipients = $farm->members()
                ->whereHas('roles', function($q) {
                    $q->whereIn('name', ['manager', 'partner']);
                })->get();
        }

        if ($recipients->isNotEmpty()) {
            $title = "تسجيل نفوق جديد";
            $body = "قام {$creator->name} بتسجيل {$mortality->quantity} نفوق في فوج: {$flock->name}";
            
            $this->notificationService->sendNotification($recipients, $title, $body, [
                'type' => 'mortality',
                'flock_id' => $flock->id,
                'mortality_id' => $mortality->id,
            ]);
        }
    }
}
