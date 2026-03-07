<?php

namespace AvalancheStudio\AvalancheCRM\Models;

use Winter\Storm\Database\Model;
use Winter\User\Models\User;
use Winter\User\Models\UserGroup;
use Backend\Models\User as BackendUser;
use Backend\Models\UserRole;
use Winter\Storm\Support\Facades\Mail;
use AvalancheStudio\AvalancheCRM\Models\EmailTemplate;
use AvalancheStudio\AvalancheCRM\Models\Campaign;
use Illuminate\Support\Str;
use Winter\Storm\Exception\ValidationException;

/**
 * Staff Model
 */
class Staff extends Model
{
    use \Winter\Storm\Database\Traits\Validation;
    use \AvalancheStudio\AvalancheCRM\Traits\LogsActivity;


    /**
     * @var string The database table used by the model.
     */
    public $table = 'avalanchestudio_avalanchecrm_staff';

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
     * @var array Relations
     */
    public $belongsTo = [
        'user' => [\Winter\User\Models\User::class]
    ];

    /**
     * Hook before creating Staff to automatically create associated User.
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

        // Bypass auto-creation if we already have a user_id
        if ($this->user_id) {
            return;
        }

        // Check if user already exists
        $existingUser = User::where('email', $this->email)->first();
        if ($existingUser) {
            throw new ValidationException(['email' => 'A user with this email already exists in the system.']);
        }

        // Generate password
        $password = Str::random(12);

        // Create User
        $user = new User();
        $user->name = $this->name;
        // If name contains a space, try to split it into name and surname for the User model
        $nameParts = explode(' ', $this->name, 2);
        if (count($nameParts) > 1) {
            $user->name = $nameParts[0];
            $user->surname = $nameParts[1];
        }
        $user->email = $this->email;
        $user->password = $password;
        $user->password_confirmation = $password;
        $user->is_activated = true;
        $user->save();

        // Add to Staff group
        $staffGroup = UserGroup::where('code', 'staff')->first();
        if ($staffGroup) {
            $user->groups()->add($staffGroup);
        }

        $this->user_id = $user->id;

        // Create a backend administrator account with the CRM Staff role
        $this->createBackendUser($password);
    }

    /**
     * Create a backend (admin) user for this staff member with the CRM Staff role.
     */
    protected function createBackendUser(string $password = null): void
    {
        // Skip if a backend user already exists with this email
        if (BackendUser::where('email', $this->email)->exists()) {
            // Link existing backend user
            $existing = BackendUser::where('email', $this->email)->first();
            $this->backend_user_id = $existing->id;

            // Ensure they have the CRM role
            $crmRole = UserRole::where('code', 'avalanchecrm-staff')->first();
            if ($crmRole && $existing->role_id !== $crmRole->id) {
                $existing->role_id = $crmRole->id;
                $existing->forceSave();
            }
            return;
        }

        $password = $password ?: Str::random(12);

        $nameParts = explode(' ', $this->name, 2);
        $firstName = $nameParts[0];
        $lastName = $nameParts[1] ?? '';

        $backendUser = new BackendUser();
        $backendUser->first_name = $firstName;
        $backendUser->last_name = $lastName;
        $backendUser->login = $this->email;
        $backendUser->email = $this->email;
        $backendUser->password = $password;
        $backendUser->password_confirmation = $password;
        $backendUser->is_activated = true;

        // Assign CRM Staff role
        $crmRole = UserRole::where('code', 'avalanchecrm-staff')->first();
        if ($crmRole) {
            $backendUser->role_id = $crmRole->id;
        }

        $backendUser->forceSave();

        $this->backend_user_id = $backendUser->id;
    }

    /**
     * Send a notification email to this staff member using a named template.
     *
     * @param string $category  Template category (e.g. 'client', 'ticket')
     * @param string $name      Template name (e.g. 'Staff Notification')
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
            \Log::error('Avalanche CRM: Failed to send notification "' . $name . '" to staff #' . $this->id . ': ' . $e->getMessage());
            return false;
        }
    }
}
