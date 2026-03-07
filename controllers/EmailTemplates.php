<?php

namespace AvalancheStudio\AvalancheCRM\Controllers;

use Backend\Classes\Controller;
use Backend\Facades\BackendMenu;
use AvalancheStudio\AvalancheCRM\Models\EmailTemplate;
use Winter\Storm\Exception\ApplicationException;
use Flash;

/**
 * Email Templates Backend Controller
 */
class EmailTemplates extends Controller
{
    /**
     * @var array Behaviors that are implemented by this controller.
     */
    public $implement = [
        \Backend\Behaviors\FormController::class,
        \Backend\Behaviors\ListController::class,
    ];

    /**
     * @var array Permissions required to view this page.
     */
    protected $requiredPermissions = [
        'avalanchestudio.avalanchecrm.emailtemplates.manage_all',
    ];

    /**
     * Category definitions with metadata.
     */
    protected function getCategoryDefinitions(): array
    {
        return [
            'marketing' => [
                'label' => 'Marketing',
                'icon' => 'icon-bullhorn',
                'color' => '#8e44ad',
                'description' => 'Email templates for marketing campaigns, newsletters and promotional offers.',
            ],
            'client' => [
                'label' => 'Client Notifications',
                'icon' => 'icon-user',
                'color' => '#27ae60',
                'description' => 'Welcome messages, account updates and general client communications.',
            ],
            'project' => [
                'label' => 'Project Notifications',
                'icon' => 'icon-briefcase',
                'color' => '#17a2b8',
                'description' => 'Project creation, status update and completion notifications.',
            ],
            'ticket' => [
                'label' => 'Ticket Notifications',
                'icon' => 'icon-ticket',
                'color' => '#6f42c1',
                'description' => 'Support ticket creation, reply and resolution notifications.',
            ],
            'invoice' => [
                'label' => 'Invoice Notifications',
                'icon' => 'icon-file-text-o',
                'color' => '#fd7e14',
                'description' => 'Invoice delivery, payment confirmations and overdue reminders.',
            ],
            'subscription' => [
                'label' => 'Subscription Notifications',
                'icon' => 'icon-refresh',
                'color' => '#2980b9',
                'description' => 'Subscription activation, renewal reminders and cancellation notices.',
            ],
        ];
    }

    /**
     * Index page â€” category dashboard with full list.
     */
    public function index()
    {
        $this->asExtension('ListController')->index();

        $definitions = $this->getCategoryDefinitions();
        $categories = [];

        $settings = \AvalancheStudio\AvalancheCRM\Models\Settings::instance();
        foreach ($definitions as $key => $def) {
            if ($key === 'marketing' && !$settings->enable_marketing)
                continue;
            if ($key === 'project' && !$settings->enable_projects)
                continue;
            if ($key === 'ticket' && !$settings->enable_tickets)
                continue;
            if ($key === 'invoice' && !$settings->enable_invoices)
                continue;
            if ($key === 'subscription' && !$settings->enable_subscriptions)
                continue;

            $templates = EmailTemplate::where('category', $key)
                ->orderBy('name')
                ->get();

            $categories[$key] = array_merge($def, [
                'count' => $templates->count(),
                'templates' => $templates,
            ]);
        }

        $this->vars['categories'] = $categories;
    }

    /**
     * Pre-select category when creating a template from a category card.
     */
    public function formExtendFields($form)
    {
        if ($form->context === 'create' && ($cat = request()->get('category'))) {
            $options = (new EmailTemplate)->getCategoryOptions();
            if (array_key_exists($cat, $options)) {
                $form->getField('category')->value = $cat;
            }
        }
    }

    /**
     * Filter the template list by category when requested via URL.
     */
    public function listExtendQuery($query)
    {
        if ($category = request()->get('category')) {
            $query->where('category', $category);
        }
    }

    /**
     * Load settings modal for a category
     */
    public function onLoadSettings()
    {
        $category = post('category');
        $definitions = $this->getCategoryDefinitions();

        if (!isset($definitions[$category])) {
            throw new \ApplicationException('Invalid category');
        }

        $settings = \AvalancheStudio\AvalancheCRM\Models\Settings::instance();

        $this->vars['category'] = $category;
        $this->vars['label'] = $definitions[$category]['label'];
        $this->vars['settings'] = $settings;

        return $this->makePartial('settings_form');
    }

    /**
     * Save settings for a category
     */
    public function onSaveSettings()
    {
        $category = post('category');
        $definitions = $this->getCategoryDefinitions();

        if (!isset($definitions[$category])) {
            throw new \ApplicationException('Invalid category');
        }

        $settings = \AvalancheStudio\AvalancheCRM\Models\Settings::instance();

        $settings->{"email_{$category}_from_address"} = trim(post("email_{$category}_from_address", ''));
        $settings->{"email_{$category}_from_name"} = trim(post("email_{$category}_from_name", ''));
        $settings->{"email_{$category}_cc"} = trim(post("email_{$category}_cc", ''));
        $settings->{"email_{$category}_bcc"} = trim(post("email_{$category}_bcc", ''));

        $settings->save();

        \Flash::success('Settings saved successfully!');
    }
}
