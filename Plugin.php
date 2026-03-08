<?php

namespace AvalancheStudio\AvalancheCRM;

use Backend\Facades\Backend;
use System\Classes\PluginBase;
use Winter\Storm\Support\Facades\Schema;
use Winter\User\Models\User as UserModel;
use Winter\User\Models\UserGroup;
use Winter\User\Controllers\Users as UsersController;
use Backend\Models\User as BackendUserModel;
use Event;
use AvalancheStudio\AvalancheCRM\Models\Settings;

/**
 * Avalanche CRM Plugin Information File
 */
class Plugin extends PluginBase
{
    /**
     * Returns information about this plugin.
     */
    public function pluginDetails(): array
    {
        return [
            'name' => 'avalanchestudio.avalanchecrm::lang.plugin.name',
            'description' => 'avalanchestudio.avalanchecrm::lang.plugin.description',
            'author' => 'AvalancheStudio',
            'icon' => 'icon-leaf'
        ];
    }

    /**
     * Register method, called when the plugin is first registered.
     */
    public function register(): void
    {
        // Winter CMS has no "public" directory â€” set dompdf's base path to the project root.
        $this->app['config']->set('dompdf.public_path', base_path());

        $this->app->register(\Barryvdh\DomPDF\ServiceProvider::class);

        // Register console commands
        $this->registerConsoleCommand('avalanchecrm.send-overdue-reminders', \AvalancheStudio\AvalancheCRM\Console\SendOverdueReminders::class);
        $this->registerConsoleCommand('avalanchecrm.send-renewal-reminders', \AvalancheStudio\AvalancheCRM\Console\SendRenewalReminders::class);
        $this->registerConsoleCommand('avalanchecrm.sync-users', \AvalancheStudio\AvalancheCRM\Console\SyncCrmUsers::class);
    }

    /**
     * Register scheduled tasks.
     */
    public function registerSchedule($schedule): void
    {
        // Send overdue invoice reminders daily at 9:00 AM
        $schedule->command('avalanchecrm:send-overdue-reminders')->dailyAt('09:00');

        // Send subscription renewal reminders daily at 9:00 AM
        $schedule->command('avalanchecrm:send-renewal-reminders')->dailyAt('09:00');
    }

    /**
     * Registers any markup tags implemented by this plugin.
     */
    public function registerMarkupTags(): array
    {
        return [
            'filters' => [
                'currency' => function ($value) {
                    $settings = \AvalancheStudio\AvalancheCRM\Models\Settings::instance();
                    $symbol = $settings->currency_symbol ?: '$';
                    $code = $settings->currency_code ?: 'USD';

                    return $symbol . number_format($value, 2) . ' ' . $code;
                }
            ]
        ];
    }

