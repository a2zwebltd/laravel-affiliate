<?php

use A2ZWeb\Affiliate\Events\StatementIssued;
use A2ZWeb\Affiliate\Models\AffiliateCommission;
use A2ZWeb\Affiliate\Models\AffiliatePartner;
use A2ZWeb\Affiliate\Services\CommissionStatementGenerator;
use A2ZWeb\Affiliate\Services\CommissionStatementIssuer;
use A2ZWeb\Affiliate\Tests\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;

function issuedStatementFor(User $user)
{
    $partner = AffiliatePartner::create([
        'user_id' => $user->id,
        'code' => 'STMT'.$user->id,
        'status' => AffiliatePartner::STATUS_APPROVED,
        'billing_full_name' => 'Jane Partner',
        'billing_address' => '1 Partner Road',
        'country_of_tax_residence' => 'PL',
    ]);
    AffiliateCommission::create([
        'partner_user_id' => $user->id,
        'referred_user_id' => 4242,
        'period_year' => 2026,
        'period_month' => 3,
        'source_amount_cents' => 10000,
        'commission_amount_cents' => 3000,
        'commission_rate_bp' => 3000,
        'currency' => 'usd',
        'status' => AffiliateCommission::STATUS_CLOSED,
    ]);

    $draft = app(CommissionStatementGenerator::class)
        ->generateForPartner($partner, Carbon::create(2026, 3, 1), Carbon::create(2026, 3, 31));

    return app(CommissionStatementIssuer::class)->issue($draft);
}

it('ships the default partner statement view', function () {
    expect(config('affiliate.partner_statement_view'))->toBe('affiliate::partner-statement');
    expect(view()->exists('affiliate::partner-statement'))->toBeTrue();
});

it('renders the statement page for its partner without any host view configured', function () {
    Bus::fake();
    Event::fake([StatementIssued::class]);

    $user = User::create(['email' => 'stmt_'.uniqid().'@x.com']);
    $statement = issuedStatementFor($user);

    $this->actingAs($user)
        ->get(route('affiliate.statements.show', $statement))
        ->assertOk()
        ->assertSee($statement->statement_number)
        ->assertSee('Test Entity Ltd.')
        ->assertSee('Jane Partner')
        ->assertSee('cust-#4242')
        ->assertSee('30.00');
});

it('falls back to the bundled view when the configured view is empty', function () {
    Bus::fake();
    Event::fake([StatementIssued::class]);
    config()->set('affiliate.partner_statement_view', '');

    $user = User::create(['email' => 'stmt_'.uniqid().'@x.com']);
    $statement = issuedStatementFor($user);

    $this->actingAs($user)
        ->get(route('affiliate.statements.show', $statement))
        ->assertOk()
        ->assertSee($statement->statement_number);
});

it('hides the statement from other users', function () {
    Bus::fake();
    Event::fake([StatementIssued::class]);

    $owner = User::create(['email' => 'own_'.uniqid().'@x.com']);
    $other = User::create(['email' => 'oth_'.uniqid().'@x.com']);
    $statement = issuedStatementFor($owner);

    $this->actingAs($other)
        ->get(route('affiliate.statements.show', $statement))
        ->assertNotFound();
});
