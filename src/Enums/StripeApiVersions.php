<?php

namespace Laravel\Cashier\Enums;

use Stripe\Util\ApiVersion;

enum StripeApiVersions: string {
    case DAHLIA = 'dahlia';
    case CLOVER = 'clover';
    case BASIL = 'basil';

    /**
     * Resolve current version.
     *
     * @return static
     */
    public static function current()
    {
        return self::tryFrom(ApiVersion::CURRENT_MAJOR);
    }

    /**
     * Transform `ui_mode` based on current API.
     */
    public function transformUIMode(string $uiMode): string
    {
        return match ($this) {
            StripeApiVersions::DAHLIA => match ($uiMode) {
                'embedded' => 'embedded_page',
                'custom' => 'elements',
            },
            default => $uiMode,
        };
    }
}
