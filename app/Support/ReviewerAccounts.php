<?php

namespace App\Support;

class ReviewerAccounts
{
    /**
     * Fixed reviewer accounts that can only access the pending review queue.
     *
     * @return list<array{name: string, email: string, password: string}>
     */
    public static function definitions(): array
    {
        return [
            [
                'name' => 'مراجع 1',
                'email' => 'reviewer1@smartcare.com.ly',
                'password' => 'Reviewer-1!2026',
            ],
            [
                'name' => 'مراجع 2',
                'email' => 'reviewer2@smartcare.com.ly',
                'password' => 'Reviewer-2!2026',
            ],
            [
                'name' => 'مراجع 3',
                'email' => 'reviewer3@smartcare.com.ly',
                'password' => 'Reviewer-3!2026',
            ],
            [
                'name' => 'مراجع 4',
                'email' => 'reviewer4@smartcare.com.ly',
                'password' => 'Reviewer-4!2026',
            ],
            [
                'name' => 'مراجع 5',
                'email' => 'reviewer5@smartcare.com.ly',
                'password' => 'Reviewer-5!2026',
            ],
            [
                'name' => 'مراجع 6',
                'email' => 'reviewer6@smartcare.com.ly',
                'password' => 'Reviewer-6!2026',
            ],
            [
                'name' => 'مراجع 7',
                'email' => 'reviewer7@smartcare.com.ly',
                'password' => 'Reviewer-7!2026',
            ],
        ];
    }

    /**
     * The three extra reviewer accounts added after the original four.
     *
     * @return list<array{name: string, email: string, password: string}>
     */
    public static function additionalDefinitions(): array
    {
        return array_slice(self::definitions(), 4);
    }

    /**
     * @return list<string>
     */
    public static function emails(): array
    {
        return array_column(self::definitions(), 'email');
    }
}
