<?php

namespace Laravel\Cashier;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Subscription
 *
 * @package Laravel\Cashier
 * @property-read HasGatewayId|Billable|Model $owner
 * @method static swap(string $plan)
 * @method static cancel()
 * @method static cancelNow()
 * @method static resume()
 */
class Subscription extends Model
{
    use HasGatewayId;

    /**
     * The attributes that are not mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'trial_ends_at',
        'ends_at',
        'created_at',
        'updated_at',
    ];

    /**
     * This subscription's manager.
     *
     * @var \Laravel\Cashier\SubscriptionManager
     */
    protected $subscriptionManager;

    /**
     * Get the user that owns the subscription.
     */
    public function user()
    {
        return $this->owner();
    }

    /**
     * Get the model related to the subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function owner()
    {
        $model = Cashier::getModelName();

        $model = new $model;

        return $this->belongsTo(get_class($model), $model->getForeignKey());
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
     * Determine if the subscription is within its grace period after cancellation.
     *
     * @return bool
     */
    public function onGracePeriod()
    {
        if (! is_null($endsAt = $this->ends_at)) {
            return Carbon::now()->lt(Carbon::instance($endsAt));
        } else {
            return false;
        }
    }

    /**
     * Determine if the subscription is within its trial period.
     *
     * @return bool
     */
    public function onTrial()
    {
        if (! is_null($this->trial_ends_at)) {
            return Carbon::today()->lt($this->trial_ends_at);
        } else {
            return false;
        }
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
     * Force the trial to end immediately.
     *
     * This method must be combined with swap, resume, etc.
     *
     * @return $this
     */
    public function skipTrial()
    {
        $this->trial_ends_at = null;

        return $this;
    }

    /**
     * Mark the subscription as cancelled.
     *
     * @return void
     */
    public function markAsCancelled()
    {
        $this->fill(['ends_at' => Carbon::now()])->save();
    }

    public function getPaymentGatewayPlanAttribute()
    {
        // FIXME
    }

    public function createAsCustomer($gateway, $token, array $options = [])
    {
        $oldMethodName = 'createAs'.Str::studly($gateway).'Customer';
        if (method_exists($this, $oldMethodName)) {
            return $this->$oldMethodName($token, $options);
        }
    }

    public function asCustomer($gateway)
    {
        $oldMethodName = 'as'.Str::studly($gateway).'Customer';
        if (method_exists($this, $oldMethodName)) {
            return $this->$oldMethodName();
        }
    }

    public function __call($method, $parameters)
    {
        $manager = $this->getSubscriptionManager();
        if (method_exists($manager, $method)) {
            return $manager->$method(...$parameters);
        }

        return parent::__call($method, $parameters);
    }

    protected function getSubscriptionManager()
    {
        if (null === $this->subscriptionManager) {
            $this->subscriptionManager = $this->getGateway()->manageSubscription($this);
        }

        return $this->subscriptionManager;
    }

    protected function getGateway()
    {
        return Cashier::gateway($this->getPaymentGatewayAttribute());
    }
}
