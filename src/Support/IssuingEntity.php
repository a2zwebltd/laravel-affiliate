<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Support;

use A2ZWeb\Affiliate\Exceptions\IssuingEntityNotConfigured;
use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use Illuminate\Support\Facades\Log;

/**
 * Validates the legal entity that issues commission statements.
 *
 * `code` and `legal_name` are required. Every field is also checked against
 * the placeholder values that package versions before 1.3.0 shipped as config
 * defaults, so a host that published the old config file (or never set the
 * env vars) fails loudly instead of issuing statements as "Acme Ltd.".
 */
final class IssuingEntity
{
    /** @var list<string> */
    public const REQUIRED = ['code', 'legal_name'];

    /** @var array<string, list<string>> */
    public const PLACEHOLDERS = [
        'code' => ['acme_ltd'],
        'legal_name' => ['Acme Ltd.'],
        'company_number' => ['00000000'],
        'address' => ['123 Example Street, Example City, EX1 2AB'],
        'tax_status_note' => ['Acme Ltd. is not VAT/GST registered. No VAT or GST applies to this statement.'],
    ];

    /**
     * @param  array<string, mixed>  $entity
     * @return list<string>
     */
    public static function problems(array $entity): array
    {
        $problems = [];

        foreach (self::REQUIRED as $key) {
            if (trim((string) ($entity[$key] ?? '')) === '') {
                $problems[] = "issuing_entity.{$key} is empty";
            }
        }

        foreach (self::PLACEHOLDERS as $key => $placeholders) {
            if (in_array(trim((string) ($entity[$key] ?? '')), $placeholders, true)) {
                $problems[] = "issuing_entity.{$key} still holds the placeholder default";
            }
        }

        return $problems;
    }

    /**
     * @param  array<string, mixed>|null  $entity  Defaults to config('affiliate_statements.issuing_entity').
     *
     * @throws IssuingEntityNotConfigured
     */
    public static function assertConfigured(?array $entity = null): void
    {
        self::assertValid($entity ?? (array) config('affiliate_statements.issuing_entity', []));
    }

    /**
     * Check the entity snapshot already frozen onto a statement.
     *
     * @throws IssuingEntityNotConfigured
     */
    public static function assertStatementSnapshot(AffiliateCommissionStatement $statement): void
    {
        self::assertValid([
            'code' => $statement->issuing_entity,
            'legal_name' => $statement->issuing_entity_legal_name,
            'company_number' => $statement->issuing_entity_company_number,
            'address' => $statement->issuing_entity_address,
            'tax_status_note' => $statement->issuing_entity_tax_status_note,
        ], $statement->id);
    }

    /**
     * @param  array<string, mixed>  $entity
     */
    private static function assertValid(array $entity, ?int $statementId = null): void
    {
        $problems = self::problems($entity);
        if ($problems === []) {
            return;
        }

        $exception = new IssuingEntityNotConfigured($problems);

        Log::error($exception->getMessage(), array_filter([
            'package' => 'a2zwebltd/laravel-affiliate',
            'statement_id' => $statementId,
        ]));

        throw $exception;
    }
}
