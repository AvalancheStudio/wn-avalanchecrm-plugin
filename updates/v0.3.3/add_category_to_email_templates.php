<?php

namespace AvalancheStudio\AvalancheCRM\Updates;

use Winter\Storm\Database\Updates\Migration;
use Winter\Storm\Support\Facades\Schema;

class AddCategoryToEmailTemplates extends Migration
{
    public function up()
    {
        if (Schema::hasColumn('avalanchestudio_avalanchecrm_email_templates', 'category')) {
            return;
        }

        Schema::table('avalanchestudio_avalanchecrm_email_templates', function ($table) {
            $table->string('category', 40)->default('marketing')->after('name');
        });

        // Update existing templates to marketing category
        \Db::table('avalanchestudio_avalanchecrm_email_templates')
            ->whereNull('category')
            ->orWhere('category', '')
            ->update(['category' => 'marketing']);
    }

    public function down()
    {
        Schema::table('avalanchestudio_avalanchecrm_email_templates', function ($table) {
            $table->dropColumn('category');
        });
    }
}
