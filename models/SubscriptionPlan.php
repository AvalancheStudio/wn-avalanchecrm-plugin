<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;
use Str;

/**
 * SubscriptionPlan Model
 */
class SubscriptionPlan extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \Winter\Storm\Database\Traits\Sortable;
    use \AvalancheStudio\AvalancheCRM\Traits\LogsActivity;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'avalanchestudio_avalanchecrm_subscription_plans';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price',
        'billing_cycle',
        'paypal_plan_id',
        'stripe_price_id',
        'features',
        'is_active',
        'sort_order',
    ];


    /**
     * @var array Validation rules for attributes
     */
    public $rules = [
        'name' => 'required',
        'price' => 'required|numeric|min:0',
        'billing_cycle' => 'required|in:monthly,quarterly,annual',
    ];

    /**
     * @var array Attributes to be cast to native types
     */
    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * @var array Attributes to be cast to Argon (Carbon) instances
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * Auto-generate slug from name before saving.
     */
    public function beforeSave()
    {
        if (empty($this->slug)) {
            $this->slug = Str::slug($this->name);
        }
    }

    /**
     * @var array Relations
     */
    public $hasMany = [
        'subscriptions' => [\AvalancheStudio\AvalancheCRM\Models\Subscription::class, 'key' => 'plan_id']
    ];

    public $hasOne = [];
    public $hasOneThrough = [];
    public $hasManyThrough = [];
    public $belongsTo = [];
    public $belongsToMany = [];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];
}
