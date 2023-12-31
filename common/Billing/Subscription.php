<?php namespace Common\Billing;

use App;
use App\User;
use Common\Billing\Gateways\Contracts\GatewayInterface;
use Common\Billing\Gateways\GatewayFactory;
use Carbon\Carbon;
use Common\Billing\Invoices\Invoice;
use Common\Billing\Subscriptions\SubscriptionFactory;
use Common\Search\Fields\SearchableModel;
use Common\Search\Searchable;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Class Subscription
 *
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $renews_at
 * @property-read User $user
 * @property-read BillingPlan $plan
 * @property-read Invoice $latest_invoice
 * @property string $gateway_name
 * @property string $gateway_id
 * @property integer $user_id
 * @property bool $active
 * @property int $id
 * @property int $plan_id
 * @property int $quantity
 * @property string|null $description
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read mixed $cancelled
 * @property-read mixed $on_grace_period
 * @property-read mixed $on_trial
 * @property-read mixed $valid
 * @method static \Common\Billing\Subscriptions\SubscriptionFactory factory(...$parameters)
 * @method static \Illuminate\Database\Eloquent\Builder|Subscription newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Subscription newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Subscription query()
 * @mixin \Eloquent
 * @method static \Illuminate\Database\Eloquent\Builder|Subscription matches(array $columns, string $value)
 * @method static \Illuminate\Database\Eloquent\Builder|Subscription mysqlSearch(string $query)
 */
class Subscription extends Model
{
    use HasFactory, Searchable;

    protected $guarded = ['id'];

    protected $appends = [
        'on_grace_period',
        'on_trial',
        'valid',
        'active',
        'cancelled'
    ];

    protected $casts = [
        'id' => 'integer',
        'plan_id' => 'integer',
        'quantity' => 'integer'
    ];

    public function getOnGracePeriodAttribute() {
        return $this->onGracePeriod();
    }

    public function getOnTrialAttribute() {
        return $this->onTrial();
    }

    public function getValidAttribute() {
        return $this->valid();
    }

    public function getActiveAttribute() {
        return $this->active();
    }

    public function getCancelledAttribute() {
        return $this->cancelled();
    }

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'trial_ends_at', 'ends_at', 'renews_at',
        'created_at', 'updated_at',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(BillingPlan::class);
    }

    public function latest_invoice()
    {
        return $this->hasOne(Invoice::class)
            ->orderBy('created_at', 'desc');
    }

    /**
     * Get plan or its parent (if has parent).
     *
     * @return BillingPlan
     */
    public function mainPlan()
    {
        return $this->plan->parent ?? $this->plan;
    }

    /**
     * Determine if the subscription is within its trial period.
     *
     * @return bool
     */
    public function onTrial()
    {
        if (! is_null($this->trial_ends_at)) {
            return Carbon::now()->lt($this->trial_ends_at);
        } else {
            return false;
        }
    }

    /**
     * Determine if the subscription is active, on trial, or within its grace period.
     *
     * @return bool
     */
    public function valid()
    {
        return $this->active() || $this->onTrial() || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is active.
     *
     * @return bool
     */
    public function active()
    {
        return is_null($this->ends_at) || $this->onGracePeriod();
    }

    /**
     * Determine if the subscription is no longer active.
     *
     * @return bool
     */
    public function cancelled()
    {
        return ! is_null($this->ends_at);
    }

    /**
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod()
    {
        if ( ! is_null($endsAt = $this->ends_at)) {
            return Carbon::now()->lt(Carbon::instance($endsAt));
        } else {
            return false;
        }
    }

    /**
     * Cancel the subscription at the end of the billing period.
     *
     * @param bool $atPeriodEnd
     * @return $this
     */
    public function cancel($atPeriodEnd = true)
    {
        if ($this->gateway_name !== 'none' && $this->gateway_name !== 'voucher') {
            $this->gateway()->subscriptions()->cancel($this, $atPeriodEnd);
        }

        // If the user was on trial, we will set the grace period to end when the trial
        // would have ended. Otherwise, we'll retrieve the end of the billing period
        // and make that the end of the grace period for this current user.
        if ($this->onTrial()) {
            $this->ends_at = $this->trial_ends_at;
        } else {
            $this->ends_at = $this->renews_at;
        }

        $this->renews_at = null;
        $this->save();

        return $this;
    }

    /**
     * Mark subscription as cancelled on local database
     * only, without interacting with payment gateway.
     *
     * @return void
     */
    public function markAsCancelled()
    {
        $this->fill(['ends_at' => $this->renews_at, 'renews_at' => null])->save();
    }

    /**
     * Cancel the subscription immediately and delete it from database.
     *
     * @return $this
     * @throws Exception
     */
    public function cancelAndDelete()
    {
        $this->cancel(false);
        $this->delete();

        return $this;
    }

    /**
     * Resume the cancelled subscription.
     *
     * @return $this
     */
    public function resume()
    {
        if ( ! $this->onGracePeriod()) {
            throw new LogicException('Unable to resume subscription that is not within grace period.');
        }

        if ($this->onTrial()) {
            $trialEnd = $this->trial_ends_at->getTimestamp();
        } else {
            $trialEnd = 'now';
        }

        // To resume the subscription we need to set the plan parameter on the Stripe
        // subscription object. This will force Stripe to resume this subscription
        // where we left off. Then, we'll set the proper trial ending timestamp.
        if ($this->gateway_name !== 'none' && $this->gateway_name !== 'voucher') {
            $this->gateway()->subscriptions()->resume($this, ['trial_end' => $trialEnd]);
        }


        // Finally, we will remove the ending timestamp from the user's record in the
        // local database to indicate that the subscription is active again and is
        // no longer "cancelled". Then we will save this record in the database.
        $this->renews_at = $this->ends_at;
        $this->ends_at = null;
        $this->save();

        return $this;
    }

    /**
     * Swap the subscription to a new billing plan.
     *
     * @param BillingPlan $plan
     * @return $this
     */
    public function changePlan(BillingPlan $plan)
    {
        $this->gateway()->subscriptions()->changePlan($this, $plan);

        $this->fill(['plan_id' => $plan->id, 'ends_at' => null])->save();

        return $this;
    }

    public function changePlanForVoucher(BillingPlan $plan)
    {
        $this->fill(['plan_id' => $plan->id, 'ends_at' => null])->save();

        return $this;
    }

    /**
     * Get gateway this subscriptions was created with.
     */
    public function gateway(): GatewayInterface
    {
        return App::make(GatewayFactory::class)->get($this->gateway_name);
    }

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'plan_id' => $this->plan_id,
            'gateway_name' => $this->gateway_name,
            'user' => $this->user ? $this->user->getSearchableValues() : null,
            'description' => $this->description,
            'ends_at' => $this->ends_at,
            'created_at' => $this->created_at->timestamp ?? '_null',
            'updated_at' => $this->updated_at->timestamp ?? '_null',
        ];
    }

    protected function makeAllSearchableUsing($query)
    {
        return $query->with(['user']);
    }

    public static function filterableFields(): array
    {
        return [
            'id',
            'plan_id',
            'gateway_name',
            'ends_at',
            'created_at',
            'updated_at',
        ];
    }

    protected static function newFactory()
    {
        return SubscriptionFactory::new();
    }
}
