<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Appointment statuses and the allowed moves between them (spec section 7.2). Anything not listed is refused.
 */
enum AppointmentStatus: string
{
    case Scheduled = 'scheduled';
    case Confirmed = 'confirmed';
    case Arrived = 'arrived';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * The statuses this one may move to.
     *
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::Scheduled => [self::Confirmed, self::Arrived, self::Cancelled, self::NoShow],
            self::Confirmed => [self::Arrived, self::Cancelled, self::NoShow],
            self::Arrived => [self::InProgress, self::Cancelled],
            self::InProgress => [self::Completed],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    public function canMoveTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /**
     * Whether an appointment in this status occupies its time. Cancelled and no-show appointments do not (spec 7.1).
     */
    public function holdsSlot(): bool
    {
        return ! in_array($this, [self::Cancelled, self::NoShow], true);
    }
}
