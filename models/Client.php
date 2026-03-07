<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;
use Winter\User\Models\User;
use Winter\Storm\Support\Facades\Mail;
use Illuminate\Support\Str;
use Winter\Storm\Exception\ValidationException;
use AvalancheStudio\AvalancheCRM\Models\EmailTemplate;
use AvalancheStudio\AvalancheCRM\Models\Campaign;

/**
 * Client Model
 */
class Client extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \AvalancheStudio\AvalancheCRM\Traits\LogsActivity;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'avalanchestudio_avalanchecrm_clients';

    /**
     * @var array Guarded fields
     */
    protected $guarded = [];

    /**
     * @var array Fillable fields
     */
    protected $fillable = [];

    /**
     * @var array Validation rules for attributes
     */
    public $rules = [];

    /**
     * @var array Attributes to be cast to native types
     */
    protected $casts = [
        'marketing_opt_out' => 'boolean',
    ];

    /**
     * @var array Attributes to be cast to JSON
     */
    protected $jsonable = [];

    /**
     * @var array Attributes to be appended to the API representation of the model (ex. toArray())
     */
    protected $appends = [];

    /**
     * @var array Attributes to be removed from the API representation of the model (ex. toArray())
     */
    protected $hidden = [];

    /**
     * @var array Attributes to be cast to Argon (Carbon) instances
     */
    protected $dates = [
        'created_at',
        'updated_at',
    ];

    /**
     * @var array Relations
     */
    public $hasOne = [];
    public $hasMany = [
        'tickets' => [\AvalancheStudio\AvalancheCRM\Models\Ticket::class],
        'invoices' => [\AvalancheStudio\AvalancheCRM\Models\Invoice::class],
        'subscriptions' => [\AvalancheStudio\AvalancheCRM\Models\Subscription::class],
        'transactions' => [\AvalancheStudio\AvalancheCRM\Models\Transaction::class],
    ];
    public $hasOneThrough = [];
    public $hasManyThrough = [];
    public $belongsTo = [
        'user' => [\Winter\User\Models\User::class]
    ];
    public $belongsToMany = [
        'projects' => [
            \AvalancheStudio\AvalancheCRM\Models\Project::class,
            'table' => 'avalanchestudio_avalanchecrm_projects_clients',
            'key' => 'client_id',
            'otherKey' => 'project_id'
        ]
    ];
    public $morphTo = [];
    public $morphOne = [];
    public $morphMany = [];
    public $attachOne = [];
    public $attachMany = [];

    /**
     * Scope: only clients who have NOT opted out of marketing.
     */
    public function scopeMarketable($query)
    {
        return $query->where(function ($q) {
            $q->where('marketing_opt_out', false)
                ->orWhereNull('marketing_opt_out');
        })->whereNotNull('email');
    }

    /**
     * Generate a unique unsubscribe token for this client.
     */
    public function generateUnsubscribeToken(): string
    {
        if (!$this->unsubscribe_token) {
            $this->unsubscribe_token = Str::random(64);
            $this->save();
        }

        return $this->unsubscribe_token;
    }

    /**
     * Get the unsubscribe URL for this client.
     */
    public function getUnsubscribeUrl(): string
    {
        return url('/avalanchecrm/unsubscribe/' . $this->generateUnsubscribeToken());
    }

    /**
     * After creating a client, send the welcome email if a template exists.
     */
    public function afterCreate()
    {
        $this->sendNotification('client', 'Client Welcome');
    }

    /**
     * After saving a client, send account update notification if profile fields changed.
     */
    public function afterSave()
    {
        if ($this->wasRecentlyCreated) {
            return;
        }

        $watchedFields = ['name', 'email', 'phone', 'company'];
        if ($this->isDirty($watchedFields)) {
            $this->sendNotification('client', 'Client Account Update');
        }
    }

    /**
     * Send a notification email to this client using a named template.
     *
     * @param string $category  Template category (e.g. 'client', 'ticket')
     * @param string $name      Template name (e.g. 'Client Welcome')
     * @return bool Whether the email was sent
     */
    public function sendNotification(string $category, string $name): bool
    {
        if (empty($this->email)) {
            return false;
        }

        $template = EmailTemplate::where('category', $category)
            ->where('name', $name)
            ->where('is_active', true)
            ->first();

        if (!$template) {
            return false;
        }

        try {
            $subject = Campaign::parseTags($template->subject ?: $template->name, $this);
            $body = Campaign::parseTags($template->content, $this);

            Mail::raw(['html' => $body], function ($message) use ($subject) {
                $message->to($this->email, $this->name);
                $message->subject($subject);
            });

            return true;
        } catch (\Exception $e) {
            \Log::error('Avalanche CRM: Failed to send notification "' . $name . '" to client #' . $this->id . ': ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Hook before creating a Client to automatically create an associated User.
     */
    public function beforeCreate()
    {
        // If created via the User form, pull the name and email from the parent form data
        // to satisfy NOT NULL database constraints before the relation is saved.
        if (request()->has('User')) {
            $userData = request()->input('User');
            if (empty($this->name)) {
                $this->name = trim(($userData['name'] ?? '') . ' ' . ($userData['surname'] ?? '')) ?: ($userData['email'] ?? '');
            }
            if (empty($this->email)) {
                $this->email = $userData['email'] ?? '';
            }
            return; // Bypass the standalone auto-creation logic
        }

        // Bypass auto-creation if the Client is being created through an existing User relation
        if ($this->user_id) {
            return;
        }

        if (!$this->email) {
            throw new ValidationException(['email' => 'An email is required to create a User account.']);
        }

        // Check if user already exists with this email
        $existingUser = User::where('email', $this->email)->first();
        if ($existingUser) {
            throw new ValidationException(['email' => 'A user with this email already exists.']);
        }

        // Generate a random secure password
        $password = Str::random(12);

        // Create the new User
        $user = new User();
        $user->name = $this->name;
        $user->email = $this->email;
        $user->password = $password;
        $user->password_confirmation = $password;
        $user->is_activated = true; // Auto-activate
        $user->save();

        // Add user to Client group
        $clientGroup = \Winter\User\Models\UserGroup::where('code', 'client')->first();
        if ($clientGroup) {
            $user->groups()->add($clientGroup);
        }

        // Assign the newly created user_id to this client
        $this->user_id = $user->id;
    }
}
