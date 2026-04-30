<?php

namespace A2ZWeb\Affiliate\Policies;

use A2ZWeb\Affiliate\Models\AffiliateCommissionStatement;
use Illuminate\Database\Eloquent\Model;

class AffiliateCommissionStatementPolicy
{
    public function viewAny(Model $user): bool
    {
        return true;
    }

    public function view(Model $user, AffiliateCommissionStatement $statement): bool
    {
        return $user->getKey() === $statement->partner_user_id
            && $statement->isVisibleToAffiliate();
    }

    public function downloadPdf(Model $user, AffiliateCommissionStatement $statement): bool
    {
        return $this->view($user, $statement) && (bool) $statement->pdf_path;
    }
}