    /**
     * Boot method, called right before the request route.
     */
    public function boot(): void
    {
        $this->ensureUserGroupsExist();

        /*
                BackendUserModel::extend(function ($model) {
                    $model->bindEvent('model.afterSave', function () use ($model) {
                        // Sync to Staff if linked via backend_user_id
                        $staff = \AvalancheStudio\AvalancheCRM\Models\Staff::where('backend_user_id', $model->id)->first();
                        if ($staff) {
                            $staff->name = trim($model->first_name . ' ' . $model->last_name);
                            $staff->email = $model->email;
                            $staff->save();
                        }
                    });
                });
        */

        UserModel::extend(function ($model) {
            $model->hasOne['client'] = [\AvalancheStudio\AvalancheCRM\Models\Client::class];
            $model->hasOne['staff'] = [\AvalancheStudio\AvalancheCRM\Models\Staff::class];

            $model->bindEvent('model.beforeValidate', function () use ($model) {
                // trace_log('UserModel::beforeValidate called. Attributes: ' . json_encode($model->getAttributes()));
            });

            $model->bindEvent('model.afterCreate', function () use ($model) {
                // If we are in the backend User form, the FormController will automatically
                // create the related Staff/Client models via deferred bindings. We only need 
                // to manually create them if we are NOT in the backend form.
                if (request()->has('User')) {
                    return;
                }

                // Determine name and email
                $name = trim(($model->name ?? '') . ' ' . ($model->surname ?? ''));
                $email = $model->email;

                if (request()->input('is_staff')) {
                    $staff = new \AvalancheStudio\AvalancheCRM\Models\Staff();
                    $staff->user_id = $model->id;
                    $staff->name = $name ?: $email; // Fallback to email if name is missing
                    $staff->email = $email;
                    $staff->save();
                }

                if (request()->input('is_client')) {
                    $client = new \AvalancheStudio\AvalancheCRM\Models\Client();
                    $client->user_id = $model->id;
                    $client->name = $name ?: $email;
                    $client->email = $email;
                    $client->save();
                }
            });

            $model->bindEvent('model.afterSave', function () use ($model) {
                $staff = $model->staff;
                if ($staff) {
                    if ($staffData = post('staff')) {
                        $staff->fill($staffData);
                    }
                    $staff->name = trim(($model->name ?? '') . ' ' . ($model->surname ?? '')) ?: $model->email;
                    $staff->email = $model->email;
                    $staff->save();
                }

                $client = $model->client;
                if ($client) {
                    $client->name = trim(($model->name ?? '') . ' ' . ($model->surname ?? '')) ?: $model->email;
                    $client->email = $model->email;
                    $client->save();

                    // Save marketing opt-out preference for clients
                    if ($marketingData = post('client_marketing')) {
                        $client->marketing_opt_out = !empty($marketingData['marketing_opt_out']);
                        $client->save();
                    }
                }
            });
        });

        // This event fires AFTER all form relations (including groups pivot) are saved.
        // This is the correct place to create Client/Staff records when a group is
        // assigned to an existing user, because group membership is accurate here.
        Event::listen('backend.form.afterUpdate', function ($widget) {
            if (!$widget->getController() instanceof UsersController) {
                return;
            }

            $model = $widget->model;

            if (!$model instanceof UserModel) {
                return;
            }

            $name = trim(($model->name ?? '') . ' ' . ($model->surname ?? '')) ?: $model->email;

            // Create Staff CRM record if user is in the staff group but has no Staff record yet
            $isStaff = $model->groups()->where('code', 'staff')->exists();
            if ($isStaff && !$model->staff) {
                $staff = new \AvalancheStudio\AvalancheCRM\Models\Staff();
                $staff->user_id = $model->id;
                $staff->name = $name;
                $staff->email = $model->email;
                $staff->save();
            }

            // Create Client CRM record if user is in the client group but has no Client record yet
            $isClient = $model->groups()->where('code', 'client')->exists();
            if ($isClient && !$model->client) {
                $client = new \AvalancheStudio\AvalancheCRM\Models\Client();
                $client->user_id = $model->id;
                $client->name = $name;
                $client->email = $model->email;
                $client->save();
            }
        });

        Event::listen('backend.form.extendFieldsBefore', function ($widget) {
            if ($widget->getController() instanceof UsersController && post()) {
                trace_log('Form submission data: ' . json_encode(post()));
            }

            if (!$widget->getController() instanceof UsersController) {
                return;
            }

            if (!$widget->model instanceof UserModel) {
                return;
            }

            // Pre-select 'Client' group if redirecting from CRM
            if ($widget->getContext() === 'create' && request()->input('is_client')) {
                $clientGroup = UserGroup::where('code', 'client')->first();
                if ($clientGroup) {
                    if (isset($widget->tabs['fields']['groups'])) {
                        $widget->tabs['fields']['groups']['default'] = [$clientGroup->id];
                    }
                }
            }

            // Pre-select 'Staff' group if redirecting from CRM
            if ($widget->getContext() === 'create' && request()->input('is_staff')) {
                $staffGroup = UserGroup::where('code', 'staff')->first();
                if ($staffGroup) {
                    if (isset($widget->tabs['fields']['groups'])) {
                        $widget->tabs['fields']['groups']['default'] = [$staffGroup->id];
                    }
                }
            }
        });

        Event::listen('backend.form.extendFields', function ($widget) {
            if (!$widget->getController() instanceof UsersController) {
                return;
            }

            if (!$widget->model instanceof UserModel) {
                return;
            }

            // Only show Staff tab if the user is in the Staff group or is_staff is requested
            $isStaff = $widget->model->groups()->where('code', 'staff')->exists() || request()->input('is_staff');

            if ($isStaff) {
                $widget->addTabFields([
                    'staff[job_title]' => [
                        'label' => 'Job Title',
                        'tab' => 'Staff',
                        'span' => 'left'
                    ],
                    'staff[department]' => [
                        'label' => 'Department',
                        'tab' => 'Staff',
                        'span' => 'right'
                    ]
                ]);
            }

            // Only show Client tabs if the user is in the Client group or is_client is requested
            $isClient = $widget->model->groups()->where('code', 'client')->exists() || request()->input('is_client');

            if ($isClient) {
                $settings = Settings::instance();
                $fields = [];

                if ($settings->enable_marketing) {
                    $fields['client_marketing'] = [
                        'tab' => 'Marketing',
                        'type' => 'partial',
                        'path' => '$/avalanchestudio/avalanchecrm/views/user_tabs/_marketing.htm'
                    ];
                }

                if ($settings->enable_tickets) {
                    $fields['client_tickets'] = [
                        'tab' => 'Tickets',
                        'type' => 'partial',
                        'path' => '$/avalanchestudio/avalanchecrm/views/user_tabs/_tickets.htm'
                    ];
                }

                if ($settings->enable_projects) {
                    $fields['client_projects'] = [
                        'tab' => 'Projects',
                        'type' => 'partial',
                        'path' => '$/avalanchestudio/avalanchecrm/views/user_tabs/_projects.htm'
                    ];
                }

                if ($settings->enable_invoices) {
                    $fields['client_invoices'] = [
                        'tab' => 'Invoices',
                        'type' => 'partial',
                        'path' => '$/avalanchestudio/avalanchecrm/views/user_tabs/_invoices.htm'
                    ];
                }

                if ($settings->enable_subscriptions) {
                    $fields['client_subscriptions'] = [
                        'tab' => 'Subscriptions',
                        'type' => 'partial',
                        'path' => '$/avalanchestudio/avalanchecrm/views/user_tabs/_subscriptions.htm'
                    ];
                }

                $widget->addTabFields($fields);
            }
        });

        // Conditionally hide navigation items
        Event::listen('backend.menu.extendItems', function ($manager) {
            $settings = Settings::instance();

            if (!$settings->enable_projects) {
                $manager->removeSideMenuItem('AvalancheStudio.AvalancheCRM', 'avalanchecrm', 'projects');
            }

            if (!$settings->enable_tickets) {
                $manager->removeSideMenuItem('AvalancheStudio.AvalancheCRM', 'avalanchecrm', 'tickets');
            }

            if (!$settings->enable_invoices) {
                $manager->removeSideMenuItem('AvalancheStudio.AvalancheCRM', 'avalanchecrm', 'invoices');
            }

            if (!$settings->enable_subscriptions) {
                $manager->removeSideMenuItem('AvalancheStudio.AvalancheCRM', 'avalanchecrm', 'subscriptions');
            }

            if (!$settings->enable_marketing) {
                $manager->removeSideMenuItem('AvalancheStudio.AvalancheCRM', 'avalanchecrm', 'campaigns');
                $manager->removeSideMenuItem('AvalancheStudio.AvalancheCRM', 'avalanchecrm', 'templates');
            }
        });
    }

