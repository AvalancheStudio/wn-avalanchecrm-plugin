<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;

class EmailTemplate extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \AvalancheStudio\AvalancheCRM\Traits\LogsActivity;


    public $table = 'avalanchestudio_avalanchecrm_email_templates';

    protected $guarded = ['*'];

    protected $fillable = [
        'name',
        'category',
        'subject',
        'content',
        'is_active',
    ];

    public $rules = [
        'name' => 'required',
        'category' => 'required',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * Category options for the dropdown.
     */
    public function getCategoryOptions(): array
    {
        return [
            'marketing' => 'Marketing',
            'client' => 'Client Notifications',
            'project' => 'Project Notifications',
            'ticket' => 'Ticket Notifications',
            'invoice' => 'Invoice Notifications',
            'subscription' => 'Subscription Notifications',
        ];
    }

    /**
     * Scope: templates in a specific category.
     */
    public function scopeCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Scope: marketing templates only.
     */
    public function scopeMarketing($query)
    {
        return $query->where('category', 'marketing');
    }

    /**
     * Scope: only active templates.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get category label attribute.
     */
    public function getCategoryLabelAttribute(): string
    {
        $options = $this->getCategoryOptions();
        return $options[$this->category] ?? ucfirst($this->category);
    }
}
