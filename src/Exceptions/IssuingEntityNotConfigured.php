<?php

declare(strict_types=1);

namespace A2ZWeb\Affiliate\Exceptions;

use RuntimeException;

/**
 * Thrown when a commission statement would be generated or issued while the
 * `affiliate_statements.issuing_entity` config is missing required values or
 * still carries the package's old "Acme Ltd." placeholders. Statements are
 * immutable legal documents, so the package refuses rather than freezing
 * placeholder data into them.
 */
class IssuingEntityNotConfigured extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(
            'Affiliate statement issuing entity is not configured: '.implode('; ', $problems)
            .'. Set the AFFILIATE_STATEMENTS_* env vars (see config/affiliate_statements.php) before generating or issuing statements.'
        );
    }
}
