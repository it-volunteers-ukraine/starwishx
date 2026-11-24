<?php
// file: inc/launchpad/Enums/OpportunityStatus.php
declare(strict_types=1);

namespace Launchpad\Enums;

/**
 * The post statuses an opportunity has in the Launchpad.
 *
 * The values are WordPress post statuses and the TS `OpportunityStatus`
 * union (inc/launchpad/Assets/types.ts): change them together.
 */
enum OpportunityStatus: string
{
    case DRAFT   = 'draft';
    case PENDING = 'pending';
    case PUBLISH = 'publish';

    /**
     * The status as a list card shows it.
     */
    public function label(): string
    {
        return match ($this) {
            self::DRAFT   => __('Draft', 'starwishx'),
            self::PENDING => __('Pending Review', 'starwishx'),
            self::PUBLISH => __('Published', 'starwishx'),
        };
    }

    /**
     * The status as the list's filter names its group.
     */
    public function filterLabel(): string
    {
        return match ($this) {
            self::DRAFT   => __('Drafts', 'starwishx'),
            self::PENDING => __('Pending', 'starwishx'),
            self::PUBLISH => __('Published', 'starwishx'),
        };
    }

    /**
     * Can the author set it through the REST API? Publishing is moderated.
     */
    public function isClientSettable(): bool
    {
        return $this !== self::PUBLISH;
    }

    /**
     * REST validate_callback for a `status` the author sends. WordPress
     * validates the raw value (before sanitize_callback), so it may be any
     * type - tryFrom() alone would throw a TypeError on a non-string.
     */
    public static function isSettable(mixed $value): bool
    {
        return is_string($value) && self::tryFrom($value)?->isClientSettable() === true;
    }

    /**
     * The statuses among `$values`, in their order; anything else is dropped.
     *
     * @param array<mixed> $values
     * @return list<self>
     */
    public static function fromList(array $values): array
    {
        return $values
            |> (fn(array $v): array => array_filter($v, is_string(...)))
            |> (fn(array $v): array => array_map(self::tryFrom(...), $v))
            |> array_filter(...)
            |> array_values(...);
    }

    /**
     * Is `$value` a list of status values only? For a REST array argument,
     * whose raw value may be anything (WordPress validates before sanitizing).
     */
    public static function isList(mixed $value): bool
    {
        return is_array($value) && count(self::fromList($value)) === count($value);
    }

    /**
     * The values of `$statuses`, for WP_Query's post_status.
     *
     * @param list<self> $statuses
     * @return list<string>
     */
    public static function values(array $statuses): array
    {
        return array_map(static fn(self $status): string => $status->value, $statuses);
    }
}