    /**
     * Ensure required user groups exist for the CRM.
     */
    protected function ensureUserGroupsExist(): void
    {
        if (!class_exists(UserGroup::class) || !Schema::hasTable('user_groups')) {
            return;
        }

        $groups = [
            [
                'name' => 'Client',
                'code' => 'client',
                'description' => 'CRM Clients Group',
            ],
            [
                'name' => 'Staff',
                'code' => 'staff',
                'description' => 'CRM Staff Group',
            ],
        ];

        foreach ($groups as $group) {
            if (!UserGroup::where('code', $group['code'])->exists()) {
                UserGroup::create($group);
            }
        }

        // Ensure the CRM Staff backend role exists
        $this->ensureBackendRoleExists();
    }

    /**
     * Ensure the CRM Staff backend role exists with all required permissions.
     */
    protected function ensureBackendRoleExists(): void
    {
        if (!Schema::hasTable('backend_user_roles')) {
            return;
        }

        // Check by both code and name to avoid unique-validation failures
        // after a plugin rename where the old role still exists.
        $exists = \Db::table('backend_user_roles')
            ->where('code', 'avalanchecrm-staff')
            ->orWhere('name', 'CRM Staff')
            ->exists();

        if (!$exists) {
            \Db::table('backend_user_roles')->insert([
                'name' => 'CRM Staff',
                'code' => 'avalanchecrm-staff',
                'description' => 'Backend role for CRM staff members with access to all CRM features and settings.',
                'permissions' => json_encode([
                    'avalanchestudio.avalanchecrm.*' => 1,
                    'avalanchestudio.avalanchecrm.manage_settings' => 1,
                    'avalanchestudio.avalanchecrm.tickets.*' => 1,
                    'avalanchestudio.avalanchecrm.marketing.*' => 1,
                ]),
                'is_system' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Registers any frontend components implemented in this plugin.
     */
    public function registerComponents(): array
    {
        return [
            \AvalancheStudio\AvalancheCRM\Components\Dashboard::class => 'dashboard',
            \AvalancheStudio\AvalancheCRM\Components\Subscriptions::class => 'subscriptions',
            \AvalancheStudio\AvalancheCRM\Components\Projects::class => 'projects',
            \AvalancheStudio\AvalancheCRM\Components\Tickets::class => 'tickets',
            \AvalancheStudio\AvalancheCRM\Components\Invoices::class => 'invoices',
            \AvalancheStudio\AvalancheCRM\Components\Account::class => 'crmAccount',
        ];
    }

    /**
     * Registers any backend permissions used by this plugin.
     */
    public function registerPermissions(): array
    {
        return [
            'avalanchestudio.avalanchecrm.*' => [
                'tab' => 'Avalanche CRM',
                'label' => 'Manage all CRM features',
            ],
            'avalanchestudio.avalanchecrm.manage_settings' => [
                'tab' => 'Avalanche CRM',
                'label' => 'Manage CRM Settings',
            ],
            'avalanchestudio.avalanchecrm.tickets.*' => [
                'tab' => 'Avalanche CRM',
                'label' => 'Access Tickets section',
            ],
            'avalanchestudio.avalanchecrm.marketing.*' => [
                'tab' => 'Avalanche CRM',
                'label' => 'Manage Email Marketing',
            ],
        ];
    }

    /**
     * Registers backend navigation items for this plugin.
     */
    public function registerNavigation(): array
    {
        return [
            'avalanchecrm' => [
                'label' => 'avalanchestudio.avalanchecrm::lang.navigation.crm',
                'url' => Backend::url('avalanchestudio/avalanchecrm/dashboard'),
                'iconSvg' => '/plugins/avalanchestudio/avalanchecrm/assets/images/mountain.svg',
                'permissions' => ['avalanchestudio.avalanchecrm.*'],
                'order' => 500,
                'sideMenu' => [
                    'dashboard' => [
                        'label' => 'avalanchestudio.avalanchecrm::lang.navigation.dashboard',
                        'icon' => 'icon-dashboard',
                        'url' => Backend::url('avalanchestudio/avalanchecrm/dashboard'),
                        'permissions' => ['avalanchestudio.avalanchecrm.*'],
                    ],
                    'clients' => [
                        'label' => 'avalanchestudio.avalanchecrm::lang.navigation.clients',
                        'icon' => 'icon-users',
                        'url' => Backend::url('avalanchestudio/avalanchecrm/clients'),
                        'permissions' => ['avalanchestudio.avalanchecrm.*'],
                    ],
                    'staff' => [
                        'label' => 'avalanchestudio.avalanchecrm::lang.navigation.staff',
                        'icon' => 'icon-user-tie',
                        'url' => Backend::url('avalanchestudio/avalanchecrm/staff'),
                        'permissions' => ['avalanchestudio.avalanchecrm.*'],
                    ],
                    'projects' => [
                        'label' => 'avalanchestudio.avalanchecrm::lang.navigation.projects',
                        'icon' => 'icon-briefcase',
                        'url' => Backend::url('avalanchestudio/avalanchecrm/projects'),
                        'permissions' => ['avalanchestudio.avalanchecrm.*'],
                    ],
                    'tickets' => [
                        'label' => 'avalanchestudio.avalanchecrm::lang.navigation.tickets',
                        'icon' => 'icon-ticket',
                        'url' => Backend::url('avalanchestudio/avalanchecrm/tickets'),
                        'permissions' => ['avalanchestudio.avalanchecrm.*'],
                    ],
                    'invoices' => [
                        'label' => 'avalanchestudio.avalanchecrm::lang.navigation.invoices',
                        'icon' => 'icon-file-text-o',
                        'url' => Backend::url('avalanchestudio/avalanchecrm/invoices'),
                        'permissions' => ['avalanchestudio.avalanchecrm.*'],
                    ],
                    'subscriptions' => [
                        'label' => 'avalanchestudio.avalanchecrm::lang.navigation.subscriptions',
                        'icon' => 'icon-refresh',
                        'url' => Backend::url('avalanchestudio/avalanchecrm/subscriptions'),
                        'permissions' => ['avalanchestudio.avalanchecrm.*'],
                    ],
                    'campaigns' => [
                        'label' => 'avalanchestudio.avalanchecrm::lang.navigation.marketing',
                        'icon' => 'icon-envelope',
                        'url' => Backend::url('avalanchestudio/avalanchecrm/campaigns'),
                        'permissions' => ['avalanchestudio.avalanchecrm.marketing.*'],
                        'sideMenu' => [
                            'campaigns' => [
                                'label' => 'avalanchestudio.avalanchecrm::lang.navigation.campaigns',
                                'icon' => 'icon-bullhorn',
                                'url' => Backend::url('avalanchestudio/avalanchecrm/campaigns'),
                                'permissions' => ['avalanchestudio.avalanchecrm.marketing.*'],
                            ],
                        ]
                    ],
                    'templates' => [
                        'label' => 'avalanchestudio.avalanchecrm::lang.navigation.email_templates',
                        'icon' => 'icon-file-code-o',
                        'url' => Backend::url('avalanchestudio/avalanchecrm/emailtemplates'),
                        'permissions' => ['avalanchestudio.avalanchecrm.marketing.*'],
                    ],
                    'logs' => [
                        'label' => 'Activity Logs',
                        'icon' => 'icon-list',
                        'url' => Backend::url('avalanchestudio/avalanchecrm/activitylogs'),
                        'permissions' => ['avalanchestudio.avalanchecrm.*'],
                        'order' => 999,
                    ],

                ]
            ],

        ];
    }

    /**
     * Registers backend settings for this plugin.
     */
    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label' => 'avalanchestudio.avalanchecrm::lang.models.settings.label',
                'description' => 'avalanchestudio.avalanchecrm::lang.models.settings.description',
                'category' => 'Avalanche CRM',
                'icon' => 'icon-cog',
                'class' => \AvalancheStudio\AvalancheCRM\Models\Settings::class,
                'order' => 500,
                'keywords' => 'crm payments stripe paypal gocardless settings',
                'permissions' => ['avalanchestudio.avalanchecrm.manage_settings']
            ]
        ];
    }
}
