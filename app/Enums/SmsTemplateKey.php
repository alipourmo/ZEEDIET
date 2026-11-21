<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The SMS templates from spec section 9. Each has a default Persian text, which the admin may edit.
 */
enum SmsTemplateKey: string
{
    case Otp = 'otp';
    case AppointmentConfirmed = 'appointment_confirmed';
    case AppointmentReminder = 'appointment_reminder';
    case AppointmentRescheduled = 'appointment_rescheduled';
    case AppointmentCancelled = 'appointment_cancelled';
    case ReportReady = 'report_ready';
    case Welcome = 'welcome';

    /** Every placeholder a template body may use (spec section 9). */
    public const PLACEHOLDERS = ['name', 'date', 'time', 'service', 'clinic', 'code'];

    public function defaultBody(): string
    {
        return match ($this) {
            self::Otp => 'کد ورود شما به پنل {clinic}: {code}',
            self::AppointmentConfirmed => '{name} عزیز، نوبت شما برای {service} در تاریخ {date} ساعت {time} ثبت شد. {clinic}',
            self::AppointmentReminder => '{name} عزیز، یادآوری نوبت {service} فردا ساعت {time}. {clinic}',
            self::AppointmentRescheduled => '{name} عزیز، نوبت شما به {date} ساعت {time} منتقل شد. {clinic}',
            self::AppointmentCancelled => '{name} عزیز، نوبت شما لغو شد. برای نوبت جدید با ما تماس بگیرید. {clinic}',
            self::ReportReady => '{name} عزیز، گزارش شما آماده مشاهده است. {clinic}',
            self::Welcome => '{name} عزیز، به {clinic} خوش آمدید. پرونده شما ایجاد شد.',
        };
    }

    public function label(): string
    {
        return __('sms.keys.'.$this->value);
    }

    /** Marketing-type messages wait for the quiet hours to end (spec section 9). Reminders and OTP do not. */
    public function isMarketing(): bool
    {
        return $this === self::Welcome;
    }

    /** The OTP's code is never stored in the log in clear (spec 7.5). */
    public function redactsCode(): bool
    {
        return $this === self::Otp;
    }

    /**
     * The placeholders this template may use. Only the OTP may use {code}.
     *
     * @return list<string>
     */
    public function allowedPlaceholders(): array
    {
        return $this === self::Otp
            ? self::PLACEHOLDERS
            : array_values(array_diff(self::PLACEHOLDERS, ['code']));
    }
}
