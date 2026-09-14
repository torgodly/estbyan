<?php

namespace App\Support;

use Carbon\CarbonInterface;

class ReviewerWorkingHours
{
    public const string TIMEZONE = 'Africa/Tripoli';

    public const int OPENS_HOUR = 7;

    public const int OPENS_MINUTE = 0;

    public const int CLOSES_HOUR = 13;

    public const int CLOSES_MINUTE = 15;

    public static function now(): CarbonInterface
    {
        return now(self::TIMEZONE);
    }

    public static function isOpen(?CarbonInterface $at = null): bool
    {
        $moment = self::at($at);

        return $moment->gte(self::opensAt($moment))
            && $moment->lt(self::closesAt($moment));
    }

    public static function isClosed(?CarbonInterface $at = null): bool
    {
        return ! self::isOpen($at);
    }

    public static function opensAt(?CarbonInterface $at = null): CarbonInterface
    {
        return self::at($at)->copy()->setTime(self::OPENS_HOUR, self::OPENS_MINUTE);
    }

    public static function closesAt(?CarbonInterface $at = null): CarbonInterface
    {
        return self::at($at)->copy()->setTime(self::CLOSES_HOUR, self::CLOSES_MINUTE);
    }

    public static function nextOpensAt(?CarbonInterface $at = null): CarbonInterface
    {
        $moment = self::at($at);
        $opensToday = self::opensAt($moment);

        if ($moment->lt($opensToday)) {
            return $opensToday;
        }

        return $opensToday->addDay();
    }

    public static function millisecondsUntilClose(?CarbonInterface $at = null): int
    {
        $moment = self::at($at);

        if (self::isClosed($moment)) {
            return 0;
        }

        return max(0, (self::closesAt($moment)->getTimestamp() - $moment->getTimestamp()) * 1000);
    }

    public static function bannerTitle(): string
    {
        return 'انتهت جلسة المراجعة لهذا اليوم';
    }

    public static function sessionEndedTitle(): string
    {
        return 'تم إنهاء جلستك لانتهاء ساعات العمل';
    }

    public static function bannerBody(): string
    {
        return 'نلتقي غداً بإذن الله، ابتداءً من الساعة السابعة صباحاً.';
    }

    public static function bannerOrganization(): string
    {
        return 'شركة الرعاية الذكية — مصلحة الضرائب';
    }

    public static function loginBlockedMessage(): string
    {
        return 'انتهت ساعات عمل المراجعين. يمكن الدخول غداً من الساعة السابعة صباحاً.';
    }

    private static function at(?CarbonInterface $at): CarbonInterface
    {
        if ($at instanceof CarbonInterface) {
            return $at->copy()->timezone(self::TIMEZONE);
        }

        return self::now();
    }
}
